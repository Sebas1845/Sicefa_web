<?php

namespace Modules\GDF\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class GdfRoleGuard
{
    public function handle(Request $request, Closure $next, ...$params)
    {
        $user = auth()->user();

        if (!$user) {
            return $this->safeRedirect('login', 'Debes iniciar sesión para continuar.', 'warning');
        }


        if ($this->isDocumentRoute($request)) {
            // Verificar que tenga al menos rol de support
            if (function_exists('checkRol')) {
                if (checkRol('gdf.academic_support') || checkRol('gdf.campesena_support')) {
                    return $next($request);
                }
            }
        }

        if (function_exists('checkRol')) {
            if (checkRol('superadmin') || checkRol('admin')) {
                return $next($request);
            }
        }

        $parsed = $this->parseParams($params);

        if (isset($parsed['role'])) {
            if (!function_exists('checkRol')) {
                return $this->safeRedirect('gdf.index', 'Validador de roles no disponible (checkRol).');
            }

            $allowedRoles = array_values(array_filter(array_map('trim', explode('|', (string) $parsed['role']))));
            $hasRole = false;

            foreach ($allowedRoles as $slug) {
                if ($slug !== '' && checkRol($slug)) {
                    $hasRole = true;
                    break;
                }
            }

            if (!$hasRole) {
                return $this->safeRedirect('gdf.index', 'No tienes permisos para acceder a esta sección.');
            }
        }


        $graceDays = isset($parsed['grace']) ? (int) $parsed['grace'] : 8;

        if (function_exists('checkRol') && checkRol('gdf.instructor')) {
            if (!$this->canEnterByAssignments($user, $graceDays)) {
                return $this->safeRedirect(
                    'gdf.index',
                    'No tienes asignación vigente (o dentro del plazo de entrada) para operar como Instructor en GDF.',
                    'error'
                );
            }
        }

        $requiresCtx = isset($parsed['ctx']) || isset($parsed['area']);

        if ($requiresCtx) {
            $ctx     = session('gdf_context', []);
            $ctxRole = $ctx['role'] ?? null;
            $ctxArea = $ctx['area'] ?? null;


            if (!$ctxRole) {
                $auto = $this->autoSetContextIfPossible($user, $parsed, $graceDays);

                if (!$auto) {
                    return $this->safeRedirect('gdf.gateway', 'Selecciona un contexto para continuar.', 'warning');
                }

                $ctx     = session('gdf_context', []);
                $ctxRole = $ctx['role'] ?? null;
                $ctxArea = $ctx['area'] ?? null;
            }

            // ctx=...
            if (isset($parsed['ctx'])) {
                $allowedCtx = array_values(array_filter(array_map('trim', explode('|', (string) $parsed['ctx']))));

                if (!in_array((string) $ctxRole, $allowedCtx, true)) {
                    return $this->safeRedirect('gdf.gateway', 'Contexto inválido para esta sección.', 'warning');
                }

                if (!$this->userHasRoleForContext((string) $ctxRole)) {
                    return $this->safeRedirect('gdf.gateway', 'No tienes el rol necesario para este contexto.', 'error');
                }
            }

            if (isset($parsed['area'])) {
                if ((string) $ctxArea !== (string) $parsed['area']) {
                    return $this->safeRedirect('gdf.gateway', 'Área incorrecta. Cambia al área requerida para ingresar.', 'warning');
                }
            }
        }

        return $next($request);
    }

