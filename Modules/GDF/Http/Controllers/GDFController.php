<?php

namespace Modules\GDF\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Modules\GDF\Entities\TravelRequest;


class GDFController extends Controller
{


    /* ============================================================
     * HOME del módulo: decide si entra directo o muestra CTA al gateway
     * ============================================================ */
    public function index(Request $request)
    {
        // 1) Invitado => vista pública (inspección)
        if (!auth()->check()) {
            return $this->publicLanding($request);
        }

        // 2) Autenticado => flujo normal (tu lógica actual)
        return $this->privateIndex($request);
    }

    /**
     * Landing pública (sin login)
     * - NO usa checkRol
     * - Solo muestra info del sistema + links + botón login
     */
    private function publicLanding(Request $request)
    {
        return view('gdf::home', [
            'primaryAction' => [
                'route' => route('login'),
                'label' => 'Iniciar sesión',
                'icon'  => 'bi-box-arrow-in-right',
            ],
            'hasGdfAccess' => false,
        ]);
    }

    /**
     * Index privado (logueado) = tu método index actual, tal cual,
     * pero corregido el warning final.
     */
    private function privateIndex(Request $request)
    {
        $options = $this->getContextOptionsForUserRobust(8);

        if (empty($options)) {
            return view('gdf::index', [
                'primaryAction' => [
                    'route' => route('gdf.gateway'),
                    'label' => 'Elegir área / contexto',
                    'icon'  => 'bi-grid-1x2'
                ],
                'hasGdfAccess' => false,
                'warning'      => 'Tu usuario no tiene roles/áreas asignadas en GDF (o tu asignación aún no está dentro del plazo de ingreso).',
            ]);
        }

        if (count($options) === 1) {
            $only  = $options[0];
            $role  = $only['key'];
            $areas = $only['areas'] ?? [];

            if (!$this->roleRequiresArea($role)) {
                session(['gdf_context' => ['role' => $role, 'area' => null]]);
                return $this->redirectByContext($role, null);
            }

            if (count($areas) === 1) {
                session(['gdf_context' => ['role' => $role, 'area' => $areas[0]]]);
                return $this->redirectByContext($role, $areas[0]);
            }

            return redirect()->route('gdf.gateway');
        }

        return view('gdf::index', [
            'primaryAction' => [
                'route' => route('gdf.gateway'),
                'label' => 'Elegir área / contexto',
                'icon'  => 'bi-grid-1x2'
            ],
            'hasGdfAccess' => true,
            'warning'      => null, // ✅ corregido
        ]);
    }



    /* ============================================================
     * Proceso: redirige según el contexto actual
     * ============================================================ */
    public function process(Request $request)
    {
        $ctx  = session('gdf_context', []);
        $role = $ctx['role'] ?? null;
        $area = $ctx['area'] ?? null;

        if (!$role) {
            return redirect()->route('gdf.gateway')
                ->with('warning', 'Debes seleccionar un contexto.');
        }

        if ($this->roleRequiresArea($role) && !$area) {
            return redirect()->route('gdf.gateway')
                ->with('warning', 'Debes seleccionar un área para continuar.');
        }

        return $this->redirectByContext($role, $area);
    }

    /* ============================================================
     * Gateway (selector)
     * ============================================================ */
    public function gateway(Request $request)
    {
        $ctx = session('gdf_context', []);

        // OJO: el gateway normalmente debe usar la misma lógica robusta
        $options = $this->getContextOptionsForUserRobust(8);

        return view('gdf::gateway.index', [
            'ctx'     => $ctx,
            'options' => $options,
            'error'   => empty($options) ? 'No tienes roles/áreas asignadas en GDF.' : null,
        ]);
    }

