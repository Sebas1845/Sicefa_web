<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

use Modules\GDF\Entities\Area;
use Modules\GDF\Services\AuthorizationPdfService;
use Illuminate\Support\Facades\Storage;
use Modules\SICA\Entities\Employee;
use Modules\SICA\Entities\Contractor;
use Modules\SICA\Entities\Role;
use App\Models\User;
use Modules\SICA\Entities\Person;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;




class SubdirectionController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    private array $pendingForSubdirection = [
        'approved', // ✅ viene desde Coordinación

    ];

    private string $finalApprovedStatus = 'confirmed';

    private function guardSubdirection(): void
    {
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);
    }


    public function index(Request $request)
    {
        $this->guardSubdirection();

        $year  = (int) $request->get('year', now()->year);
        $today = now()->toDateString();

        $areas = Area::query()->orderBy('name')->get();

        // ===== KPIs Presupuesto =====
        $available = $this->tableExists('budgets')
            ? (float) DB::table('budgets')->where('year', $year)->where('active', 1)->sum('current_amount')
            : 0.0;

        $executed = 0.0;
        if ($this->tableExists('budget_movements') && $this->tableExists('budgets')) {
            $executed = (float) DB::table('budget_movements as bm')
                ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->where('bm.type', 'execute')
                ->sum('bm.amount');
        }

        $total = $available + $executed;

        // ===== KPIs Motos =====
        $motos_total = $this->safeCount('motorcycles');
        $motos_available = $this->safeCountWhere('motorcycles', ['status' => 'available']);
        $motos_assigned  = $this->safeCountWhere('motorcycles', ['status' => 'assigned']);
        $moto_assignments_open = $this->safeCountWhereIn('motorcycle_assignments', 'status', ['approved', 'delivered']);

        // ===== Pendientes + Últimas Confirmadas =====
        $pendingRequests = collect();
        $pendingGdfRequests = collect();
        $pendingSitravRequests = collect();

        $confirmedGdfRequests = collect();
        $confirmedSitravRequests = collect();

        if ($this->tableExists('travel_requests')) {

            $peopleTable = $this->tableExists('people') ? 'people' : null;

            $nameExpr = $peopleTable
                ? "TRIM(CONCAT_WS(' ',
                NULLIF(p.first_name,''),
                NULLIF(p.first_last_name,''),
                NULLIF(p.second_last_name,'')
              ))"
                : "'—'";

            $yearExpr = $this->yearExpr('tr');

            // --- Pendientes para subdirección ---
            $pendingBase = DB::table('travel_requests as tr')
                ->whereRaw("$yearExpr = ?", [$year])
                ->whereIn('tr.status', $this->pendingForSubdirection);

            $pendingGdfRequests = (clone $pendingBase)
                ->where('tr.module', 'gdf')
                ->when($peopleTable, fn($q) => $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id'))
                ->selectRaw("
                tr.id,
                tr.total_amount as amount,
                COALESCE(NULLIF($nameExpr,''), '—') as person_name,
                tr.status,
                tr.created_at
            ")
                ->orderByDesc('tr.created_at')
                ->limit(5)
                ->get();

            $pendingSitravRequests = (clone $pendingBase)
                ->where('tr.module', 'sitrav')
                ->when($peopleTable, fn($q) => $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id'))
                ->selectRaw("
                tr.id,
                tr.total_amount as amount,
                COALESCE(NULLIF($nameExpr,''), '—') as person_name,
                tr.status,
                tr.created_at
            ")
                ->orderByDesc('tr.created_at')
                ->limit(5)
                ->get();

            $pendingRequests = $pendingGdfRequests->concat($pendingSitravRequests);

            // --- Últimas confirmadas ---
            $confirmedBase = DB::table('travel_requests as tr')
                ->whereRaw("$yearExpr = ?", [$year])
                ->where('tr.status', $this->finalApprovedStatus);

            $confirmedGdfRequests = (clone $confirmedBase)
                ->where('tr.module', 'gdf')
                ->when($peopleTable, fn($q) => $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id'))
                ->selectRaw("
                tr.id,
                tr.total_amount as amount,
                COALESCE(NULLIF($nameExpr,''), '—') as person_name,
                tr.created_at
            ")
                ->orderByDesc('tr.created_at')
                ->limit(5)
                ->get();

            $confirmedSitravRequests = (clone $confirmedBase)
                ->where('tr.module', 'sitrav')
                ->when($peopleTable, fn($q) => $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id'))
                ->selectRaw("
                tr.id,
                tr.total_amount as amount,
                COALESCE(NULLIF($nameExpr,''), '—') as person_name,
                tr.created_at
            ")
                ->orderByDesc('tr.created_at')
                ->limit(5)
                ->get();
        }

        $kpis = [
            'total_budget'     => (int) round($total),
            'executed_budget'  => (int) round($executed),
            'available_budget' => (int) round($available),
            'pending_requests' => (int) $pendingRequests->count(),

            'motos_total' => (int) $motos_total,
            'motos_available' => (int) $motos_available,
            'motos_assigned' => (int) $motos_assigned,
            'moto_assignments_open' => (int) $moto_assignments_open,
        ];

        // ===== Rubros cards =====
        $rubroCards = collect();
        $globalAvail = (int) round($available);
        $globalSpent = (int) round($executed);
        $globalTotal = (int) round($total);

        if ($this->tableExists('budgets') && $this->tableExists('budget_items')) {

            $rubrosBase = DB::table('budgets as b')
                ->join('budget_items as bi', 'bi.id', '=', 'b.budget_item_id')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->selectRaw('b.budget_item_id, bi.code, bi.name')
                ->distinct()
                ->get();

            $availByItem = DB::table('budgets as b')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->selectRaw('b.budget_item_id, COALESCE(SUM(b.current_amount),0) as avail')
                ->groupBy('b.budget_item_id')
                ->pluck('avail', 'budget_item_id');

            $spentByItem = collect();
            if ($this->tableExists('budget_movements')) {
                $spentByItem = DB::table('budget_movements as bm')
                    ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                    ->where('b.year', $year)
                    ->where('b.active', 1)
                    ->where('bm.type', 'execute')
                    ->selectRaw('b.budget_item_id, COALESCE(SUM(bm.amount),0) as spent')
                    ->groupBy('b.budget_item_id')
                    ->pluck('spent', 'budget_item_id');
            }

            $areasUsingByItem = collect();
            if ($this->tableExists('budget_area_allocations')) {
                $areasUsingByItem = DB::table('budget_area_allocations as baa')
                    ->join('budgets as b', 'b.id', '=', 'baa.budget_id')
                    ->where('b.year', $year)
                    ->where('b.active', 1)
                    ->where('baa.active', 1)
                    ->selectRaw('b.budget_item_id, COUNT(DISTINCT baa.area_id) as areas_count')
                    ->groupBy('b.budget_item_id')
                    ->pluck('areas_count', 'budget_item_id');
            }

            $peopleByItem = collect();
            $primaryPeopleByItem = collect();
            if ($this->tableExists('person_area_budget_assignments')) {
                $peopleByItem = DB::table('person_area_budget_assignments as paba')
                    ->where('paba.is_active', 1)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
                    })
                    ->selectRaw('paba.budget_item_id, COUNT(DISTINCT paba.person_id) as people_count')
                    ->groupBy('paba.budget_item_id')
                    ->pluck('people_count', 'budget_item_id');

                $primaryPeopleByItem = DB::table('person_area_budget_assignments as paba')
                    ->where('paba.is_active', 1)
                    ->where('paba.is_primary', 1)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
                    })
                    ->selectRaw('paba.budget_item_id, COUNT(DISTINCT paba.person_id) as primary_count')
                    ->groupBy('paba.budget_item_id')
                    ->pluck('primary_count', 'budget_item_id');
            }

            $rubroCards = $rubrosBase->map(function ($r) use ($availByItem, $spentByItem, $areasUsingByItem, $peopleByItem, $primaryPeopleByItem) {
                $avail = (float) ($availByItem[$r->budget_item_id] ?? 0);
                $spent = (float) ($spentByItem[$r->budget_item_id] ?? 0);
                $total = $avail + $spent;

                return [
                    'id' => (int) $r->budget_item_id,
                    'code' => $r->code,
                    'name' => $r->name,
                    'avail' => (int) round($avail),
                    'spent' => (int) round($spent),
                    'total' => (int) round($total),
                    'areas_count' => (int) ($areasUsingByItem[$r->budget_item_id] ?? 0),
                    'people_count' => (int) ($peopleByItem[$r->budget_item_id] ?? 0),
                    'primary_count' => (int) ($primaryPeopleByItem[$r->budget_item_id] ?? 0),
                ];
            })->sortByDesc('avail')->values();
        }

        // Mantengo nombres esperados por tu vista (si ya los estabas usando así)
        $approvedGdfRequests    = $confirmedGdfRequests;
        $approvedSitravRequests = $confirmedSitravRequests;

        return view('gdf::subdirection.dashboard', compact(
            'year',
            'areas',
            'kpis',
            'pendingRequests',
            'pendingGdfRequests',
            'pendingSitravRequests',
            'approvedGdfRequests',
            'approvedSitravRequests',
            'rubroCards',
            'globalAvail',
            'globalSpent',
            'globalTotal'
        ));
    }
    public function indexuser(Request $request)
    {
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);

        $q = trim((string)$request->get('q', ''));
        $roleSlug = trim((string)$request->get('role_slug', ''));
        $onlyUsers = $request->boolean('only_users');
        $src = trim((string)$request->get('src', '')); // contractor | employee
        $importType = (string)$request->get('import_type', 'none'); // both|contractors|employees|none
        $importQ = trim((string)$request->get('import_q', ''));
        $includeApprentices = $request->boolean('include_apprentices');

        $gdfRoles = Role::query()
            ->where('slug', 'like', 'gdf.%')
            ->whereNotIn('slug', ['gdf.admin', 'gdf.subdirection'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $usersQ = User::query()
            ->with(['person', 'roles'])
            ->when($onlyUsers, fn($qq) => $qq->whereNotNull('email'))
            ->when($roleSlug !== '', function ($qq) use ($roleSlug) {
                $qq->whereHas('roles', fn($r) => $r->where('slug', $roleSlug));
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($sub) use ($q) {
                    $sub->where('nickname', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhereHas('person', function ($p) use ($q) {
                            $p->where('document_number', 'like', "%{$q}%")
                                ->orWhere('first_name', 'like', "%{$q}%")
                                ->orWhere('first_last_name', 'like', "%{$q}%")
                                ->orWhere('second_last_name', 'like', "%{$q}%");
                        });
                });
            });

        if (!$includeApprentices) {
        }

        $users = $usersQ->orderByDesc('id')->paginate(18)->appends($request->query());


        $contractors = [];
        $employees = [];

        if (in_array($importType, ['both', 'contractors', 'employees'], true)) {

            if (in_array($importType, ['both', 'contractors'], true)) {
                $contractors = Contractor::query()
                    ->with('person')
                    ->when($importQ !== '', function ($qq) use ($importQ) {
                        $qq->whereHas('person', function ($p) use ($importQ) {
                            $p->where('document_number', 'like', "%{$importQ}%")
                                ->orWhere('first_name', 'like', "%{$importQ}%")
                                ->orWhere('first_last_name', 'like', "%{$importQ}%");
                        });
                    })
                    ->whereHas('person', function ($p) {
                        // sin usuario: person_id no existe en users
                        $p->whereNotIn('id', DB::table('users')->whereNotNull('person_id')->pluck('person_id'));
                    })
                    ->limit(200)
                    ->get();
            }

            if (in_array($importType, ['both', 'employees'], true)) {
                $employees = Employee::query()
                    ->with('person')
                    ->when($importQ !== '', function ($qq) use ($importQ) {
                        $qq->whereHas('person', function ($p) use ($importQ) {
                            $p->where('document_number', 'like', "%{$importQ}%")
                                ->orWhere('first_name', 'like', "%{$importQ}%")
                                ->orWhere('first_last_name', 'like', "%{$importQ}%");
                        });
                    })
                    ->whereHas('person', function ($p) {
                        $p->whereNotIn('id', DB::table('users')->whereNotNull('person_id')->pluck('person_id'));
                    })
                    ->limit(200)
                    ->get();
            }
        }

        return view('gdf::subdirection.users.index', compact(
            'users',
            'gdfRoles',
            'q',
            'roleSlug',
            'onlyUsers',
            'src',
            'importType',
            'importQ',
            'includeApprentices',
            'contractors',
            'employees'
        ));
    }
    public function sendResetLink(Request $request, int $id)
    {
        // ✅ Solo subdirección
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);

        $user = User::with('person')->findOrFail($id);

        // Debe tener correo
        $to = $user->email
            ?? optional($user->person)->personal_email
            ?? optional($user->person)->misena_email
            ?? null;

        if (!$to) {
            return back()->with('error', 'Este usuario no tiene correo. No se puede enviar el enlace.');
        }

        // ✅ Generar token 1-uso (tu misma lógica)
        $plainToken = $this->createLoginTokenForUser($user);

        $fullName = trim(
            (optional($user->person)->first_name ?? '') . ' ' .
                (optional($user->person)->first_last_name ?? '') . ' ' .
                (optional($user->person)->second_last_name ?? '')
        );

        $url = route('gdf.security.magic', ['token' => $plainToken]);

        $subject = 'Enlace para crear / cambiar contraseña (GDF)';
        $body = implode("\n", [
            "Hola {$fullName},",
            "",
            "Se solicitó un enlace para definir o cambiar tu contraseña de acceso al módulo GDF.",
            "",
            "Abre este enlace (válido una sola vez):",
            $url,
            "",
            "Si no fuiste tú, ignora este correo.",
        ]);

        try {
            Mail::raw($body, function ($msg) use ($to, $subject) {
                $msg->to($to)->subject($subject);
            });

            // ✅ Forzar que pida contraseña al entrar (si tu campo existe)
            if (Schema::hasColumn('users', 'force_password_change')) {
                $user->force_password_change = 1;
                $user->save();
            }

            return back()->with('success', "Enlace enviado a {$to}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo enviar el correo. ' . $e->getMessage());
        }
    }

    public function assignRole(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'user_id'   => ['required', 'integer'],
            'role_slug' => ['required', 'string', 'max:120'],
        ]);

        // Bloqueo de roles prohibidos
        if (in_array($data['role_slug'], ['gdf.admin', 'gdf.subdirection'], true)) {
            return back()->with('error', 'No se permite asignar gdf.admin ni gdf.subdirection.');
        }

        $user = User::with('roles')->findOrFail((int)$data['user_id']);

        $role = Role::where('slug', $data['role_slug'])->first();
        if (!$role) return back()->with('error', 'Rol no existe.');

        $user->roles()->syncWithoutDetaching([$role->id]);

        // Forzar password change si aplica
        if (Schema::hasColumn('users', 'force_password_change')) {
            $user->force_password_change = 1;
            $user->save();
        }

        return back()->with('success', 'Rol asignado.');
    }


    public function revokeRole(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'user_id'   => ['required', 'integer'],
            'role_slug' => ['required', 'string', 'max:120'],
        ]);

        // No quitar subdirection/admin desde aquí (por seguridad)
        if (in_array($data['role_slug'], ['gdf.admin', 'gdf.subdirection'], true)) {
            return back()->with('error', 'No se permite quitar gdf.admin ni gdf.subdirection desde esta pantalla.');
        }

        $user = User::with('roles')->findOrFail((int)$data['user_id']);
        $role = Role::where('slug', $data['role_slug'])->first();
        if (!$role) return back()->with('error', 'Rol no existe.');

        $user->roles()->detach([$role->id]);

        return back()->with('success', 'Rol removido.');
    }




    public function createUserByDocument(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:30'],
            'source'          => ['nullable', 'in:auto,contractor,employee'],
            'role_slug'       => ['required', 'string', 'max:120'],
            'areas'           => ['nullable', 'array'],
            'areas.*'         => ['in:academic,campesena'],
            'scope'           => ['nullable', 'string', 'max:50'],
        ]);

        if (in_array($data['role_slug'], ['gdf.admin', 'gdf.subdirection'], true)) {
            return back()->with('error', 'No se permite asignar gdf.admin ni gdf.subdirection.');
        }

        $doc = trim($data['document_number']);
        $source = $data['source'] ?? 'auto';

        // 1) Encontrar Person desde contractor/employee
        $person = Person::where('document_number', $doc)->first();

        if (!$person) {
            // intentar “resolver” por source
            if ($source !== 'employee') {
                $c = Contractor::whereHas('person', fn($p) => $p->where('document_number', $doc))->with('person')->first();
                if ($c && $c->person) $person = $c->person;
            }
            if (!$person && $source !== 'contractor') {
                $e = Employee::whereHas('person', fn($p) => $p->where('document_number', $doc))->with('person')->first();
                if ($e && $e->person) $person = $e->person;
            }
        }

        if (!$person) return back()->with('error', 'No se encontró persona (contractor/employee) con esa cédula.');

        // 2) Crear o actualizar User
        $email = $person->misena_email ?: ($person->personal_email ?: null);
        if (!$email) return back()->with('error', 'La persona no tiene correo (misena/personal).');

        $email = strtolower(trim($email));

        $user = User::where('person_id', $person->id)->first();
        if (!$user) {
            $user = User::where('email', $email)->first();
        }

        if (!$user) {
            $nick = Str::upper(Str::substr(preg_replace('/\s+/', '', (string)$person->first_name), 0, 3)) . $person->document_number;
            $user = User::create([
                'person_id' => $person->id,
                'nickname'  => $nick,
                'email'     => $email,
                'password'  => Hash::make(Str::random(40)),
            ]);
        } else {
            if (empty($user->person_id)) {
                $user->person_id = $person->id;
            }
            if (empty($user->email)) {
                $user->email = $email;
            }
            $user->save();
        }

        $role = Role::where('slug', $data['role_slug'])->first();
        if (!$role) return back()->with('error', 'Rol no existe.');

        $user->roles()->syncWithoutDetaching([$role->id]);

        $token = $this->createLoginTokenForUser($user);
        $ok = $this->sendResetMail($person, $user, $token);

        if (Schema::hasColumn('users', 'force_password_change')) {
            $user->force_password_change = 1;
            $user->save();
        }

        return back()->with(
            $ok ? 'success' : 'warning',
            $ok
                ? "Usuario listo. Se envió enlace a {$user->email}."
                : "Usuario listo, pero el correo falló."
        );
    }


    public function importUsersCreate(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'selected'   => ['required', 'array', 'min:1'],
            'selected.*' => ['string'],
        ]);

        $created = 0;
        $failed = 0;

        foreach ($data['selected'] as $sel) {
            try {
                // "contractor:PERSON_ID" o "employee:PERSON_ID"
                [$type, $personId] = array_pad(explode(':', $sel, 2), 2, null);
                $personId = (int)$personId;

                if (!in_array($type, ['contractor', 'employee'], true) || $personId <= 0) {
                    $failed++;
                    continue;
                }

                $person = Person::find($personId);
                if (!$person) {
                    $failed++;
                    continue;
                }

                $email = $person->misena_email ?: ($person->personal_email ?: null);
                if (!$email) {
                    $failed++;
                    continue;
                }

                $email = strtolower(trim($email));

                $user = User::where('person_id', $person->id)->first()
                    ?? User::where('email', $email)->first();

                if (!$user) {
                    $nick = Str::upper(Str::substr(preg_replace('/\s+/', '', (string)$person->first_name), 0, 3)) . $person->document_number;
                    $user = User::create([
                        'person_id' => $person->id,
                        'nickname'  => $nick,
                        'email'     => $email,
                        'password'  => Hash::make(Str::random(40)),
                    ]);
                    $created++;
                }

                // Fuerza cambio pass
                if (Schema::hasColumn('users', 'force_password_change')) {
                    $user->force_password_change = 1;
                    $user->save();
                }
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return back()->with('success', "Importación finalizada. Creados: {$created}. Fallidos: {$failed}.");
    }


    private function createLoginTokenForUser(User $user): string
    {
        $plain = Str::random(64);

        $payload = [
            'user_id'    => $user->id,
            'used_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('login_tokens', 'expires_at')) {
            $payload['expires_at'] = now()->addHours(48);
        }

        if (Schema::hasColumn('login_tokens', 'token_hash')) {
            $payload['token_hash'] = hash('sha256', $plain);
        } else {
            $payload['token'] = $plain;
        }

        DB::table('login_tokens')->insert($payload);

        return $plain;
    }

    private function sendResetMail(Person $person, User $user, string $plainToken): bool
    {
        $to = $user->email ?? $person->personal_email ?? $person->misena_email ?? null;
        if (!$to) return false;

        $url = route('gdf.security.magic', ['token' => $plainToken]);

        $fullName = trim(
            ($person->first_name ?? '') . ' ' .
                ($person->first_last_name ?? '') . ' ' .
                ($person->second_last_name ?? '')
        );

        $subject = 'Enlace para crear / cambiar contraseña (GDF)';
        $body = implode("\n", [
            "Hola {$fullName},",
            "",
            "Aquí está tu enlace para crear o cambiar contraseña:",
            $url,
            "",
            "Este enlace expira y solo se puede usar una vez.",
        ]);

        try {
            Mail::raw($body, function ($msg) use ($to, $subject) {
                $msg->to($to)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }


    public function reports(Request $request)
    {
        $this->guardSubdirection();
        $year = (int) $request->get('year', now()->year);
        return view('gdf::subdirection.reports', compact('year'));
    }

    public function reportsSummary(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);

        $q = DB::table('travel_requests as tr');
        $this->applyTravelRequestFilters($q, $request, $year);

        $total_requests = (int) (clone $q)->count();
        $total_amount = (float) (clone $q)->sum('tr.total_amount');
        $total_transport = (float) (clone $q)->sum('tr.total_transport');
        $total_per_diem = (float) (clone $q)->sum('tr.total_per_diem');
        $total_other = (float) (clone $q)->sum('tr.total_other');

        $pending_subdirection = (int) (clone $q)
            ->whereIn('tr.status', $this->pendingForSubdirection)
            ->count();

        return response()->json([
            'total_requests' => $total_requests,
            'total_amount' => $total_amount,
            'total_transport' => $total_transport,
            'total_per_diem' => $total_per_diem,
            'total_other' => $total_other,
            'pending_subdirection' => $pending_subdirection,
        ]);
    }

    public function reportsTrend(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);

        $q = DB::table('travel_requests as tr');
        $this->applyTravelRequestFilters($q, $request, $year);

        $rows = $q->selectRaw("
                DATE_FORMAT(COALESCE(tr.start_date, tr.created_at), '%Y-%m') as ym,
                COUNT(*) as total_requests,
                COALESCE(SUM(tr.total_amount),0) as total_amount
            ")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();

        return response()->json($rows);
    }

    public function reportsTopDestinations(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_segments');
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);
        $limit = (int) $request->get('limit', 10);
        $limit = max(5, min($limit, 50));

        $q = DB::table('travel_segments as ts')
            ->join('travel_requests as tr', 'tr.id', '=', 'ts.travel_request_id');

        $this->applyTravelRequestFilters($q, $request, $year);

        $labelExpr = "COALESCE(NULLIF(ts.destination_display_name,''), NULLIF(ts.destination_place,''), '—')";

        $rows = $q->selectRaw("
                $labelExpr as label,
                ts.destination_type,
                COUNT(DISTINCT tr.id) as requests_count,
                COALESCE(SUM(ts.total_cost),0) as total_cost
            ")
            ->groupBy('label', 'ts.destination_type')
            ->orderByDesc('total_cost')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }

    public function reportsMapPointsGdf(Request $request)
    {
        $this->guardSubdirection();
        return $this->reportsMapPointsByModule($request, 'gdf');
    }

    public function reportsMapPointsSitrav(Request $request)
    {
        $this->guardSubdirection();
        return $this->reportsMapPointsByModule($request, 'sitrav');
    }

    private function reportsMapPointsByModule(Request $request, string $module)
    {
        $this->ensureTableOrFail('travel_segments');
        $this->ensureTableOrFail('travel_requests');

        $year = (int) $request->get('year', now()->year);
        $limit = (int) $request->get('limit', 200);
        $limit = max(50, min($limit, 2000));

        $q = DB::table('travel_segments as ts')
            ->join('travel_requests as tr', 'tr.id', '=', 'ts.travel_request_id')
            ->where('tr.module', $module);

        $this->applyTravelRequestFilters($q, $request, $year);

        $q->whereNotNull('ts.destination_lat')
            ->whereNotNull('ts.destination_lng');

        $labelExpr = "COALESCE(NULLIF(ts.destination_display_name,''), NULLIF(ts.destination_place,''), '—')";

        $rows = $q->selectRaw("
                $labelExpr as label,
                ts.destination_type,
                COUNT(DISTINCT tr.id) as requests_count,
                COALESCE(SUM(ts.total_cost),0) as total_cost,
                AVG(ts.destination_lat) as lat,
                AVG(ts.destination_lng) as lng
            ")
            ->groupBy('label', 'ts.destination_type')
            ->orderByDesc('requests_count')
            ->limit($limit)
            ->get();

        return response()->json($rows);
    }
    private function recalcTotalsForRequest(int $travelRequestId): array
    {
        // 1) Costos: suma amount (si quieres solo transport, filtra cost_type)
        $costsTotal = 0.0;
        if ($this->tableExists('travel_costs')) {
            $costsTotal = (float) DB::table('travel_costs')
                ->where('travel_request_id', $travelRequestId)
                ->sum('amount');
        }

        // 2) Viáticos: usa approved_amount si existe, si no calculated_amount
        $allowTotal = 0.0;
        if ($this->tableExists('travel_allowances')) {
            $allowTotal = (float) DB::table('travel_allowances')
                ->where('travel_request_id', $travelRequestId)
                ->whereIn('status', ['draft', 'liquidated', 'approved']) // NO rejected
                ->sum(DB::raw("COALESCE(approved_amount, calculated_amount)"));
        }

        // 3) Otros (si tu modelo lo usa; si no, déjalo en 0)
        $otherTotal = 0.0;

        $grandTotal = (float) ($costsTotal + $allowTotal + $otherTotal);

        return [
            'total_transport' => $costsTotal,
            'total_per_diem'  => $allowTotal,
            'total_other'     => $otherTotal,
            'total_amount'    => $grandTotal,
        ];
    }


    public function requestsIndex(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $year   = (int) $request->get('year', now()->year);
        $q      = trim((string) $request->get('q', ''));
        $module = $request->get('module');              // gdf|sitrav|null

        // ✅ default: ver confirmadas (final)
        $status = $request->get('status', $this->finalApprovedStatus); // confirmed

        $query = DB::table('travel_requests as tr')
            ->whereRaw($this->yearExpr('tr') . ' = ?', [$year]);

        if ($module) $query->where('tr.module', $module);

        if ($status && $status !== 'all') {
            if ($status === 'pending_subdirection') {
                $query->whereIn('tr.status', $this->pendingForSubdirection); // approved
            } else {
                $query->where('tr.status', $status);
            }
        }

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('tr.id', $q)
                    ->orWhere('tr.origin', 'like', "%{$q}%")
                    ->orWhere('tr.destination', 'like', "%{$q}%")
                    ->orWhere('tr.radicado_code', 'like', "%{$q}%");
            });
        }

        $peopleTable = $this->tableExists('people') ? 'people' : null;
        $nameExpr = $peopleTable
            ? "TRIM(CONCAT_WS(' ',
                NULLIF(p.first_name,''),
                NULLIF(p.first_last_name,''),
                NULLIF(p.second_last_name,'')
              ))"
            : "'—'";

        if ($peopleTable) {
            $query->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id');
        }

        $rows = $query->selectRaw("
                tr.id, tr.module, tr.status,
                tr.origin, tr.destination,
                tr.start_date, tr.end_date,
                tr.total_amount,
                COALESCE(NULLIF($nameExpr,''), '—') as person_name,
                tr.budget_item_id, tr.area_id,
                tr.created_at
            ")
            ->orderByDesc('tr.created_at')
            ->paginate(15)
            ->appends($request->query());

        return view('gdf::subdirection.requests.index', compact('rows', 'year', 'q', 'module', 'status'));
    }

    public function requestsShow($id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $peopleTable = $this->tableExists('people') ? 'people' : null;

        $q = DB::table('travel_requests as tr')->where('tr.id', (int)$id);

        if ($peopleTable) {
            $q->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id');
        }

        $hasAreas = $this->tableExists('areas');
        if ($hasAreas) {
            $q->leftJoin('areas as a', 'a.id', '=', 'tr.area_id');
        }

        $nameExpr = $peopleTable
            ? "TRIM(CONCAT_WS(' ',
            NULLIF(p.first_name,''),
            NULLIF(p.first_last_name,''),
            NULLIF(p.second_last_name,'')
        ))"
            : "'—'";

        $areaKeyExpr = $hasAreas
            ? "CASE WHEN LOWER(a.name) LIKE '%campesena%' THEN 'campesena' ELSE 'academic' END"
            : "'—'";

        $tr = $q->selectRaw("
        tr.*,
        COALESCE(NULLIF($nameExpr,''), '—') as person_name,
        " . ($hasAreas ? "COALESCE(a.name,'—') as area_name," : "'—' as area_name,") . "
        $areaKeyExpr as area_key
    ")->first();

        if (!$tr) abort(404);


        $budget = null;
        if ($this->tableExists('budgets') && !empty($tr->budget_id)) {
            $budget = DB::table('budgets')->where('id', (int)$tr->budget_id)->first();
        }

        $budgetItem = null;
        if ($this->tableExists('budget_items') && !empty($tr->budget_item_id)) {
            $budgetItem = DB::table('budget_items')->where('id', (int)$tr->budget_item_id)->first();
        }

        $area = null;
        if ($hasAreas && !empty($tr->area_id)) {
            $area = DB::table('areas')->where('id', (int)$tr->area_id)->first();
        }

        $segments = collect();

        if ($this->tableExists('travel_segments')) {

            $seg = DB::table('travel_segments as ts')
                ->where('ts.travel_request_id', (int)$tr->id)
                ->orderBy('ts.id');

            // joins opcionales a rates
            if ($this->tableExists('municipality_rates')) {
                $seg->leftJoin('municipality_rates as mr', 'mr.id', '=', 'ts.municipality_rate_id');
            }
            if ($this->tableExists('village_rates')) {
                $seg->leftJoin('village_rates as vr', 'vr.id', '=', 'ts.village_rate_id');
            }

            $destExpr = "
            COALESCE(
                NULLIF(ts.destination_display_name,''),
                NULLIF(mr.municipality_name,''),
                NULLIF(vr.village_name,''),
                NULLIF(ts.destination_place,''),
                '—'
            )
        ";

            $segments = $seg->selectRaw("
            ts.*,
            $destExpr as destination_name
        ")->get();
        }

        $costs = $this->tableExists('travel_costs')
            ? DB::table('travel_costs')->where('travel_request_id', (int)$tr->id)->orderBy('id')->get()
            : collect();

        $allowances = $this->tableExists('travel_allowances')
            ? DB::table('travel_allowances')->where('travel_request_id', (int)$tr->id)->orderBy('id')->get()
            : collect();

        $logs = $this->tableExists('travel_reviews')
            ? DB::table('travel_reviews')->where('travel_request_id', (int)$tr->id)->orderByDesc('id')->get()
            : collect();


        $documents = collect();
        $authorizationDoc = null;

        if ($this->tableExists('travel_request_documents')) {
            $documents = DB::table('travel_request_documents')
                ->where('travel_request_id', (int)$tr->id)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->get();

            $authorizationDoc = $documents->first(function ($d) {
                $dtype = strtolower((string)($d->document_type ?? ''));
                $title = strtolower((string)($d->title ?? ''));
                $orig  = strtolower((string)($d->original_name ?? ''));
                $stored = strtolower((string)($d->stored_name ?? ''));

                return $dtype === 'authorization'
                    || $title === 'autorización'
                    || str_contains($title, 'autoriz')
                    || str_contains($orig, 'autoriz')
                    || str_contains($stored, 'autoriz');
            });
        }


        $sigacDocuments = collect();
        if (
            ((string)($tr->module ?? '')) === 'sitrav'
            && !empty($tr->source_request_id)
            && $this->tableExists('program_request_documents')
        ) {
            $sigacDocuments = DB::table('program_request_documents')
                ->where('program_request_id', (int)$tr->source_request_id)
                ->orderByDesc('id')
                ->get();
        }

        return view('gdf::subdirection.requests.show', [
            'tr' => $tr,
            'budget' => $budget,
            'budgetItem' => $budgetItem,
            'area' => $area,

            'segments' => $segments,
            'costs' => $costs,
            'allowances' => $allowances,
            'logs' => $logs,

            'documents' => $documents,
            'authorizationDoc' => $authorizationDoc,
            'sigacDocuments' => $sigacDocuments,
        ]);
    }

    public function downloadDocument(int $id, int $docId)
    {
        $this->guardSubdirection();

        if (!$this->tableExists('travel_request_documents')) abort(404);

        $doc = DB::table('travel_request_documents')
            ->where('id', $docId)
            ->where('travel_request_id', $id)
            ->whereNull('deleted_at')
            ->first();

        if (!$doc) abort(404);

        $path = trim((string)($doc->path ?? ''));
        if ($path === '') abort(404);

        // ✅ Usa el disk guardado en BD (por tu tabla, default "public")
        $disk = trim((string)($doc->disk ?? 'public'));
        if ($disk === '') $disk = 'public';

        // ✅ Verifica existencia
        if (!Storage::disk($disk)->exists($path)) abort(404);

        // ✅ Nombre de descarga: original_name > title.pdf > basename(path)
        $filename = (string)($doc->original_name ?? '');
        $filename = trim($filename);

        if ($filename === '') {
            $title = trim((string)($doc->title ?? ''));
            $filename = $title !== '' ? ($title . '.pdf') : basename($path);
        }

        $filename = $this->safeDownloadName($filename, $path);

        return Storage::disk($disk)->download($path, $filename);
    }


    public function requestsApprove(Request $request, int $id)
    {
        $this->guardSubdirection();

        $this->ensureTableOrFail('travel_requests');
        $this->ensureTableOrFail('budgets');
        $this->ensureTableOrFail('budget_movements');

        try {

            DB::transaction(function () use ($id) {

                // 1) Traer solicitud con lock
                $tr = DB::table('travel_requests')
                    ->where('id', $id)
                    ->lockForUpdate()
                    ->first();

                if (!$tr) abort(404, 'Solicitud no existe.');

                // 2) Validar estado pendiente subdirección
                $status = (string)($tr->status ?? '');
                if (!in_array($status, $this->pendingForSubdirection, true)) {
                    abort(422, "La solicitud no está lista para Subdirección. Estado actual: {$status}");
                }

                // 3) Recalcular total (COSTS + ALLOWANCES)
                $tot   = $this->recalculateTotalsForApproval($tr);
                $total = (float)($tot['total_amount'] ?? 0);

                if ($total <= 0) {
                    abort(422, 'El total de la solicitud es 0. Revisa costos/viáticos antes de aprobar.');
                }

                // 4) Año
                $year = $this->resolveYearFromTravel($tr);

                // 5) Presupuesto (rubro+área+año) con lock
                $areaId = (int)($tr->area_id ?? 0);
                $itemId = (int)($tr->budget_item_id ?? 0);

                if ($areaId <= 0 || $itemId <= 0) {
                    abort(422, 'La solicitud no tiene área o rubro (budget_item_id) definido.');
                }

                $budget = DB::table('budgets')
                    ->where('year', $year)
                    ->where('active', 1)
                    ->where('area_id', $areaId)
                    ->where('budget_item_id', $itemId)
                    ->lockForUpdate()
                    ->first();

                if (!$budget) {
                    abort(422, "No existe presupuesto activo para Año={$year}, Área={$areaId}, Rubro={$itemId}.");
                }

                $current = (float)($budget->current_amount ?? 0);
                if ($current < $total) {
                    abort(422, "Presupuesto insuficiente. Disponible={$current}, requerido={$total}.");
                }

                // 6) Descontar presupuesto
                $newCurrent = $current - $total;

                $budgetUpdate = ['current_amount' => $newCurrent];
                if ($this->columnExists('budgets', 'updated_at')) $budgetUpdate['updated_at'] = now();

                DB::table('budgets')->where('id', (int)$budget->id)->update($budgetUpdate);

                // 7) Insert movimiento (sin updated_at porque NO existe)
                $mv = [
                    'budget_id'         => (int)$budget->id,
                    'area_id'           => $areaId,
                    'travel_request_id' => (int)$tr->id,
                    'module'            => (string)($tr->module ?? null),
                    'type'              => 'execute',
                    'amount'            => $total,
                    'description'       => "Ejecución por aprobación Subdirección. Solicitud #{$tr->id}",
                    'created_by'        => (int)\Auth::id(),
                    'source_type'       => 'travel_request',
                    'source_id'         => (int)$tr->id,
                ];
                if ($this->columnExists('budget_movements', 'created_at')) $mv['created_at'] = now();

                DB::table('budget_movements')->insert($mv);

                // 8) Aprobar viáticos (travel_allowances) y fijar approved_amount si está null
                if ($this->tableExists('travel_allowances')) {

                    // Si existe approved_amount: setearlo = calculated_amount cuando esté null
                    if ($this->columnExists('travel_allowances', 'approved_amount')) {
                        $upd = [
                            'approved_amount' => DB::raw('calculated_amount'),
                            'status'          => 'approved',
                        ];
                        if ($this->columnExists('travel_allowances', 'updated_at')) $upd['updated_at'] = now();

                        DB::table('travel_allowances')
                            ->where('travel_request_id', (int)$tr->id)
                            ->whereIn('status', ['draft', 'liquidated', 'approved'])
                            ->whereNull('approved_amount')
                            ->update($upd);
                    }

                    // Asegurar status=approved (por si ya tenía approved_amount)
                    $upd2 = ['status' => 'approved'];
                    if ($this->columnExists('travel_allowances', 'updated_at')) $upd2['updated_at'] = now();

                    DB::table('travel_allowances')
                        ->where('travel_request_id', (int)$tr->id)
                        ->whereIn('status', ['draft', 'liquidated', 'approved'])
                        ->update($upd2);
                }

                // 9) Update travel_request: estado final + budget_id + totales (SIEMPRE)
                $updateTr = [
                    'status' => $this->finalApprovedStatus, // confirmed
                ];

                if ($this->columnExists('travel_requests', 'budget_id')) {
                    $updateTr['budget_id'] = (int)$budget->id;
                }

                if ($this->columnExists('travel_requests', 'total_transport')) $updateTr['total_transport'] = (float)($tot['total_transport'] ?? 0);
                if ($this->columnExists('travel_requests', 'total_per_diem'))  $updateTr['total_per_diem']  = (float)($tot['total_per_diem'] ?? 0);
                if ($this->columnExists('travel_requests', 'total_other'))     $updateTr['total_other']     = (float)($tot['total_other'] ?? 0);
                if ($this->columnExists('travel_requests', 'total_amount'))    $updateTr['total_amount']    = (float)($tot['total_amount'] ?? 0);

                if ($this->columnExists('travel_requests', 'updated_at')) $updateTr['updated_at'] = now();

                DB::table('travel_requests')->where('id', (int)$tr->id)->update($updateTr);

                $this->subdirectionReviewLog(
                    (int)$tr->id,
                    'approved',
                    'Aprobado por Subdirección. Presupuesto ejecutado y viáticos aprobados.'
                );
            });

            try {
                \Modules\GDF\Services\AuthorizationPdfService::generateForTravelRequest(
                    (int)$id,
                    (int)auth()->id()
                );
            } catch (\Throwable $e) {
                // La aprobación ya quedó; solo avisa.
                return back()->with('warning', 'Aprobó OK, pero falló la generación/guardado del PDF: ' . $e->getMessage());
            }

            return back()->with('success', 'Solicitud aprobada, presupuesto descontado, viáticos aprobados, totales actualizados y autorización generada.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }
    }



    protected function hasColumn(string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) return $cache[$key];

        try {
            $cache[$key] = \Illuminate\Support\Facades\Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
    private function diskPublicFirst(): string
    {
        // si tu app no tiene disk public, cae a local
        try {
            Storage::disk('public')->path(''); // prueba
            return 'public';
        } catch (\Throwable $e) {
            return 'local';
        }
    }

    private function safeDownloadName(string $name, string $path): string
    {
        $name = trim($name) !== '' ? trim($name) : basename($path);
        $name = preg_replace('/[^\w\s\.\-\(\)\[\]]+/u', '_', $name);

        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext && !str_ends_with(strtolower($name), '.' . strtolower($ext))) {
            $name .= '.' . $ext;
        }
        return $name;
    }
    public function downloadSigacDocument(int $id, int $docId)
    {
        $this->guardSubdirection();

        $this->ensureTableOrFail('travel_requests');
        $this->ensureTableOrFail('program_request_documents');

        // 1) Traer travel_request y validar que sea SITRAV y tenga source_request_id
        $tr = DB::table('travel_requests')->where('id', $id)->first();
        if (!$tr) abort(404);

        if ((string)($tr->module ?? '') !== 'sitrav' || empty($tr->source_request_id)) abort(404);

        // 2) Documento SIGAC debe pertenecer a ese program_request_id
        $doc = DB::table('program_request_documents')
            ->where('id', $docId)
            ->where('program_request_id', (int)$tr->source_request_id)
            ->first();

        if (!$doc) abort(404);

        $path = trim((string)($doc->path ?? ''));
        if ($path === '') abort(404);

        $disk = $this->diskPublicFirst();
        if (!Storage::disk($disk)->exists($path)) abort(404);

        $filename = $this->safeDownloadName($doc->name ?? basename($path), $path);
        return Storage::disk($disk)->download($path, $filename);
    }



    public function requestsReject(Request $request, $id)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $this->ensureTableOrFail('travel_requests');

        DB::table('travel_requests')->where('id', (int)$id)->update([
            'status'     => 'rejected',
            'updated_at' => now(),
        ]);

        $this->subdirectionReviewLog((int)$id, 'rejected', $data['comment']);

        return redirect()
            ->route('gdf.subdirection.requests.show', $id)
            ->with('success', 'Solicitud rechazada.');
    }

    public function requestsReturn(Request $request, $id)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        $this->ensureTableOrFail('travel_requests');

        // si quieres distinguir a quién se devolvió, puedes guardar meta en notes/log
        DB::table('travel_requests')->where('id', (int)$id)->update([
            'status'     => 'returned',
            'updated_at' => now(),
        ]);

        $this->subdirectionReviewLog((int)$id, 'returned', "[{$data['target']}] " . $data['comment']);

        return redirect()
            ->route('gdf.subdirection.requests.show', $id)
            ->with('success', 'Solicitud devuelta.');
    }


    private function applyTravelRequestFilters($q, Request $request, int $year): void
    {
        $q->whereRaw($this->yearExpr('tr') . ' = ?', [$year]);

        if ($request->filled('module')) {
            $q->where('tr.module', $request->get('module'));
        }

        if ($request->filled('status')) {
            $status = $request->get('status');
            if ($status === 'pending_subdirection') {
                $q->whereIn('tr.status', $this->pendingForSubdirection);
            } else {
                $q->where('tr.status', $status);
            }
        }

        if ($request->filled('area_id')) {
            $q->where('tr.area_id', (int) $request->get('area_id'));
        }

        if ($request->filled('budget_item_id')) {
            $q->where('tr.budget_item_id', (int) $request->get('budget_item_id'));
        }

        if ($request->filled('from')) {
            $q->whereDate('tr.start_date', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $q->whereDate('tr.start_date', '<=', $request->get('to'));
        }
    }


    private function recalculateTotalsForApproval(object $tr): array
    {
        $travelRequestId = (int)($tr->id ?? 0);
        if ($travelRequestId <= 0) {
            return [
                'changed' => false,
                'total_transport' => 0.0,
                'total_per_diem'  => 0.0,
                'total_other'     => 0.0,
                'total_amount'    => 0.0,
            ];
        }

        // 1) COSTOS (travel_costs)
        $costsTotal = 0.0;
        $transport  = 0.0;
        $otherCosts = 0.0;

        if ($this->tableExists('travel_costs')) {
            $costsTotal = (float) DB::table('travel_costs')
                ->where('travel_request_id', $travelRequestId)
                ->sum('amount');

            // Si quieres separar transport vs otros:
            $transport = (float) DB::table('travel_costs')
                ->where('travel_request_id', $travelRequestId)
                ->where('cost_type', 'transport')
                ->sum('amount');

            $otherCosts = max(0.0, $costsTotal - $transport);
        }

        $allowTotal = 0.0;
        if ($this->tableExists('travel_allowances')) {
            $allowTotal = (float) DB::table('travel_allowances')
                ->where('travel_request_id', $travelRequestId)
                ->whereIn('status', ['draft', 'liquidated', 'approved']) // excluye rejected
                ->sum(DB::raw("COALESCE(approved_amount, calculated_amount)"));
        }

        $totalTransport = $transport;
        $totalOther     = $otherCosts;
        $totalPerDiem   = $allowTotal;
        $grandTotal     = max(0, $totalTransport + $totalPerDiem + $totalOther);

        $changed =
            ((float)($tr->total_transport ?? 0) != $totalTransport) ||
            ((float)($tr->total_per_diem ?? 0)  != $totalPerDiem)   ||
            ((float)($tr->total_other ?? 0)     != $totalOther)     ||
            ((float)($tr->total_amount ?? 0)    != $grandTotal);

        return [
            'changed'         => $changed,
            'total_transport' => $totalTransport,
            'total_per_diem'  => $totalPerDiem,
            'total_other'     => $totalOther,
            'total_amount'    => $grandTotal,
        ];
    }


    private function perDiemAppliesTo(object $tr): bool
    {
        $type = strtolower((string)($tr->person_type ?? ''));
        if ($type === 'staff') return true;

        if ($type === 'contractor') {
            return (bool) config('gdf.per_diem_contractors', false);
        }

        return false;
    }

    private function computePerDiemTotal(object $tr): float
    {
        if (empty($tr->start_date) || empty($tr->end_date)) return 0.0;

        try {
            $start = Carbon::parse((string)$tr->start_date)->startOfDay();
            $end   = Carbon::parse((string)$tr->end_date)->startOfDay();
        } catch (\Throwable $e) {
            return 0.0;
        }

        if ($end->lt($start)) return 0.0;

        $days = $start->diffInDays($end) + 1;
        if ($days <= 0) return 0.0;

        $year = (int) $start->format('Y');

        $rate = $this->resolvePerDiemRate($year, strtolower((string)($tr->person_type ?? 'staff')));
        if ($rate <= 0) return 0.0;

        $notes = $this->safeJsonDecode((string)($tr->notes ?? ''));
        if (is_array($notes)) {
            if (isset($notes['per_diem_days']) && is_numeric($notes['per_diem_days'])) {
                $days = max(0, (int)$notes['per_diem_days']);
            }
            if (isset($notes['per_diem_rate']) && is_numeric($notes['per_diem_rate'])) {
                $rate = max(0, (float)$notes['per_diem_rate']);
            }
        }

        return (float) ($days * $rate);
    }

    private function resolvePerDiemRate(int $year, string $personType): float
    {
        if ($this->tableExists('travel_allowances')) {

            $rateCol = null;
            foreach (['daily_amount', 'amount', 'value', 'rate'] as $c) {
                if ($this->columnExists('travel_allowances', $c)) {
                    $rateCol = $c;
                    break;
                }
            }

            if ($rateCol) {
                $q = DB::table('travel_allowances');

                if ($this->columnExists('travel_allowances', 'year')) {
                    $q->where('year', $year);
                }
                if ($this->columnExists('travel_allowances', 'person_type')) {
                    $q->where('person_type', $personType);
                }
                if ($this->columnExists('travel_allowances', 'active')) {
                    $q->where('active', 1);
                }

                $row = $q->orderByDesc('id')->first();
                if ($row && isset($row->{$rateCol})) return (float) $row->{$rateCol};
            }
        }

        return (float) config('gdf.per_diem_default_rate', 0);
    }

    private function safeJsonDecode(string $raw)
    {
        $raw = trim($raw);
        if ($raw === '' || $raw === 'null') return null;
        if ($raw[0] !== '{' && $raw[0] !== '[') return null;

        try {
            $x = json_decode($raw, true);
            return (json_last_error() === JSON_ERROR_NONE) ? $x : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function subdirectionReviewLog(int $travelRequestId, string $action, ?string $comments): void
    {
        if (!$this->tableExists('travel_reviews')) return;
        if (!$this->columnExists('travel_reviews', 'travel_request_id')) return;

        $payload = [
            'travel_request_id' => $travelRequestId,
            'reviewer_id'       => Auth::id(),
            'action'            => $action,
            'comments'          => $comments,
        ];

        if ($this->columnExists('travel_reviews', 'created_at')) $payload['created_at'] = now();
        if ($this->columnExists('travel_reviews', 'updated_at')) $payload['updated_at'] = now();

        try {
            DB::table('travel_reviews')->insert($payload);
        } catch (\Throwable $e) {
            // no tumbar
        }
    }
    public function movementsIndex(Request $request)
    {
        $this->guardSubdirection();

        $this->ensureTableOrFail('budget_movements');

        $year   = (int) $request->get('year', now()->year);
        $q      = trim((string) $request->get('q', ''));
        $type   = (string) $request->get('type', '');
        $module = (string) $request->get('module', '');
        $areaId = (int) $request->get('area_id', 0);
        $itemId = (int) $request->get('budget_item_id', 0);

        $bm = DB::table('budget_movements as bm');

        $hasBudgets = $this->tableExists('budgets');
        $hasAreas   = $this->tableExists('areas');
        $hasItems   = $this->tableExists('budget_items');
        $hasUsers   = $this->tableExists('users');
        $hasPeople  = $this->tableExists('people');

        if ($hasBudgets) {
            $bm->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                ->where('b.year', $year)
                ->where('b.active', 1);

            if ($itemId > 0) $bm->where('b.budget_item_id', $itemId);
            if ($areaId > 0) $bm->where('b.area_id', $areaId);
        } else {
            // fallback por fecha del movimiento
            if ($this->columnExists('budget_movements', 'created_at')) {
                $bm->whereRaw("YEAR(bm.created_at) = ?", [$year]);
            }
            if ($areaId > 0 && $this->columnExists('budget_movements', 'area_id')) {
                $bm->where('bm.area_id', $areaId);
            }
        }

        if ($type !== '')   $bm->where('bm.type', $type);
        if ($module !== '') $bm->where('bm.module', $module);

        if ($q !== '') {
            $bm->where(function ($w) use ($q) {
                $w->where('bm.id', $q)
                    ->orWhere('bm.budget_id', $q)
                    ->orWhere('bm.travel_request_id', $q)
                    ->orWhere('bm.description', 'like', "%{$q}%");
            });
        }

        if ($hasAreas) {
            $bm->leftJoin('areas as a', 'a.id', '=', $hasBudgets ? 'b.area_id' : 'bm.area_id');
        }

        if ($hasItems && $hasBudgets) {
            $bm->leftJoin('budget_items as bi', 'bi.id', '=', 'b.budget_item_id');
        }

        if ($hasUsers) {
            $bm->leftJoin('users as u', 'u.id', '=', 'bm.created_by');

            $hasUserPersonId = $this->columnExists('users', 'person_id');
            if ($hasPeople && $hasUserPersonId) {
                $bm->leftJoin('people as up', 'up.id', '=', 'u.person_id');
            }
        }

        // Select base
        $bm->selectRaw("
        bm.id,
        bm.type,
        bm.module,
        bm.amount,
        bm.description,
        bm.created_at,
        bm.budget_id,
        bm.travel_request_id,
        bm.created_by
    ");

        if ($hasBudgets) {
            $bm->addSelect([
                DB::raw('b.area_id as budget_area_id'),
                DB::raw('b.budget_item_id as budget_item_id'),
            ]);
        }

        if ($hasAreas) {
            // Nota: aquí "a.name" es de areas, normalmente existe.
            $bm->addSelect(DB::raw("COALESCE(a.name, '—') as area_name"));
        } else {
            $bm->addSelect(DB::raw("'—' as area_name"));
        }

        if ($hasItems && $hasBudgets) {
            $bm->addSelect(DB::raw("COALESCE(bi.name, '—') as budget_item_name"));

            if ($this->columnExists('budget_items', 'code')) {
                $bm->addSelect(DB::raw("COALESCE(bi.code, '') as budget_item_code"));
            } else {
                $bm->addSelect(DB::raw("'' as budget_item_code"));
            }
        } else {
            $bm->addSelect(DB::raw("'—' as budget_item_name"));
            $bm->addSelect(DB::raw("'' as budget_item_code"));
        }

        // ✅ created_by_name ROBUSTO (sin u.name fijo)
        if ($hasUsers) {

            $hasUserPersonId = $this->columnExists('users', 'person_id');

            // Caso ideal: nombre desde people
            if ($hasPeople && $hasUserPersonId) {
                $personExpr = "TRIM(CONCAT_WS(' ',
                NULLIF(up.first_name,''),
                NULLIF(up.first_last_name,''),
                NULLIF(up.second_last_name,'')
            ))";

                $fallback = $this->columnExists('users', 'email') ? "NULLIF(u.email,'')" : "NULL";

                $bm->addSelect(DB::raw("COALESCE(NULLIF($personExpr,''), $fallback, '—') as created_by_name"));
            } else {
                // Fallback: detectar columna nombre en users
                $userNameCol = null;
                foreach (['name', 'full_name', 'username', 'nickname'] as $c) {
                    if ($this->columnExists('users', $c)) {
                        $userNameCol = $c;
                        break;
                    }
                }

                $userEmailCol = $this->columnExists('users', 'email') ? 'email' : null;

                if ($userNameCol && $userEmailCol) {
                    $bm->addSelect(DB::raw("COALESCE(NULLIF(u.$userNameCol,''), NULLIF(u.$userEmailCol,''), '—') as created_by_name"));
                } elseif ($userNameCol) {
                    $bm->addSelect(DB::raw("COALESCE(NULLIF(u.$userNameCol,''), '—') as created_by_name"));
                } elseif ($userEmailCol) {
                    $bm->addSelect(DB::raw("COALESCE(NULLIF(u.$userEmailCol,''), '—') as created_by_name"));
                } else {
                    $bm->addSelect(DB::raw("'—' as created_by_name"));
                }
            }
        } else {
            $bm->addSelect(DB::raw("'—' as created_by_name"));
        }

        // Orden
        if ($this->columnExists('budget_movements', 'created_at')) {
            $bm->orderByDesc('bm.created_at');
        } else {
            $bm->orderByDesc('bm.id');
        }

        $rows = $bm->paginate(20)->appends($request->query());

        // Para filtros (si existen)
        $areas = $hasAreas ? DB::table('areas')->orderBy('name')->get() : collect();
        $items = $hasItems ? DB::table('budget_items')->orderBy('name')->get() : collect();

        return view('gdf::subdirection.movements.index', compact(
            'rows',
            'year',
            'q',
            'type',
            'module',
            'areaId',
            'itemId',
            'areas',
            'items'
        ));
    }
    public function rubrosIndex(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budget_items');

        $year = (int) $request->get('year', now()->year);
        if ($year < 2000 || $year > 2100) $year = (int) now()->year;

        $q = trim((string) $request->get('q', ''));

        $hasBudgets = $this->tableExists('budgets');
        $hasMoves   = $this->tableExists('budget_movements');


        $globalAvail = 0.0;
        $globalSpent = 0.0;
        $globalTotal = 0.0;

        if ($hasBudgets) {
            $globalAvail = (float) DB::table('budgets')
                ->where('year', $year)
                ->where('active', 1)
                ->sum('current_amount');

            if ($hasMoves) {
                $globalSpent = (float) DB::table('budget_movements as bm')
                    ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                    ->where('b.year', $year)
                    ->where('b.active', 1)
                    ->where('bm.type', 'execute')
                    ->sum('bm.amount');
            }

            $globalTotal = $globalAvail + $globalSpent;
        }

        $qb = DB::table('budget_items as bi')
            ->where('bi.active', 1);

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                $w->where('bi.name', 'like', "%{$q}%")
                    ->orWhere('bi.code', 'like', "%{$q}%");
            });
        }

    
        if ($hasBudgets) {
            $budgetsAgg = DB::table('budgets as b')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->groupBy('b.budget_item_id')
                ->selectRaw('
                b.budget_item_id,
                COALESCE(SUM(b.current_amount),0) as avail,
                COUNT(DISTINCT b.area_id) as areas_count
            ');

            $qb->leftJoinSub($budgetsAgg, 'ba', function ($join) {
                $join->on('ba.budget_item_id', '=', 'bi.id');
            });
        }

   
        if ($hasBudgets && $hasMoves) {
            $movesAgg = DB::table('budget_movements as bm')
                ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->groupBy('b.budget_item_id')
                ->selectRaw('
                b.budget_item_id,
                COALESCE(SUM(CASE WHEN bm.type = "execute" THEN bm.amount ELSE 0 END),0) as spent,
                COUNT(DISTINCT bm.id) as moves_count
            ');

            $qb->leftJoinSub($movesAgg, 'ma', function ($join) {
                $join->on('ma.budget_item_id', '=', 'bi.id');
            });
        }

        // =========================
        // ✅ Select final (sin duplicar)
        // =========================
        $rows = $qb->selectRaw('
            bi.id,
            bi.code,
            bi.name,
            COALESCE(ba.avail,0) as avail,
            COALESCE(ma.spent,0) as spent,
            (COALESCE(ba.avail,0) + COALESCE(ma.spent,0)) as total,
            COALESCE(ba.areas_count,0) as areas_count,
            COALESCE(ma.moves_count,0) as moves_count
        ')
            ->orderBy('bi.name')
            ->paginate(15)
            ->appends($request->query());

        return view('gdf::subdirection.rubros.index', [
            'rows'        => $rows,
            'year'        => $year,
            'q'           => $q,
            'globalAvail' => (int) round($globalAvail),
            'globalSpent' => (int) round($globalSpent),
            'globalTotal' => (int) round($globalTotal),
        ]);
    }

    public function budgetsCreate(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');
        $this->ensureTableOrFail('budget_items');

        $year = (int) $request->get('year', now()->year);
        if ($year < 2000 || $year > 2100) $year = (int) now()->year;

        $selectedAreaId  = $request->get('area_id');
        $selectedRubroId = $request->get('budget_item_id');

        // Áreas
        $areas = $this->tableExists('areas')
            ? DB::table('areas')->orderBy('name')->get()
            : collect();

        // Rubros
        $rubros = DB::table('budget_items')
            ->where('active', 1)
            ->orderBy('name')
            ->get();

        // Presupuestos existentes (para evitar duplicados)
        $existing = collect();
        $existingRubroIds = collect();

        if ($selectedAreaId) {
            $existing = DB::table('budgets')
                ->where('year', $year)
                ->where('active', 1)
                ->where('area_id', (int)$selectedAreaId)
                ->orderByDesc('id')
                ->get();

            $existingRubroIds = $existing->pluck('budget_item_id')->unique()->values();
        }

        // Promedios / históricos (opcionales)
        $avgByAreaRubro = null;
        $avgByRubro = null;
        $historyByAreaRubro = collect();
        $historyByRubro = collect();

        if ($selectedAreaId && $selectedRubroId) {
            $avgByAreaRubro = (float) DB::table('budgets')
                ->where('active', 1)
                ->where('area_id', (int)$selectedAreaId)
                ->where('budget_item_id', (int)$selectedRubroId)
                ->avg('current_amount');

            $historyByAreaRubro = DB::table('budgets')
                ->where('active', 1)
                ->where('area_id', (int)$selectedAreaId)
                ->where('budget_item_id', (int)$selectedRubroId)
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        if ($selectedRubroId) {
            $avgByRubro = (float) DB::table('budgets')
                ->where('active', 1)
                ->where('budget_item_id', (int)$selectedRubroId)
                ->avg('current_amount');

            $historyByRubro = DB::table('budgets')
                ->where('active', 1)
                ->where('budget_item_id', (int)$selectedRubroId)
                ->orderByDesc('id')
                ->limit(5)
                ->get();
        }

        return view('gdf::subdirection.budgets.create', compact(
            'year',
            'areas',
            'rubros',
            'selectedAreaId',
            'selectedRubroId',
            'existing',
            'existingRubroIds',
            'avgByAreaRubro',
            'avgByRubro',
            'historyByAreaRubro',
            'historyByRubro'
        ));
    }

    public function budgetsStore(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');
        $this->ensureTableOrFail('budget_movements');

        $data = $request->validate([
            'year'           => ['required', 'integer', 'min:2000', 'max:2100'],
            'area_id'        => ['required', 'integer', 'min:1'],
            'budget_item_id' => ['required', 'integer', 'min:1'],
            'initial_amount' => ['required', 'numeric', 'min:0'],
            'active'         => ['nullable', 'boolean'],
        ]);

        $year   = (int) $data['year'];
        $areaId = (int) $data['area_id'];
        $itemId = (int) $data['budget_item_id'];
        $amount = (float) $data['initial_amount'];
        $active = (int) $request->boolean('active', true);

        try {
            DB::transaction(function () use ($year, $areaId, $itemId, $amount, $active) {

                // ✅ 1) Crear presupuesto
                $payload = [
                    'year'           => $year,
                    'area_id'        => $areaId,
                    'budget_item_id' => $itemId,
                    'initial_amount' => $amount,
                    'current_amount' => $amount,
                    'active'         => $active,
                ];
                if ($this->columnExists('budgets', 'created_at')) $payload['created_at'] = now();
                if ($this->columnExists('budgets', 'updated_at')) $payload['updated_at'] = now();

                $budgetId = DB::table('budgets')->insertGetId($payload);

                $mv = [
                    'budget_id'         => (int) $budgetId,
                    'area_id'           => $areaId,
                    'travel_request_id' => null,
                    'module'            => 'gdf', // o null si prefieres
                    'type'              => 'addition',
                    'amount'            => $amount,
                    'description'       => "Creación de presupuesto: carga inicial {$year} (Área {$areaId}, Rubro {$itemId})",
                    'created_by'        => (int) auth()->id(),
                    'source_type'       => 'budget',
                    'source_id'         => (int) $budgetId,
                ];
                if ($this->columnExists('budget_movements', 'created_at')) $mv['created_at'] = now();

                DB::table('budget_movements')->insert($mv);
            });

            return redirect()
                ->route('gdf.subdirection.budgets.index', ['year' => $year])
                ->with('success', 'Presupuesto creado correctamente y movimiento de adición registrado.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'No se pudo crear el presupuesto: ' . $e->getMessage());
        }
    }


    public function budgetsIndex(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');
        $this->ensureTableOrFail('budget_items');
        $this->ensureTableOrFail('areas');

        $year = (int) $request->get('year', now()->year);
        if ($year < 2000 || $year > 2100) $year = (int) now()->year;

        $q = trim((string) $request->get('q', ''));

        $qb = DB::table('budgets as b')
            ->join('budget_items as bi', 'bi.id', '=', 'b.budget_item_id')
            ->join('areas as a', 'a.id', '=', 'b.area_id')
            ->where('b.year', $year);

        if ($q !== '') {
            $qb->where(function ($w) use ($q) {
                if (is_numeric($q)) $w->orWhere('b.id', (int)$q);

                $w->orWhere('bi.name', 'like', "%{$q}%")
                    ->orWhere('bi.code', 'like', "%{$q}%")
                    ->orWhere('a.name', 'like', "%{$q}%");
            });
        }

        $budgets = $qb->selectRaw("
            b.id,
            b.year,
            b.area_id,
            a.name as area_name,
            b.budget_item_id,
            bi.name as budget_item_name,
            COALESCE(bi.code,'') as budget_item_code,
            COALESCE(b.initial_amount,0) as total_amount,
            COALESCE(b.current_amount,0) as current_amount,
            COALESCE(b.active,1) as active
        ")
            ->orderBy('a.name')
            ->orderBy('bi.name')
            ->paginate(15)
            ->appends($request->query());

        $kpiTotal      = (float) DB::table('budgets')->where('year', $year)->sum('initial_amount');
        $kpiAvailable  = (float) DB::table('budgets')->where('year', $year)->sum('current_amount');
        $kpiExecuted   = max(0, $kpiTotal - $kpiAvailable);

        return view('gdf::subdirection.budgets.index', [
            'budgets'      => $budgets,
            'year'         => $year,
            'q'            => $q,
            'warning'      => null,
            'kpiTotal'     => $kpiTotal,
            'kpiAvailable' => $kpiAvailable,
            'kpiExecuted'  => $kpiExecuted,
        ]);
    }



    public function budgetsEdit($id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');

        $hasItems = $this->tableExists('budget_items');

        $q = DB::table('budgets as b')->where('b.id', (int)$id);

        if ($hasItems) {
            $q->leftJoin('budget_items as bi', 'bi.id', '=', 'b.budget_item_id');
        }

        // Select con alias para la vista
        $select = [
            'b.*',
        ];

        if ($hasItems) {
            $select[] = DB::raw("COALESCE(bi.name,'—') as budget_item_name");
            if ($this->columnExists('budget_items', 'code')) {
                $select[] = DB::raw("COALESCE(bi.code,'') as budget_item_code");
            } else {
                $select[] = DB::raw("'' as budget_item_code");
            }
        } else {
            $select[] = DB::raw("'—' as budget_item_name");
            $select[] = DB::raw("'' as budget_item_code");
        }

        $budget = $q->select($select)->first();
        if (!$budget) abort(404);

        return view('gdf::subdirection.budgets.edit', compact('budget'));
    }



    public function rubrosCreate()
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budget_items');

        return view('gdf::subdirection.rubros.create');
    }

    public function rubrosStore(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budget_items');

        // Validación dinámica
        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($this->columnExists('budget_items', 'code')) {
            $rules['code'] = ['required', 'string', 'max:50', 'unique:budget_items,code'];
        } else {
            // si no existe code en BD, no lo exijas
            $rules['code'] = ['nullable', 'string', 'max:50'];
        }

        if ($this->columnExists('budget_items', 'description')) {
            $rules['description'] = ['nullable', 'string', 'max:2000'];
        }

        if ($this->columnExists('budget_items', 'allow_staff')) {
            $rules['allow_staff'] = ['nullable', 'boolean'];
        }
        if ($this->columnExists('budget_items', 'allow_contractors')) {
            $rules['allow_contractors'] = ['nullable', 'boolean'];
        }
        if ($this->columnExists('budget_items', 'active')) {
            $rules['active'] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        $payload = [
            'name' => $data['name'],
        ];

        if ($this->columnExists('budget_items', 'code')) {
            $payload['code'] = $data['code'];
        }
        if ($this->columnExists('budget_items', 'description')) {
            $payload['description'] = $data['description'] ?? null;
        }
        if ($this->columnExists('budget_items', 'allow_staff')) {
            $payload['allow_staff'] = (int)($request->boolean('allow_staff'));
        }
        if ($this->columnExists('budget_items', 'allow_contractors')) {
            $payload['allow_contractors'] = (int)($request->boolean('allow_contractors'));
        }
        if ($this->columnExists('budget_items', 'active')) {
            $payload['active'] = (int)($request->boolean('active', true));
        }

        // timestamps si existen
        if ($this->columnExists('budget_items', 'created_at')) $payload['created_at'] = now();
        if ($this->columnExists('budget_items', 'updated_at')) $payload['updated_at'] = now();

        $id = DB::table('budget_items')->insertGetId($payload);

        return redirect()
            ->route('gdf.subdirection.rubros', $id)
            ->with('success', 'Rubro creado correctamente.');
    }

    public function rubrosEdit($id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budget_items');

        $rubro = DB::table('budget_items')->where('id', (int)$id)->first();
        if (!$rubro) abort(404);

        return view('gdf::subdirection.rubros.edit', compact('rubro'));
    }

    public function rubrosUpdate(Request $request, $id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budget_items');

        $rubro = DB::table('budget_items')->where('id', (int)$id)->first();
        if (!$rubro) abort(404);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
        ];

        if ($this->columnExists('budget_items', 'code')) {
            $rules['code'] = ['nullable', 'string', 'max:50', "unique:budget_items,code,{$id}"];
        } else {
            $rules['code'] = ['nullable', 'string', 'max:50'];
        }

        if ($this->columnExists('budget_items', 'description')) {
            $rules['description'] = ['nullable', 'string', 'max:2000'];
        }

        if ($this->columnExists('budget_items', 'allow_staff')) {
            $rules['allow_staff'] = ['nullable', 'boolean'];
        }
        if ($this->columnExists('budget_items', 'allow_contractors')) {
            $rules['allow_contractors'] = ['nullable', 'boolean'];
        }
        if ($this->columnExists('budget_items', 'active')) {
            $rules['active'] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        $payload = [
            'name' => $data['name'],
        ];

        if ($this->columnExists('budget_items', 'code')) {
            $payload['code'] = $data['code'] ?? null;
        }
        if ($this->columnExists('budget_items', 'description')) {
            $payload['description'] = $data['description'] ?? null;
        }
        if ($this->columnExists('budget_items', 'allow_staff')) {
            $payload['allow_staff'] = (int)($request->boolean('allow_staff'));
        }
        if ($this->columnExists('budget_items', 'allow_contractors')) {
            $payload['allow_contractors'] = (int)($request->boolean('allow_contractors'));
        }
        if ($this->columnExists('budget_items', 'active')) {
            $payload['active'] = (int)($request->boolean('active'));
        }

        if ($this->columnExists('budget_items', 'updated_at')) $payload['updated_at'] = now();

        DB::table('budget_items')->where('id', (int)$id)->update($payload);

        return redirect()
            ->route('gdf.subdirection.rubros.edit', $id)
            ->with('success', 'Rubro actualizado correctamente.');
    }

    private function ensureTableOrFail(string $table): void
    {
        if (!$this->tableExists($table)) abort(500, "Tabla requerida no existe: {$table}");
    }

    private function tableExists(string $table): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            return DB::getSchemaBuilder()->hasColumn($table, $column);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function safeCount(string $table): int
    {
        if (!$this->tableExists($table)) return 0;
        return (int) DB::table($table)->count();
    }

    private function safeCountWhere(string $table, array $where): int
    {
        if (!$this->tableExists($table)) return 0;
        $q = DB::table($table);
        foreach ($where as $k => $v) $q->where($k, $v);
        return (int) $q->count();
    }

    private function safeCountWhereIn(string $table, string $col, array $vals): int
    {
        if (!$this->tableExists($table)) return 0;
        return (int) DB::table($table)->whereIn($col, $vals)->count();
    }

    private function yearExpr(string $alias): string
    {
        if ($this->columnExists('travel_requests', 'start_date')) {
            return "YEAR(COALESCE({$alias}.start_date, {$alias}.created_at))";
        }
        return "YEAR({$alias}.created_at)";
    }

    private function resolveYearFromTravel(object $tr): int
    {
        try {
            if (!empty($tr->start_date)) return (int) Carbon::parse((string)$tr->start_date)->format('Y');
        } catch (\Throwable $e) {
        }
        try {
            if (!empty($tr->created_at)) return (int) Carbon::parse((string)$tr->created_at)->format('Y');
        } catch (\Throwable $e) {
        }
        return (int) now()->format('Y');
    }
    public function auditIndex(Request $request)
    {
        $this->guardSubdirection();

        $year   = (int)($request->get('year') ?: now()->year);
        $area   = (string)($request->get('area') ?: 'all');     // all|academic|campesena
        $module = (string)($request->get('module') ?: 'all');   // all|gdf|sitrav
        $limit  = (int)($request->get('limit') ?: 10);

        $countableStatuses = ['approved', 'executed', 'confirmed'];

        // ✅ Como areas SOLO tiene name, inferimos el tipo por el nombre
        $areaKeyExpr = "CASE
        WHEN LOWER(a.name) LIKE '%campesena%' THEN 'campesena'
        ELSE 'academic'
    END";

        $base = DB::table('travel_requests as tr')
            ->join('areas as a', 'a.id', '=', 'tr.area_id')
            ->leftJoin('travel_segments as ts', 'ts.travel_request_id', '=', 'tr.id')
            ->whereYear('tr.created_at', $year)
            ->whereIn('tr.status', $countableStatuses);

        // Filtro módulo
        if ($module !== 'all') {
            $base->where('tr.module', $module);
        }

        // Filtro área
        if ($area !== 'all') {
            $base->whereRaw("$areaKeyExpr = ?", [$area]);
        }

        // 1) Top Municipios por Área
        $topMunicipalitiesByArea = (clone $base)
            ->leftJoin('municipality_rates as mr', 'mr.id', '=', 'ts.municipality_rate_id')
            ->selectRaw("$areaKeyExpr as area_key")
            ->selectRaw("LOWER(TRIM(COALESCE(mr.municipality_name, ts.destination_place, ''))) as municipality")
            ->selectRaw("COUNT(*) as total")
            ->where('ts.destination_type', 'municipality')
            ->whereRaw("COALESCE(mr.municipality_name, ts.destination_place, '') <> ''")
            ->groupBy('area_key', 'municipality')
            ->orderByDesc('total')
            ->limit(5000)
            ->get()
            ->groupBy('area_key')
            ->map(fn($rows) => $rows->take($limit)->values());

        $topVillagesByArea = (clone $base)
            ->leftJoin('village_rates as vr', 'vr.id', '=', 'ts.village_rate_id')
            ->selectRaw("$areaKeyExpr as area_key")
            ->selectRaw("LOWER(TRIM(COALESCE(vr.village_name, ts.destination_place, ''))) as village")
            ->selectRaw("COUNT(*) as total")
            ->where('ts.destination_type', 'village')
            ->whereRaw("COALESCE(vr.village_name, ts.destination_place, '') <> ''")
            ->groupBy('area_key', 'village')
            ->orderByDesc('total')
            ->limit(5000)
            ->get()
            ->groupBy('area_key')
            ->map(fn($rows) => $rows->take($limit)->values());

        $countsByArea = (clone $base)
            ->selectRaw("$areaKeyExpr as area_key")
            ->selectRaw("COUNT(DISTINCT tr.id) as total")
            ->groupBy('area_key')
            ->get()
            ->keyBy('area_key');

        $countsAreaModule = (clone $base)
            ->selectRaw("$areaKeyExpr as area_key")
            ->selectRaw("tr.module as module")
            ->selectRaw("COUNT(DISTINCT tr.id) as total")
            ->groupBy('area_key', 'tr.module')
            ->get();

        $areasKeys   = collect(['academic', 'campesena'])->values();
        $modulesKeys = collect(['gdf', 'sitrav'])->values();

        $stack = [];
        foreach ($areasKeys as $ak) foreach ($modulesKeys as $mk) $stack[$ak][$mk] = 0;

        foreach ($countsAreaModule as $row) {
            $ak = $row->area_key ?: 'academic';
            $mk = $row->module ?: 'gdf';
            if (!isset($stack[$ak])) $stack[$ak] = ['gdf' => 0, 'sitrav' => 0];
            $stack[$ak][$mk] = (int)$row->total;
        }

        $chart = [
            'year' => $year,
            'labelsAreas' => $areasKeys,
            'countsByArea' => $areasKeys->map(fn($ak) => (int)($countsByArea[$ak]->total ?? 0))->values(),
            'stack' => [
                'gdf'    => $areasKeys->map(fn($ak) => (int)($stack[$ak]['gdf'] ?? 0))->values(),
                'sitrav' => $areasKeys->map(fn($ak) => (int)($stack[$ak]['sitrav'] ?? 0))->values(),
            ],
        ];

        return view('gdf::subdirection.audit.index', compact(
            'year',
            'area',
            'module',
            'limit',
            'topMunicipalitiesByArea',
            'topVillagesByArea',
            'chart'
        ));
    }
}
