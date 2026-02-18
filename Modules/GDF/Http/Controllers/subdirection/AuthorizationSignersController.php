<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AuthorizationSignersController extends Controller
{
    private const SIGN_DIR  = 'gdf/Firmas';                  // disk public
    private const JSON_PATH = 'gdf/authorization_signers.json'; // disk local

    public function __construct()
    {
        $this->middleware(['auth']);
    }

    private function guardSubdirection(): void
    {
        $ok = function_exists('checkRol') && (
            checkRol('gdf.subdirection') ||
            checkRol('gdf.superadmin') ||
            checkRol('superadmin')
        );
        if (!$ok) abort(403);
    }

    /* ============================================================
     * ORDEN POR ROL (NO MANUAL)
     * ============================================================ */
    private function roleOrder(?string $roleKey): int
    {
        return match ($roleKey) {
            'subdirection' => 1,
            'coordination' => 2,
            'treasury'     => 3,
            'support'      => 4,
            default        => 99,
        };
    }

    /* ============================================================
     * Helpers: columnas variables en people
     * ============================================================ */
    private function firstExistingColumn(string $table, array $candidates): ?string
    {
        foreach ($candidates as $col) {
            try {
                if (DB::getSchemaBuilder()->hasColumn($table, $col)) return $col;
            } catch (\Throwable $e) {}
        }
        return null;
    }

    /* ============================================================
     * JSON helpers
     * ============================================================ */
    private function readAll(): array
    {
        if (!Storage::disk('local')->exists(self::JSON_PATH)) return [];
        $raw = Storage::disk('local')->get(self::JSON_PATH);
        $arr = json_decode($raw, true);
        return is_array($arr) ? $arr : [];
    }

    private function writeAll(array $data): void
    {
        Storage::disk('local')->put(
            self::JSON_PATH,
            json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    private function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $r) $max = max($max, (int)($r['id'] ?? 0));
        return $max + 1;
    }

    private function findRow(array $rows, int $id): ?array
    {
        foreach ($rows as $r) {
            if ((int)($r['id'] ?? 0) === $id) return $r;
        }
        return null;
    }

    /* ============================================================
     * Áreas del sistema (para selector del form)
     * ============================================================ */
    private function fetchAreas()
    {
        if (!DB::getSchemaBuilder()->hasTable('areas')) return collect();

        return DB::table('areas')
            ->select('id', 'name', 'active')
            ->orderBy('name')
            ->get();
    }

    /* ============================================================
     * Áreas asignadas de una persona (gdf_area_user)
     * ============================================================ */
    private function getPersonAreas(int $personId): array
    {
        if ($personId <= 0) return [];

        if (
            !DB::getSchemaBuilder()->hasTable('gdf_area_user') ||
            !DB::getSchemaBuilder()->hasTable('users') ||
            !DB::getSchemaBuilder()->hasTable('areas')
        ) return [];

        $userId = DB::table('users')->where('person_id', $personId)->value('id');
        if (!$userId) return [];

        $rows = DB::table('gdf_area_user as gau')
            ->join('areas as a', 'a.id', '=', 'gau.area_id')
            ->where('gau.user_id', (int)$userId)
            ->where('gau.active', 1)
            ->orderBy('a.name')
            ->select('a.id', 'a.name')
            ->get();

        $areas = [];
        foreach ($rows as $r) {
            $areas[] = ['id' => (int)$r->id, 'name' => (string)$r->name];
        }
        return $areas;
    }

    /* ============================================================
     * FILTRO + ORDEN LISTADO (index)
     * - module: gdf|sitrav|both
     * - areaKey: all|academic|campesena
     * - type: all|global|area
     * ============================================================ */
    private function filterRows(array $rows, string $module, string $areaKey, string $q, string $type): array
    {
        $out = [];
        foreach ($rows as $r) {
            $rm = (string)($r['module'] ?? 'gdf'); // gdf|sitrav|both

            // módulo: si piden gdf → acepto gdf o both
            // si piden sitrav → acepto sitrav o both
            // si piden both → muestro todo (gdf/sitrav/both)
            if ($module !== 'both') {
                if ($rm !== 'both' && $rm !== $module) continue;
            }

            $rk = $r['area_key'] ?? null; // null/'' => global
            $isGlobal = is_null($rk) || $rk === '';

            // filtro type
            if ($type === 'global' && !$isGlobal) continue;
            if ($type === 'area' && $isGlobal) continue;

            // filtro por área (si filtras por área: muestra Global + esa área)
            if ($areaKey !== 'all') {
                if (!$isGlobal && $rk !== $areaKey) continue;
            }

            // búsqueda q
            if ($q !== '') {
                $hay = Str::lower(trim(
                    ($r['person_name'] ?? '') . ' ' .
                    ($r['person_document'] ?? '') . ' ' .
                    ($r['label'] ?? '') . ' ' .
                    ($r['position'] ?? '') . ' ' .
                    ($r['role_key'] ?? '')
                ));
                if (!Str::contains($hay, Str::lower($q))) continue;
            }

            $out[] = $r;
        }

        // ORDEN:
        // 1) Global primero
        // 2) orden por rol
        // 3) id estable
        usort($out, function ($a, $b) {
            $aGlobal = empty($a['area_key']) ? 0 : 1;
            $bGlobal = empty($b['area_key']) ? 0 : 1;
            if ($aGlobal !== $bGlobal) return $aGlobal <=> $bGlobal;

            $ao = $this->roleOrder($a['role_key'] ?? null);
            $bo = $this->roleOrder($b['role_key'] ?? null);
            if ($ao !== $bo) return $ao <=> $bo;

            return (int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0);
        });

        return $out;
    }

    /* ============================================================
     * AJAX: personas por ÁREA (gdf_area_user)
     * + permite buscar dentro del área por nombre/cédula
     * ============================================================ */
    public function peopleByArea(Request $request)
    {
        $this->guardSubdirection();

        $areaId = (int)$request->get('area_id', 0);
        $q      = trim((string)$request->get('q', ''));

        if ($areaId <= 0) return response()->json(['data' => []]);

        if (!DB::getSchemaBuilder()->hasTable('gdf_area_user')) {
            return response()->json(['data' => [], 'error' => 'Tabla gdf_area_user no existe']);
        }
        if (!DB::getSchemaBuilder()->hasTable('users') || !DB::getSchemaBuilder()->hasTable('people')) {
            return response()->json(['data' => [], 'error' => 'Faltan tablas users/people']);
        }

        $docCol   = $this->firstExistingColumn('people', ['document_number','document','identification_number','number_document','dni']);
        $emailCol = $this->firstExistingColumn('people', ['email','personal_email','institutional_email']);
        $phoneCol = $this->firstExistingColumn('people', ['phone','phone_number','mobile','cellphone','cell_phone']);

        $selectDoc   = $docCol   ? "p.{$docCol} as person_document" : "'' as person_document";
        $selectEmail = $emailCol ? "p.{$emailCol} as person_email"  : "'' as person_email";
        $selectPhone = $phoneCol ? "p.{$phoneCol} as person_phone"  : "'' as person_phone";

        $qb = DB::table('gdf_area_user as gau')
            ->join('users as u', 'u.id', '=', 'gau.user_id')
            ->join('people as p', 'p.id', '=', 'u.person_id')
            ->where('gau.area_id', $areaId)
            ->where('gau.active', 1);

        // ✅ Buscar por nombre o cédula dentro del área
        if ($q !== '' && mb_strlen($q) >= 2) {
            $qb->where(function ($qq) use ($q, $docCol) {
                $qq->whereRaw("CONCAT_WS(' ', p.first_name, p.first_last_name, p.second_last_name) LIKE ?", ["%{$q}%"]);
                if ($docCol) $qq->orWhere("p.{$docCol}", 'like', "%{$q}%");
            });
        }

        $rows = $qb->selectRaw("
                u.id as user_id,
                p.id as person_id,
                TRIM(CONCAT_WS(' ', p.first_name, p.first_last_name, p.second_last_name)) as person_name,
                {$selectDoc},
                {$selectEmail},
                {$selectPhone}
            ")
            ->orderBy('person_name')
            ->limit(60)
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $areas = $this->getPersonAreas((int)$r->person_id);
            $areaText = count($areas)
                ? ('Áreas: ' . implode(', ', array_map(fn($a)=>$a['name'], $areas)))
                : 'Sin áreas (Global)';

            $label = trim(($r->person_name ?: '—') . ' — ' . ($r->person_document ?: ''));
            $data[] = [
                'user_id'         => (int)$r->user_id,
                'person_id'       => (int)$r->person_id,
                'person_name'     => (string)$r->person_name,
                'person_document' => (string)$r->person_document,
                'person_email'    => (string)$r->person_email,
                'person_phone'    => (string)$r->person_phone,
                'areas'           => $areas,
                'area_text'       => $areaText,
                'label'           => $label,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /* ============================================================
     * AJAX: buscar GLOBAL por nombre/cédula (sin área)
     * + devuelve áreas asignadas
     * ============================================================ */
    public function peopleSearch(Request $request)
    {
        $this->guardSubdirection();

        $q = trim((string)$request->get('q', ''));
        if ($q === '' || mb_strlen($q) < 2) return response()->json(['data' => []]);

        if (!DB::getSchemaBuilder()->hasTable('people') || !DB::getSchemaBuilder()->hasTable('users')) {
            return response()->json(['data' => [], 'error' => 'Faltan tablas people/users']);
        }

        $docCol   = $this->firstExistingColumn('people', ['document_number','document','identification_number','number_document','dni']);
        $emailCol = $this->firstExistingColumn('people', ['email','personal_email','institutional_email']);
        $phoneCol = $this->firstExistingColumn('people', ['phone','phone_number','mobile','cellphone','cell_phone']);

        $selectDoc   = $docCol   ? "p.{$docCol} as person_document" : "'' as person_document";
        $selectEmail = $emailCol ? "p.{$emailCol} as person_email"  : "'' as person_email";
        $selectPhone = $phoneCol ? "p.{$phoneCol} as person_phone"  : "'' as person_phone";

        $rows = DB::table('people as p')
            ->leftJoin('users as u', 'u.person_id', '=', 'p.id')
            ->selectRaw("
                p.id as person_id,
                u.id as user_id,
                TRIM(CONCAT_WS(' ', p.first_name, p.first_last_name, p.second_last_name)) as person_name,
                {$selectDoc},
                {$selectEmail},
                {$selectPhone}
            ")
            ->where(function ($qq) use ($q, $docCol) {
                $qq->whereRaw("CONCAT_WS(' ', p.first_name, p.first_last_name, p.second_last_name) LIKE ?", ["%{$q}%"]);
                if ($docCol) $qq->orWhere("p.{$docCol}", 'like', "%{$q}%");
            })
            ->orderBy('person_name')
            ->limit(25)
            ->get();

        $data = [];
        foreach ($rows as $r) {
            $areas = $this->getPersonAreas((int)$r->person_id);
            $areaText = count($areas)
                ? ('Áreas: ' . implode(', ', array_map(fn($a)=>$a['name'], $areas)))
                : 'Sin áreas (Global)';

            $label = trim(($r->person_name ?: '—') . ' — ' . ($r->person_document ?: ''));
            $data[] = [
                'person_id'       => (int)$r->person_id,
                'user_id'         => (int)($r->user_id ?? 0),
                'person_name'     => (string)$r->person_name,
                'person_document' => (string)$r->person_document,
                'person_email'    => (string)$r->person_email,
                'person_phone'    => (string)$r->person_phone,
                'areas'           => $areas,
                'area_text'       => $areaText,
                'label'           => $label,
            ];
        }

        return response()->json(['data' => $data]);
    }

    /* ============================================================
     * AJAX: detalle persona (si lo necesitas por id)
     * ============================================================ */
    public function personDetails(Request $request)
    {
        $this->guardSubdirection();

        $personId = (int)$request->get('person_id', 0);
        if ($personId <= 0) return response()->json(['ok' => false, 'data' => null]);

        if (!DB::getSchemaBuilder()->hasTable('people') || !DB::getSchemaBuilder()->hasTable('users')) {
            return response()->json(['ok' => false, 'data' => null, 'error' => 'Faltan tablas people/users']);
        }

        $docCol   = $this->firstExistingColumn('people', ['document_number','document','identification_number','number_document','dni']);
        $emailCol = $this->firstExistingColumn('people', ['email','personal_email','institutional_email']);
        $phoneCol = $this->firstExistingColumn('people', ['phone','phone_number','mobile','cellphone','cell_phone']);

        $selectDoc   = $docCol   ? "p.{$docCol} as person_document" : "'' as person_document";
        $selectEmail = $emailCol ? "p.{$emailCol} as person_email"  : "'' as person_email";
        $selectPhone = $phoneCol ? "p.{$phoneCol} as person_phone"  : "'' as person_phone";

        $row = DB::table('people as p')
            ->leftJoin('users as u', 'u.person_id', '=', 'p.id')
            ->where('p.id', $personId)
            ->selectRaw("
                p.id as person_id,
                u.id as user_id,
                TRIM(CONCAT_WS(' ', p.first_name, p.first_last_name, p.second_last_name)) as person_name,
                {$selectDoc},
                {$selectEmail},
                {$selectPhone}
            ")
            ->first();

        if (!$row) return response()->json(['ok' => false, 'data' => null]);

        return response()->json([
            'ok' => true,
            'data' => [
                'person_id'       => (int)$row->person_id,
                'user_id'         => (int)($row->user_id ?? 0),
                'person_name'     => (string)$row->person_name,
                'person_document' => (string)$row->person_document,
                'person_email'    => (string)$row->person_email,
                'person_phone'    => (string)$row->person_phone,
                'areas'           => $this->getPersonAreas((int)$row->person_id),
            ],
        ]);
    }

    /* ============================================================
     * CRUD JSON
     * ============================================================ */
    public function index(Request $request)
    {
        $this->guardSubdirection();

        $module  = (string)$request->get('module', 'gdf');     // gdf|sitrav|both
        $areaKey = (string)$request->get('area_key', 'all');   // all|academic|campesena
        $type    = (string)$request->get('type', 'all');       // all|global|area
        $q       = trim((string)$request->get('q', ''));

        if (!in_array($module, ['gdf','sitrav','both'], true)) $module = 'gdf';
        if (!in_array($areaKey, ['all','academic','campesena'], true)) $areaKey = 'all';
        if (!in_array($type, ['all','global','area'], true)) $type = 'all';

        $rows = $this->readAll();
        $signers = $this->filterRows($rows, $module, $areaKey, $q, $type);

        return view('gdf::subdirection.authorization_signers.index', [
            'signers' => $signers,
            'module'  => $module,
            'areaKey' => $areaKey,
            'type'    => $type,
            'q'       => $q,
            'jsonPath'=> self::JSON_PATH,
        ]);
    }

    public function create(Request $request)
    {
        $this->guardSubdirection();

        $module  = (string)$request->get('module', 'gdf');       // gdf|sitrav|both
        $areaKey = (string)$request->get('area_key', 'academic'); // academic|campesena|all

        if (!in_array($module, ['gdf','sitrav','both'], true)) $module = 'gdf';
        if (!in_array($areaKey, ['academic','campesena','all'], true)) $areaKey = 'academic';

        return view('gdf::subdirection.authorization_signers.form', [
            'mode'   => 'create',
            'module' => $module,
            'areaKey'=> $areaKey === 'all' ? 'academic' : $areaKey,
            'row'    => [],
            'areas'  => $this->fetchAreas(),
        ]);
    }

    public function store(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'module'    => ['required','in:gdf,sitrav,both'],
            'role_key'  => ['required','in:subdirection,coordination,treasury,support'],

            // null => global
            'area_key'  => ['nullable','in:academic,campesena'],

            'person_id' => ['nullable','integer','min:1'],
            'user_id'   => ['nullable','integer','min:1'],

            'person_name'     => ['required','string','max:140'],
            'person_document' => ['required','string','max:30'],
            'person_email'    => ['nullable','string','max:120'],
            'person_phone'    => ['nullable','string','max:40'],

            'label'     => ['required','string','max:120'],
            'position'  => ['nullable','string','max:120'],

            'active'    => ['nullable','boolean'],
            'signature' => ['nullable','image','mimes:png,jpg,jpeg,webp','max:2048'],
        ]);

        // Forzar GLOBAL si es rol global
        if (in_array($data['role_key'], ['subdirection','treasury'], true)) {
            $data['area_key'] = null;
        }

        $rows = $this->readAll();
        $id = $this->nextId($rows);

        $signaturePath = null;
        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = "signer_{$id}_" . now()->format('Ymd_His') . ".{$ext}";
            $signaturePath = $file->storeAs(self::SIGN_DIR, $filename, 'public');
        }

        $rows[] = [
            'id'        => $id,
            'module'    => $data['module'],         // gdf|sitrav|both
            'role_key'  => $data['role_key'],       // orden automático por rol
            'area_key'  => $data['area_key'] ?? null, // null => global

            'person_id' => !empty($data['person_id']) ? (int)$data['person_id'] : null,
            'user_id'   => !empty($data['user_id']) ? (int)$data['user_id'] : null,

            'person_name'     => trim($data['person_name']),
            'person_document' => trim($data['person_document']),
            'person_email'    => trim((string)($data['person_email'] ?? '')) ?: null,
            'person_phone'    => trim((string)($data['person_phone'] ?? '')) ?: null,

            'label'     => trim($data['label']),
            'position'  => trim((string)($data['position'] ?? '')) ?: null,

            'active'    => $request->boolean('active', true) ? 1 : 0,
            'signature_path' => $signaturePath,

            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];

        $this->writeAll($rows);

        return redirect()
            ->route('gdf.subdirection.authorization_signers.index', [
                'module'   => $data['module'],
                'area_key' => $data['area_key'] ?? 'all',
            ])
            ->with('success', 'Firmante creado correctamente.');
    }

    public function edit(int $id)
    {
        $this->guardSubdirection();

        $rows = $this->readAll();
        $row = $this->findRow($rows, $id);
        if (!$row) abort(404);

        return view('gdf::subdirection.authorization_signers.form', [
            'mode'   => 'edit',
            'module' => $row['module'] ?? 'gdf',
            'areaKey'=> ($row['area_key'] ?? 'academic') ?: 'academic',
            'row'    => $row,
            'areas'  => $this->fetchAreas(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'module'    => ['required','in:gdf,sitrav,both'],
            'role_key'  => ['required','in:subdirection,coordination,treasury,support'],
            'area_key'  => ['nullable','in:academic,campesena'],

            'person_id' => ['nullable','integer','min:1'],
            'user_id'   => ['nullable','integer','min:1'],

            'person_name'     => ['required','string','max:140'],
            'person_document' => ['required','string','max:30'],
            'person_email'    => ['nullable','string','max:120'],
            'person_phone'    => ['nullable','string','max:40'],

            'label'     => ['required','string','max:120'],
            'position'  => ['nullable','string','max:120'],

            'active'    => ['nullable','boolean'],
            'signature' => ['nullable','image','mimes:png,jpg,jpeg,webp','max:2048'],
        ]);

        // Forzar GLOBAL si es rol global
        if (in_array($data['role_key'], ['subdirection','treasury'], true)) {
            $data['area_key'] = null;
        }

        $rows = $this->readAll();
        $found = false;

        foreach ($rows as &$r) {
            if ((int)($r['id'] ?? 0) !== $id) continue;
            $found = true;

            if ($request->hasFile('signature')) {
                $old = (string)($r['signature_path'] ?? '');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }

                $file = $request->file('signature');
                $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
                $filename = "signer_{$id}_" . now()->format('Ymd_His') . ".{$ext}";
                $r['signature_path'] = $file->storeAs(self::SIGN_DIR, $filename, 'public');
            }

            $r['module']   = $data['module'];
            $r['role_key'] = $data['role_key'];
            $r['area_key'] = $data['area_key'] ?? null;

            $r['person_id'] = !empty($data['person_id']) ? (int)$data['person_id'] : null;
            $r['user_id']   = !empty($data['user_id']) ? (int)$data['user_id'] : null;

            $r['person_name']     = trim($data['person_name']);
            $r['person_document'] = trim($data['person_document']);
            $r['person_email']    = trim((string)($data['person_email'] ?? '')) ?: null;
            $r['person_phone']    = trim((string)($data['person_phone'] ?? '')) ?: null;

            $r['label']    = trim($data['label']);
            $r['position'] = trim((string)($data['position'] ?? '')) ?: null;

            $r['active']     = $request->boolean('active', true) ? 1 : 0;
            $r['updated_at'] = now()->toDateTimeString();
            break;
        }
        unset($r);

        if (!$found) abort(404);

        $this->writeAll($rows);

        return redirect()
            ->route('gdf.subdirection.authorization_signers.index', [
                'module'   => $data['module'],
                'area_key' => $data['area_key'] ?? 'all',
            ])
            ->with('success', 'Firmante actualizado correctamente.');
    }

    public function destroy(int $id)
    {
        $this->guardSubdirection();

        $rows = $this->readAll();
        $new = [];
        $deleted = false;

        foreach ($rows as $r) {
            if ((int)($r['id'] ?? 0) === $id) {
                $deleted = true;
                $old = (string)($r['signature_path'] ?? '');
                if ($old !== '' && Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
                continue;
            }
            $new[] = $r;
        }

        if (!$deleted) abort(404);

        $this->writeAll($new);

        return back()->with('success', 'Firmante eliminado.');
    }
}