    public function select(Request $request)
    {
        $options = $this->getContextOptionsForUserRobust(8);

        if (empty($options)) {
            return redirect()->route('gdf.index')
                ->with('error', 'No tienes roles/áreas asignadas en GDF.');
        }

        $allowedRoles = array_column($options, 'key');

        $data = $request->validate([
            'ctx'  => ['required', 'string', 'in:' . implode(',', $allowedRoles)],
            'area' => ['nullable', 'string', 'in:academic,campesena'],
        ]);

        $selectedRole = $data['ctx'];
        $selectedArea = $data['area'] ?? null;

        $cfg = collect($options)->firstWhere('key', $selectedRole);
        if (!$cfg) {
            return back()->with('error', 'Opción inválida.');
        }

        // Resolver autoselección de área si aplica
        [$finalRole, $finalArea] = $this->resolveRoleAndArea($cfg, $selectedArea);

        if (!$finalRole) {
            return back()->with('error', 'No se pudo resolver el contexto.');
        }

        // Validar área si el rol la requiere
        if ($this->roleRequiresArea($finalRole)) {
            $areasAllowed = $cfg['areas'] ?? [];

            if (!$finalArea) {
                return back()->with('error', 'Debes seleccionar un área.');
            }

            if (!empty($areasAllowed) && !in_array($finalArea, $areasAllowed, true)) {
                return back()->with('error', 'No tienes permisos en esa área.');
            }
        } else {
            $finalArea = null;
        }

        session(['gdf_context' => ['role' => $finalRole, 'area' => $finalArea]]);

        return $this->redirectByContext($finalRole, $finalArea);
    }

    public function clearContext(Request $request)
    {
        $request->session()->forget('gdf_context');
        return redirect()->route('gdf.index')->with('success', 'Contexto limpiado.');
    }

    /* ============================================================
     * Dashboards (placeholders)
     * ============================================================ */
    public function treasury()
    {
        return view('gdf::treasury.dashboard');
    }

    public function academic_coordination(Request $request)
    {
        return $this->dashboardByGroup($request, 'academic');
    }

    public function campesena(Request $request)
    {
        return $this->dashboardByGroup($request, 'campesena');
    }

    /* ============================================================
     * Redirect central por contexto
     * ============================================================ */
    private function redirectByContext(string $ctxRole, ?string $area)
    {
        return match ($ctxRole) {

            'official' => Route::has('gdf.instructor.dashboard')
                ? redirect()->route('gdf.instructor.dashboard')
                : redirect()->route('gdf.gateway'),

            'coord' => $area === 'academic'
                ? redirect()->route('gdf.academic.dashboard')
                : redirect()->route('gdf.campesena.dashboard'),

            'support' => $area === 'academic'
                ? redirect()->route('gdf.support.academic.dashboard')
                : redirect()->route('gdf.support.campesena.dashboard'),


            'treasury' => Route::has('gdf.treasury.dashboard')
                ? redirect()->route('gdf.treasury.dashboard')
                : redirect()->route('gdf.gateway'),

            'subdirection', 'superadmin' => Route::has('gdf.subdirection.dashboard')
                ? redirect()->route('gdf.subdirection.dashboard')
                : redirect()->route('gdf.gateway'),

            default => redirect()->route('gdf.gateway'),
        };
    }

    private function dashboardByGroup(Request $request, string $group)
    {
        $areaIds = (array) config("gdf.area_groups.$group", []);
        if (empty($areaIds)) abort(403, "Área {$group} no configurada en gdf.area_groups");

        $ctx  = session('gdf_context', []);
        $role = (string)($ctx['role'] ?? '');

        // Prefijos y labels por área
        $areaKey     = $group;
        $routePrefix = $group === 'academic' ? 'gdf.academic' : 'gdf.campesena';

        // =========================
        // COORDINACIÓN: lista de solicitudes aprobadas por tesorería
        // =========================
        if ($role === 'coord') {
            $q = trim((string) $request->get('q', ''));

            $query = TravelRequest::query()
                ->where('status', 'approved_by_treasury')
                ->whereIn('area_id', $areaIds);

            if ($q !== '') {
                $query->where(function ($sub) use ($q) {
                    $sub->where('origin', 'like', "%{$q}%")
                        ->orWhere('destination', 'like', "%{$q}%")
                        ->orWhere('id', $q);
                });
            }

            $requests = $query->latest('updated_at')->paginate(15)->appends(['q' => $q]);

            $title = $group === 'academic' ? 'Coordinación Académica' : 'Coordinación Campesena';

            return view('gdf::coordination.dashboard', compact('requests', 'q', 'areaKey', 'routePrefix', 'title'));
        }

        // =========================
        // APOYO: tu dashboard propio (motos + bandeja)
        // =========================
        if ($role === 'support') {
            // KPI + bandejas (si ya tienes SupportDashboardController, puedes redirigir allá)
            $title = $group === 'academic' ? 'Apoyo Coordinación Académica' : 'Apoyo Campesena';

            // Si ya construiste esta vista:
            // return view('gdf::support.dashboard', compact('areaKey','routePrefix','title','kpis','quickRequests','queueApproved','queueDelivered'));

            // Si aún no pasas datos, al menos separa vista:
            return view('gdf::support.dashboard', compact('areaKey', 'routePrefix', 'title'));
        }

        // Si alguien entra sin rol esperado
        return redirect()->route('gdf.gateway')->with('warning', 'Contexto inválido para este dashboard.');
    }


