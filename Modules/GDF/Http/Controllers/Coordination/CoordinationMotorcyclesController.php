<?php

namespace Modules\GDF\Http\Controllers\Coordination;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;

use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\Motorcycle;
use Modules\GDF\Entities\MotorcycleAssignment;
use Modules\GDF\Entities\MotorcycleAreaQuota;

use Modules\SICA\Entities\Employee;
use Modules\SICA\Entities\Contractor;
use App\Models\User;

class CoordinationMotorcyclesController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /* =========================
     | Guards / Helpers
     * ========================= */

    private function areaKeyFromPath(Request $request): string
    {
        return str_contains($request->path(), 'gdf/campesena') ? 'campesena' : 'academic';
    }

    private function guardCoordOrSupport(string $areaKey): void
    {
        $areaKey = strtolower(trim($areaKey));

        $ok = function_exists('checkRol') && (
            ($areaKey === 'academic'  && (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support'))) ||
            ($areaKey === 'campesena' && (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support'))) ||
            checkRol('gdf.superadmin')
        );

        if (!$ok) abort(403);
    }

    private function routePrefix(string $areaKey): string
    {
        return strtolower(trim($areaKey)) === 'campesena' ? 'campesena' : 'academic';
    }

    private function resolveAreaId(string $areaKey): int
    {
        $areaKey = strtolower(trim($areaKey));

        if ($areaKey === 'campesena') {
            $area = Area::query()
                ->select(['id', 'name'])
                ->whereRaw("UPPER(name) LIKE '%CAMPESENA%'")
                ->first();

            if (!$area) abort(500, 'No se encontró el área CAMPESENA en areas.name.');
            return (int) $area->id;
        }

        if ($areaKey === 'academic') {
            $area = Area::query()
                ->select(['id', 'name'])
                ->whereRaw("UPPER(name) LIKE '%COORDINACION%'")
                ->where(function ($q) {
                    $q->whereRaw("UPPER(name) LIKE '%ACADEM%'")
                        ->orWhereRaw("UPPER(name) LIKE '%ACADÉM%'");
                })
                ->first();

            if (!$area) abort(500, 'No se encontró el área COORDINACIÓN ACADÉMICA en areas.name.');
            return (int) $area->id;
        }

        abort(422, 'Área inválida. Use academic|campesena.');
    }

    private function quotaMetrics(int $areaId, int $year): array
    {
        $quota = (int) MotorcycleAreaQuota::query()
            ->where('area_id', $areaId)
            ->where('year', $year)
            ->where('active', 1)
            ->value('quota_total');

        $used = (int) Motorcycle::query()
            ->where('current_area_id', $areaId)
            ->count();

        $available = max($quota - $used, 0);

        return compact('quota', 'used', 'available');
    }

    private function validateQuotaOrFail(int $areaId, int $year): void
    {
        $m = $this->quotaMetrics($areaId, $year);

        if ($m['quota'] <= 0) abort(422, 'El área no tiene cupo configurado para este año.');
        if ($m['used'] >= $m['quota']) abort(422, 'Cupo del área agotado. No se puede ubicar otra moto.');
    }

    /* =====================================================
     | (Opcional) Si tu quotaStatus lo requiere, define esto
     | Ajusta según tu modelo real (si academic/campesena agrupa varias áreas)
     * ===================================================== */
    private function areaIdsByKey(string $areaKey): array
    {
        $areaKey = strtolower(trim($areaKey));

        // Por defecto: 1 sola área (la que resuelve por name)
        // Si luego quieres agrupar varias áreas por key, aquí es donde lo ajustas.
        return [$this->resolveAreaId($areaKey)];
    }

    /* =========================
     | Index (motorcycles.index)
     * ========================= */

    public function index(Request $request)
    {
        $areaKey = $request->get('area') ?: $this->areaKeyFromPath($request);
        $this->guardCoordOrSupport((string) $areaKey);

        $year = (int) ($request->get('year') ?: now()->year);

        return redirect()->route(
            'gdf.' . $this->routePrefix((string)$areaKey) . '.motorcycles.assign.create',
            ['area' => (string)$areaKey, 'year' => $year]
        );
    }

    /* =========================
     | Assign Create (GET)
     * ========================= */

    public function assignCreate(Request $request)
    {
        $areaKey = (string) $request->get('area', 'academic');
        $this->guardCoordOrSupport($areaKey);

        $areaId = $this->resolveAreaId($areaKey);
        $year   = (int) ($request->get('year') ?: now()->year);

        $metrics   = $this->quotaMetrics($areaId, $year);
        $quota     = (int) $metrics['quota'];
        $used      = (int) $metrics['used'];
        $available = (int) $metrics['available'];

        $motorcycles = Motorcycle::query()
            ->select(['id', 'plate', 'brand', 'model', 'current_area_id', 'status', 'current_odometer'])
            ->where('status', 'available')
            ->where(function ($q) use ($areaId) {
                $q->whereNull('current_area_id')
                    ->orWhere('current_area_id', $areaId);
            })
            ->orderBy('plate')
            ->get();

        $inventory = Motorcycle::query()
            ->select(['id', 'plate', 'brand', 'model', 'status', 'current_odometer', 'current_area_id'])
            ->where('current_area_id', $areaId)
            ->orderBy('plate')
            ->get();

        $activeAssignments = MotorcycleAssignment::query()
            ->with([
                'person:id,document_number,first_name,first_last_name,second_last_name',
                'motorcycle:id,plate'
            ])
            ->where('area_id', $areaId)
            ->whereIn('status', ['approved', 'delivered'])
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $searchRoute = route('gdf.' . $this->routePrefix($areaKey) . '.motorcycles.search_person', [
            'area' => $areaKey,
            'year' => $year,
        ]);

        $postRoute = route('gdf.' . $this->routePrefix($areaKey) . '.motorcycles.assign.store');

        $peopleRoute = route('gdf.' . $this->routePrefix($areaKey) . '.motorcycles.people_in_area', [
            'area' => $areaKey,
            'year' => $year,
        ]);

        return view('gdf::coordination.motorcycles.assign', compact(
            'areaKey',
            'areaId',
            'year',
            'quota',
            'used',
            'available',
            'motorcycles',
            'inventory',
            'activeAssignments',
            'searchRoute',
            'postRoute',
            'peopleRoute'
        ));
    }

    /* =========================
     | Search person (AJAX)
     | - busca por cédula (y opcional nombre)
     | - contratista OK si end >= hoy-8 o NULL (opcional)
     * ========================= */

    public function searchPerson(Request $request)
    {
        $areaKey = (string) ($request->get('area') ?: $this->areaKeyFromPath($request));
        $this->guardCoordOrSupport($areaKey);

        $q = trim((string) $request->get('q', ''));
        if ($q === '' || strlen($q) < 3) {
            return response()->json(['ok' => true, 'items' => []]);
        }

        $today  = Carbon::today();
        $minEnd = $today->copy()->subDays(8);

        // IMPORTANTE: ->toBase() antes de mapear a arrays
        $employees = Employee::query()
            ->with(['person:id,document_number,first_name,first_last_name,second_last_name'])
            ->whereHas('person', function ($p) use ($q) {
                $p->where('document_number', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get()
            ->toBase()
            ->map(function ($e) {
                $person = $e->person;
                if (!$person) return null;

                $hasUser = User::where('person_id', $person->id)->exists();

                return [
                    'source'            => 'employee',
                    'person_id'         => (int) $person->id,
                    'document_number'   => $person->document_number,
                    'full_name'         => trim($person->first_name . ' ' . $person->first_last_name . ' ' . $person->second_last_name),
                    'has_user'          => (bool) $hasUser,
                    'is_active_contract' => true,
                    'contract_end_date' => null,
                ];
            })
            ->filter()
            ->values();

        $contractors = Contractor::query()
            ->with(['person:id,document_number,first_name,first_last_name,second_last_name'])
            ->whereHas('person', function ($p) use ($q) {
                $p->where('document_number', 'like', "%{$q}%");
            })
            ->limit(10)
            ->get()
            ->toBase()
            ->map(function ($c) use ($minEnd) {
                $person = $c->person;
                if (!$person) return null;

                $hasUser = User::where('person_id', $person->id)->exists();

                $end = $c->contract_end_date ? Carbon::parse($c->contract_end_date) : null;

                // Regla: OK si end >= hoy-8; NULL => OK (si quieres bloquear NULL, cambia a false)
                $isOk = $end ? $end->gte($minEnd) : true;

                return [
                    'source'            => 'contractor',
                    'person_id'         => (int) $person->id,
                    'document_number'   => $person->document_number,
                    'full_name'         => trim($person->first_name . ' ' . $person->first_last_name . ' ' . $person->second_last_name),
                    'has_user'          => (bool) $hasUser,
                    'is_active_contract' => (bool) $isOk,
                    'contract_end_date' => $end ? $end->toDateString() : null,
                ];
            })
            ->filter()
            ->values();

        // Ya ambos son Base Collection (arrays), merge no va a llamar getKey()
        $merged = $employees->merge($contractors)
            ->groupBy('person_id')
            ->map(function ($group) {
                return $group
                    ->sortByDesc(fn($x) => (int)($x['is_active_contract'] === true))
                    ->sortBy(fn($x) => $x['source'] === 'employee' ? 0 : 1)
                    ->first();
            })
            ->values()
            ->sortByDesc(fn($x) => (int)($x['is_active_contract'] === true))
            ->values();

        return response()->json(['ok' => true, 'items' => $merged]);
    }


    /* =========================
     | Assign Store (POST)
     | - existing/new
     | - 1 moto por persona + 1 persona por moto (locks)
     | - contratista OK si end >= hoy-8 o NULL (opcional)
     * ========================= */

    public function assignStore(Request $request)
    {
        $areaKey = (string) ($request->get('area') ?: $this->areaKeyFromPath($request));
        $this->guardCoordOrSupport($areaKey);

        $areaId = $this->resolveAreaId($areaKey);

        $data = $request->validate([
            'area' => ['required', 'in:academic,campesena'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],

            'person_id'     => ['required', 'integer', 'exists:people,id'],
            'person_source' => ['required', 'in:employee,contractor'],

            'motorcycle_mode' => ['required', 'in:existing,new'],
            'motorcycle_id'   => ['required_if:motorcycle_mode,existing', 'nullable', 'integer', 'exists:motorcycles,id'],

            'plate'            => ['required_if:motorcycle_mode,new', 'nullable', 'string', 'max:20'],
            'brand'            => ['nullable', 'string', 'max:60'],
            'model'            => ['nullable', 'string', 'max:60'],
            'current_odometer' => ['required_if:motorcycle_mode,new', 'nullable', 'integer', 'min:0'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $year     = (int) $data['year'];
        $personId = (int) $data['person_id'];

        // Contratista: gracia 8 días
        if ($data['person_source'] === 'contractor') {
            $today  = Carbon::today();
            $minEnd = $today->copy()->subDays(8);

            $hasOk = Contractor::query()
                ->where('person_id', $personId)
                ->where(function ($q) use ($minEnd) {
                    // Si prefieres BLOQUEAR NULL, elimina esta línea
                    $q->whereNull('contract_end_date')
                        ->orWhereDate('contract_end_date', '>=', $minEnd);
                })
                ->exists();

            if (!$hasOk) {
                return back()->withInput()->with('error', 'El contratista tiene el contrato vencido hace más de 8 días.');
            }
        }

        // Pre-check persona sin asignación activa
        $hasActiveAssignment = MotorcycleAssignment::query()
            ->where('person_id', $personId)
            ->whereIn('status', ['approved', 'delivered'])
            ->exists();

        if ($hasActiveAssignment) {
            return back()->withInput()->with('error', 'La persona ya tiene una asignación activa.');
        }

        // pre-check cupo
        $this->validateQuotaOrFail($areaId, $year);

        try {
            DB::transaction(function () use ($data, $areaId, $year, $personId) {

                // 0) Re-check persona sin asignación activa (TX + lock)
                $hasActiveAssignmentTx = MotorcycleAssignment::query()
                    ->where('person_id', $personId)
                    ->whereIn('status', ['approved', 'delivered'])
                    ->lockForUpdate()
                    ->exists();

                if ($hasActiveAssignmentTx) {
                    abort(422, 'La persona ya tiene una asignación activa.');
                }

                // 1) Re-check cupo in-tx
                $quota = (int) MotorcycleAreaQuota::query()
                    ->where('area_id', (int) $areaId)
                    ->where('year', (int) $year)
                    ->where('active', 1)
                    ->lockForUpdate()
                    ->value('quota_total');

                $used = (int) Motorcycle::query()
                    ->where('current_area_id', (int) $areaId)
                    ->lockForUpdate()
                    ->count();

                if ($quota <= 0 || $used >= $quota) {
                    abort(422, 'Cupo del área agotado. No se puede ubicar/crear otra moto.');
                }

                $motorcycleId = null;

                // 2) existing
                if ($data['motorcycle_mode'] === 'existing') {
                    $motorcycleId = (int) $data['motorcycle_id'];

                    $m = Motorcycle::query()
                        ->where('id', $motorcycleId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($m->status !== 'available') {
                        abort(422, 'La moto no está disponible.');
                    }

                    if (!is_null($m->current_area_id) && (int) $m->current_area_id !== (int) $areaId) {
                        abort(422, 'La moto pertenece a otra área. Debe transferirse primero.');
                    }

                    // REGLA CLAVE: 1 persona por moto (verifica asignación activa real)
                    $hasActiveForMoto = MotorcycleAssignment::query()
                        ->where('motorcycle_id', $motorcycleId)
                        ->whereIn('status', ['approved', 'delivered'])
                        ->lockForUpdate()
                        ->exists();

                    if ($hasActiveForMoto) {
                        $plate = $m->plate ?? ('#' . $motorcycleId);
                        abort(422, "La moto {$plate} ya tiene una asignación activa. No está disponible.");
                    }

                    if (is_null($m->current_area_id)) {
                        $m->current_area_id = (int) $areaId;
                    }

                    $m->status = 'assigned';
                    $m->save();
                }

                // 3) new
                if ($data['motorcycle_mode'] === 'new') {
                    $plate = strtoupper(trim((string) $data['plate']));
                    if ($plate === '') abort(422, 'La placa es obligatoria.');

                    $existsPlate = Motorcycle::query()
                        ->where('plate', $plate)
                        ->lockForUpdate()
                        ->exists();

                    if ($existsPlate) abort(422, 'Ya existe una moto con esa placa.');

                    $m = new Motorcycle();
                    $m->plate = $plate;
                    $m->brand = $data['brand'] ?? null;
                    $m->model = $data['model'] ?? null;
                    $m->current_odometer = (int) $data['current_odometer'];
                    $m->current_area_id = (int) $areaId;
                    $m->status = 'assigned';
                    $m->save();

                    $motorcycleId = (int) $m->id;

                    // defensivo (normalmente innecesario en new)
                    $hasActiveForMoto = MotorcycleAssignment::query()
                        ->where('motorcycle_id', $motorcycleId)
                        ->whereIn('status', ['approved', 'delivered'])
                        ->lockForUpdate()
                        ->exists();

                    if ($hasActiveForMoto) {
                        abort(422, "La moto {$plate} ya tiene una asignación activa. No está disponible.");
                    }
                }

                // 4) Crear asignación
                MotorcycleAssignment::create([
                    'status'           => 'approved',
                    'motorcycle_id'    => $motorcycleId,
                    'person_id'        => $personId,
                    'area_id'          => (int) $areaId,
                    'budget_item_id'   => null,
                    'requested_by'     => Auth::id(),
                    'approved_by'      => Auth::id(),
                    'managed_by'       => Auth::id(),
                    'observations_out' => $data['notes'] ?? null,
                ]);
            });
        } catch (\Throwable $e) {
            $msg = $e->getMessage() ?: 'No fue posible completar la asignación.';
            return back()->withInput()->with('error', $msg);
        }

        $prefix = $this->routePrefix($areaKey);

        return redirect()
            ->route($prefix . '.motorcycles.assign.create', ['area' => $data['area'], 'year' => $data['year']])
            ->with('success', 'Proceso completado: moto creada/seleccionada y asignación registrada.');
    }

    /* =========================
     | quotaStatus (GET/JSON)
     | Nota: este método está “genérico”; ajusta nombres de tablas/columnas a tu esquema real.
     * ========================= */

    public function quotaStatus(Request $request)
    {
        $areaKey = (string) ($request->get('area') ?: $this->areaKeyFromPath($request));
        $this->guardCoordOrSupport($areaKey);

        $year    = (int) ($request->get('year') ?: now()->year);
        $areaIds = $this->areaIdsByKey($areaKey);

        // Ajusta tablas/columnas si tus modelos ya cubren esto.
        // Aquí dejo un diagnóstico básico con los MODELOS reales (MotorcycleAreaQuota/Motorcycle/MotorcycleAssignment).
        $quota = (int) MotorcycleAreaQuota::query()
            ->whereIn('area_id', $areaIds)
            ->where('year', $year)
            ->where('active', 1)
            ->sum('quota_total');

        $inventoryTotal = (int) Motorcycle::query()
            ->whereIn('current_area_id', $areaIds)
            ->count();

        $assignedActive = (int) MotorcycleAssignment::query()
            ->whereIn('area_id', $areaIds)
            ->whereIn('status', ['approved', 'delivered'])
            ->count();

        $available = max(0, $inventoryTotal - $assignedActive);
        $quotaRemaining = max(0, $quota - $assignedActive);

        return response()->json([
            'ok'              => true,
            'area'            => $areaKey,
            'year'            => $year,
            'quota'           => $quota,
            'inventory_total' => $inventoryTotal,
            'assigned_active' => $assignedActive,
            'available'       => $available,
            'quota_remaining' => $quotaRemaining,
        ]);
    }

    /* =========================
     | peopleInArea (AJAX)
     | - lista “inscritos” al área (por roles)
     | Ajusta tabla gdf_user_roles y role_key a tu realidad
     * ========================= */

    public function peopleInArea(Request $request)
    {
        $areaKey = (string) ($request->get('area') ?: $this->areaKeyFromPath($request));
        $this->guardCoordOrSupport($areaKey);

        $areaId = $this->resolveAreaId($areaKey);

        $today  = Carbon::today();
        $minEnd = $today->copy()->subDays(8);

        $instructorRoles = [
            'gdf.instructor',
            'gdf.campesena_instructor',
            'gdf.academic_instructor',
        ];

        // Ajusta nombre de tabla si difiere
        $rows = DB::table('gdf_user_roles as ur')
            ->join('users as u', 'u.id', '=', 'ur.user_id')
            ->join('people as p', 'p.id', '=', 'u.person_id')
            ->leftJoin('employees as e', 'e.person_id', '=', 'p.id')
            ->leftJoin('contractors as c', 'c.person_id', '=', 'p.id')
            ->where('ur.area_id', $areaId)
            ->whereIn('ur.role_key', $instructorRoles)
            ->select([
                'p.id as person_id',
                'p.document_number',
                'p.first_name',
                'p.first_last_name',
                'p.second_last_name',
                DB::raw("CASE WHEN c.id IS NOT NULL THEN 'contractor' ELSE 'employee' END as source"),
                DB::raw("CASE WHEN u.id IS NOT NULL THEN 1 ELSE 0 END as has_user"),
                // Regla Motos: contratista OK si end >= hoy-8; NULL => OK
                DB::raw("CASE
                    WHEN c.id IS NULL THEN 1
                    WHEN c.contract_end_date IS NULL THEN 1
                    WHEN DATE(c.contract_end_date) >= DATE('" . $minEnd->toDateString() . "') THEN 1
                    ELSE 0 END as is_active_contract
                "),
                DB::raw("DATE(c.contract_end_date) as contract_end_date"),
            ])
            ->distinct()
            ->orderBy('p.first_name')
            ->limit(200)
            ->get();

        $items = $rows->map(function ($r) {
            return [
                'source'            => $r->source,
                'person_id'         => (int) $r->person_id,
                'document_number'   => $r->document_number,
                'full_name'         => trim($r->first_name . ' ' . $r->first_last_name . ' ' . $r->second_last_name),
                'has_user'          => (bool) $r->has_user,
                'is_active_contract' => (bool) $r->is_active_contract,
                'contract_end_date' => $r->contract_end_date,
            ];
        });

        return response()->json(['ok' => true, 'items' => $items]);
    }
}