    /**
     */
    /**
     * Verifica si la ruta actual es una ruta de documentos que debe permitirse
     */
    private function isDocumentRoute(Request $request): bool
    {
        $path = $request->path();

        $documentPatterns = [
            // SUPPORT
            'gdf/support/academica/requests/*/documents',
            'gdf/support/campesena/requests/*/documents',
            'gdf/support/academica/documents/*/download',
            'gdf/support/campesena/documents/*/download',
            'gdf/support/academica/documents/*/download-sigac',
            'gdf/support/campesena/documents/*/download-sigac',
            'gdf/support/academica/documents/*/preview',
            'gdf/support/campesena/documents/*/preview',
            'gdf/support/academica/documents/*/preview-sigac',
            'gdf/support/campesena/documents/*/preview-sigac',
            'gdf/support/academica/documents/*/review',
            'gdf/support/campesena/documents/*/review',

            // COORDINACIÓN - Rutas de revisión (show)
            'gdf/academic/review/*',
            'gdf/campesena/review/*',

            // ✅ NUEVAS RUTAS DE AUTORIZACIÓN (PDF)
            'gdf/authorization/*/view',
            'gdf/instructor/requests/*/authorization',

            // ✅ Storage público (IMPORTANTE)
            'storage/*',
            'storage/gdf/*',
            'storage/gdf/requests/*',
        ];

        foreach ($documentPatterns as $pattern) {
            $regex = '#^' . str_replace('*', '[^/]+', $pattern) . '$#';
            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }

    private function parseParams(array $params): array
    {
        $parsed = [];
        $raw = array_values(array_filter(array_map('trim', $params), fn($v) => $v !== ''));

        foreach ($raw as $p) {
            if (str_contains($p, '=')) {
                [$k, $v] = array_pad(explode('=', $p, 2), 2, null);
                $k = trim((string) $k);
                $v = trim((string) $v);
                if ($k !== '' && $v !== '') $parsed[$k] = $v;
            } else {
                if (!isset($parsed['role'])) $parsed['role'] = $p; // legacy
            }
        }

        return $parsed;
    }

    private function safeRedirect(string $route, string $msg, string $level = 'error')
    {
        $current = optional(request()->route())->getName();

        // Evita loop a la misma ruta
        if ($current === $route) {
            $route = 'gdf.index';
        }

        $fallback = Route::has($route) ? $route : (Route::has('gdf.index') ? 'gdf.index' : null);

        if ($fallback) {
            return redirect()->route($fallback)->with($level, $msg);
        }

        return redirect('/')->with($level, $msg);
    }

    private function resolvePersonId($user): ?int
    {
        if (!empty($user->person_id)) return (int) $user->person_id;

        if (method_exists($user, 'person')) {
            $pid = optional($user->person)->id;
            if ($pid) return (int) $pid;
        }

        try {
            $pid = DB::table('users')->where('id', (int) $user->id)->value('person_id');
            if ($pid) return (int) $pid;
        } catch (\Throwable $e) {
        }

        return null;
    }

    private function canEnterByAssignments($user, int $graceDays = 8): bool
    {
        $personId = $this->resolvePersonId($user);
        if (!$personId) return false;

        $today      = now()->toDateString();
        $graceStart = now()->addDays($graceDays)->toDateString();

        return DB::table('person_area_budget_assignments as paba')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')
                    ->orWhere('paba.end_date', '>=', $today);
            })
            ->where(function ($q) use ($graceStart) {
                $q->whereNull('paba.start_date')
                    ->orWhere('paba.start_date', '<=', $graceStart);
            })
            ->exists();
    }

    private function mapAreaIdsToKeys(array $areaIds): array
    {
        $academicIds  = array_map('intval', (array) config('gdf.area_groups.academic', []));
        $campesenaIds = array_map('intval', (array) config('gdf.area_groups.campesena', []));

        $areas = [];
        foreach ($areaIds as $id) {
            if (in_array((int) $id, $academicIds, true))  $areas[] = 'academic';
            if (in_array((int) $id, $campesenaIds, true)) $areas[] = 'campesena';
        }

        return array_values(array_unique($areas));
    }

    private function areasForUserFromAssignmentsEnter($user, int $graceDays = 8): array
    {
        $personId = $this->resolvePersonId($user);
        if (!$personId) return [];

        $today      = now()->toDateString();
        $graceStart = now()->addDays($graceDays)->toDateString();

        $areaIds = DB::table('person_area_budget_assignments as paba')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')->orWhere('paba.end_date', '>=', $today);
            })
            ->where(function ($q) use ($graceStart) {
                $q->whereNull('paba.start_date')->orWhere('paba.start_date', '<=', $graceStart);
            })
            ->distinct()
            ->pluck('paba.area_id')
            ->map(fn($v) => (int) $v)
            ->all();

        return $this->mapAreaIdsToKeys($areaIds);
    }

    private function autoSetContextIfPossible($user, array $parsed, int $graceDays = 8): bool
    {
        if (!function_exists('checkRol')) return false;

        $ctxCandidates = [];
        if (isset($parsed['ctx'])) {
            $ctxCandidates = array_values(array_filter(array_map('trim', explode('|', (string) $parsed['ctx']))));
        }

        // Instructor
        if (checkRol('gdf.instructor') && (empty($ctxCandidates) || in_array('official', $ctxCandidates, true))) {
            $areas = $this->areasForUserFromAssignmentsEnter($user, $graceDays);

            if (count($areas) === 1) {
                session(['gdf_context' => ['role' => 'official', 'area' => $areas[0]]]);
                return true;
            }
            return false;
        }

        // Coordinación
        if ((checkRol('gdf.academic_coordinator') || checkRol('gdf.campesena_coordinator'))
            && (empty($ctxCandidates) || in_array('coord', $ctxCandidates, true))
        ) {

            $areas = [];
            if (checkRol('gdf.academic_coordinator'))  $areas[] = 'academic';
            if (checkRol('gdf.campesena_coordinator')) $areas[] = 'campesena';

            if (count($areas) === 1) {
                session(['gdf_context' => ['role' => 'coord', 'area' => $areas[0]]]);
                return true;
            }
            return false;
        }

        // Apoyo
        if ((checkRol('gdf.academic_support') || checkRol('gdf.campesena_support'))
            && (empty($ctxCandidates) || in_array('support', $ctxCandidates, true))
        ) {

            $areas = [];
            if (checkRol('gdf.academic_support'))  $areas[] = 'academic';
            if (checkRol('gdf.campesena_support')) $areas[] = 'campesena';

            if (count($areas) === 1) {
                session(['gdf_context' => ['role' => 'support', 'area' => $areas[0]]]);
                return true;
            }
            return false;
        }

        // Tesorería / Subdirección
        if (checkRol('gdf.treasury') && (empty($ctxCandidates) || in_array('treasury', $ctxCandidates, true))) {
            session(['gdf_context' => ['role' => 'treasury', 'area' => null]]);
            return true;
        }

        if (checkRol('gdf.subdirection') && (empty($ctxCandidates) || in_array('subdirection', $ctxCandidates, true))) {
            session(['gdf_context' => ['role' => 'subdirection', 'area' => null]]);
            return true;
        }

        if (checkRol('gdf.superadmin') && (empty($ctxCandidates) || in_array('superadmin', $ctxCandidates, true))) {
            session(['gdf_context' => ['role' => 'superadmin', 'area' => null]]);
            return true;
        }

        return false;
    }

    private function userHasRoleForContext(string $ctx): bool
    {
        if (!function_exists('checkRol')) return true;

        return match ($ctx) {
            'official'     => checkRol('gdf.instructor'),
            'coord'        => checkRol('gdf.academic_coordinator') || checkRol('gdf.campesena_coordinator'),
            'support'      => checkRol('gdf.academic_support') || checkRol('gdf.campesena_support'),
            'treasury'     => checkRol('gdf.treasury'),
            'subdirection' => checkRol('gdf.subdirection'),
            'superadmin'   => checkRol('gdf.superadmin'),
            default        => false,
        };
    }
}