    /* ============================================================
     * Context options: robusto (checkRol o fallback BD)
     * Y áreas por asignaciones con GRACE (para entrar)
     * ============================================================ */
    private function getContextOptionsForUserRobust(int $graceDays = 8): array
    {
        $user = Auth::user();
        if (!$user) return [];

        // Map de roles detectados
        $has = [];

        if (function_exists('checkRol')) {
            $has = [
                'gdf.superadmin'           => checkRol('gdf.superadmin'),
                'gdf.subdirection'         => checkRol('gdf.subdirection'),
                'gdf.treasury'             => checkRol('gdf.treasury'),
                'gdf.instructor'           => checkRol('gdf.instructor'),
                'gdf.academic_coordinator' => checkRol('gdf.academic_coordinator'),
                'gdf.campesena_coordinator' => checkRol('gdf.campesena_coordinator'),
                'gdf.academic_support'     => checkRol('gdf.academic_support'),
                'gdf.campesena_support'    => checkRol('gdf.campesena_support'),
                'gdf.admin'                => checkRol('gdf.admin'),
            ];
        } else {
            $user->loadMissing('roles');
            $slugs = $user->roles?->pluck('slug')->all() ?? [];
            $slugs = array_map('strval', $slugs);
            foreach ($slugs as $s) $has[$s] = true;
        }

        $hasRole = fn(string $slug) => !empty($has[$slug]);

        // Superadmin: ve todo (sin depender de asignaciones)
        if ($hasRole('gdf.superadmin')) {
            return [
                ['key' => 'superadmin', 'label' => 'Super Admin', 'desc' => 'Acceso total y soporte.', 'icon' => 'bi-shield-lock', 'areas' => []],
                ['key' => 'subdirection', 'label' => 'Subdirección', 'desc' => 'Administra parametrización.', 'icon' => 'bi-diagram-3', 'areas' => []],
                ['key' => 'treasury', 'label' => 'Tesorería', 'desc' => 'Aprueba y gestiona pagos.', 'icon' => 'bi-cash-coin', 'areas' => []],
                ['key' => 'coord', 'label' => 'Coordinación', 'desc' => 'Gestiona solicitudes por área.', 'icon' => 'bi-mortarboard', 'areas' => ['academic', 'campesena']],
                ['key' => 'support', 'label' => 'Apoyo', 'desc' => 'Valida soportes por área.', 'icon' => 'bi-person-check', 'areas' => ['academic', 'campesena']],
                ['key' => 'official', 'label' => 'Instructor / Funcionario', 'desc' => 'Crea solicitudes por área.', 'icon' => 'bi-person-badge', 'areas' => ['academic', 'campesena']],
            ];
        }

        $options = [];

        // Roles sin área
        if ($hasRole('gdf.subdirection')) {
            $options[] = ['key' => 'subdirection', 'label' => 'Subdirección', 'desc' => 'Administra parametrización.', 'icon' => 'bi-diagram-3', 'areas' => []];
        }

        if ($hasRole('gdf.treasury')) {
            $options[] = ['key' => 'treasury', 'label' => 'Tesorería', 'desc' => 'Aprueba y gestiona pagos.', 'icon' => 'bi-cash-coin', 'areas' => []];
        }

        // Instructor: áreas desde person_area_budget_assignments con GRACE para entrar
        if ($hasRole('gdf.instructor')) {
            $areas = $this->areasForUserFromAssignmentsEnter((int)$user->id, $graceDays);

            // OJO: si no hay áreas, NO agregamos el rol (para que el gateway no muestre una opción muerta)
            if (!empty($areas)) {
                $options[] = [
                    'key'   => 'official',
                    'label' => 'Instructor / Funcionario',
                    'desc'  => 'Crea solicitudes por área.',
                    'icon'  => 'bi-person-badge',
                    'areas' => $areas,
                ];
            }
        }

        // Coordinación / Apoyo por rol real
        $hasAcademicCoord  = $hasRole('gdf.academic_coordinator');
        $hasCampesenaCoord = $hasRole('gdf.campesena_coordinator');
        $hasAcademicSupp   = $hasRole('gdf.academic_support');
        $hasCampesenaSupp  = $hasRole('gdf.campesena_support');

        if ($hasAcademicCoord || $hasCampesenaCoord) {
            $areas = [];
            if ($hasAcademicCoord)  $areas[] = 'academic';
            if ($hasCampesenaCoord) $areas[] = 'campesena';

            $options[] = [
                'key'   => 'coord',
                'label' => 'Coordinación',
                'desc'  => 'Gestiona solicitudes por área.',
                'icon'  => 'bi-mortarboard',
                'areas' => $areas,
            ];
        }

        if ($hasAcademicSupp || $hasCampesenaSupp) {
            $areas = [];
            if ($hasAcademicSupp)  $areas[] = 'academic';
            if ($hasCampesenaSupp) $areas[] = 'campesena';

            $options[] = [
                'key'   => 'support',
                'label' => 'Apoyo',
                'desc'  => 'Valida soportes por área.',
                'icon'  => 'bi-person-check',
                'areas' => $areas,
            ];
        }

        return $options;
    }

    /* ============================================================
     * Resolver rol + área (autoselección si solo 1)
     * ============================================================ */
    private function resolveRoleAndArea(array $cfg, ?string $selectedArea): array
    {
        $role = $cfg['key'] ?? null;
        if (!$role) return [null, null];

        $areas = $cfg['areas'] ?? [];

        if (!$this->roleRequiresArea($role)) {
            return [$role, null];
        }

        if ($selectedArea) {
            return [$role, $selectedArea];
        }

        if (count($areas) === 1) {
            return [$role, $areas[0]];
        }

        return [$role, null];
    }

    private function roleRequiresArea(string $role): bool
    {
        return in_array($role, ['official', 'coord', 'support'], true);
    }

    /* ============================================================
     * Áreas para ENTRAR (con graceDays)
     * - end_date no vencida
     * - start_date <= hoy + graceDays  (o NULL)
     * ============================================================ */
    private function areasForUserFromAssignmentsEnter(int $userId, int $graceDays = 8): array
    {
        $personId = DB::table('users')->where('id', $userId)->value('person_id');
        if (!$personId) return [];

        $today      = now()->toDateString();
        $graceStart = now()->addDays($graceDays)->toDateString();

        $areaIds = DB::table('person_area_budget_assignments as paba')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            // no vencidas
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
            })
            // pre-vigencia permitida
            ->where(function ($q) use ($graceStart) {
                $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $graceStart);
            })
            ->distinct()
            ->pluck('paba.area_id')
            ->map(fn($v) => (int)$v)
            ->all();

        return $this->mapAreaIdsToKeys($areaIds);
    }

    /* ============================================================
     * Áreas ACTIVAS HOY (sin grace) -> útil para bloquear CREATE/STORE
     * ============================================================ */
    private function areasForUserFromAssignmentsActiveToday(int $userId): array
    {
        $personId = DB::table('users')->where('id', $userId)->value('person_id');
        if (!$personId) return [];

        $today = now()->toDateString();

        $areaIds = DB::table('person_area_budget_assignments as paba')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
            })
            ->distinct()
            ->pluck('paba.area_id')
            ->map(fn($v) => (int)$v)
            ->all();

        return $this->mapAreaIdsToKeys($areaIds);
    }

    private function mapAreaIdsToKeys(array $areaIds): array
    {
        $academicIds  = array_map('intval', (array) config('gdf.area_groups.academic', []));
        $campesenaIds = array_map('intval', (array) config('gdf.area_groups.campesena', []));

        $areas = [];
        foreach ($areaIds as $id) {
            if (in_array($id, $academicIds, true))  $areas[] = 'academic';
            if (in_array($id, $campesenaIds, true)) $areas[] = 'campesena';
        }

        return array_values(array_unique($areas));
    }

    /* ============================================================
 * PÁGINAS PÚBLICAS (INSPECCIÓN)
 * ============================================================ */


    public function Developers()
    {
        return view('gdf::developers');
    }

    public function About()
    {
        return view('gdf::about');
    }

    public function Tech()
    {
        return view('gdf::tech');
    }
}
