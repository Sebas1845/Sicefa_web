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
        $user      = auth()->user();
        $routeName = optional($request->route())->getName();

        /**
         * ============================================================
         * 0) Debe estar autenticado si el middleware se usa en rutas auth
         * ============================================================
         */
        if (!$user) {
            // Si por algún motivo lo usan sin auth middleware
            return $this->safeRedirect('login', 'Debes iniciar sesión para continuar.', 'warning');
        }

        // ============================================================
        // 0.5) BYPASS: Admin global puede entrar a GDF sin roles GDF
        // Ajusta los slugs a tus roles reales del sistema (no GDF)
        // ============================================================
        if (function_exists('checkRol')) {
            $isGlobalAdmin = checkRol('superadmin') || checkRol('admin'); // <-- AJUSTA ESTO

            if ($isGlobalAdmin) {
                return $next($request);
            }
        }

        /**
         * ============================================================
         * 1) Parseo parámetros
         * Soporta:
         *  - role=gdf.instructor|gdf.treasury
         *  - ctx=official|treasury
         *  - area=academic|campesena
         *  - legacy: "gdf.instructor|gdf.treasury"
         * ============================================================
         */
        $parsed = [];
        $raw = array_values(array_filter(array_map('trim', $params), fn($v) => $v !== ''));

        foreach ($raw as $p) {
            if (str_contains($p, '=')) {
                [$k, $v] = array_pad(explode('=', $p, 2), 2, null);
                $k = trim((string) $k);
                $v = trim((string) $v);
                if ($k !== '' && $v !== '') {
                    $parsed[$k] = $v;
                }
            } else {
                if (!isset($parsed['role'])) {
                    $parsed['role'] = $p; // legacy
                }
            }
        }

        /**
         * ============================================================
         * 2) Validar rol real (checkRol)
         * ============================================================
         */
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

        /**
         * ============================================================
         * 3) Permitir entrada anticipada por asignaciones (grace period)
         * ============================================================
         * Solo aplica a roles operativos por área:
         * - gdf.instructor
         * - gdf.academic_coordinator / gdf.campesena_coordinator
         * - gdf.academic_support / gdf.campesena_support
         *
         * Regla:
         * - Puede entrar si end_date no venció
         * - Y start_date <= hoy + 8 días  (o NULL)
         */
        if (function_exists('checkRol')) {
            $isInstructor = checkRol('gdf.instructor');

            $isCoordOrSupport =
                checkRol('gdf.academic_coordinator') ||
                checkRol('gdf.campesena_coordinator') ||
                checkRol('gdf.academic_support') ||
                checkRol('gdf.campesena_support');

            if ($isInstructor || $isCoordOrSupport) {
                $canEnter = $this->canEnterByAssignments((int) $user->id, 8);

                if (!$canEnter) {
                    // Mensaje más claro para tu caso
                    return $this->safeRedirect(
                        'gdf.index',
                        'No tienes asignaciones vigentes (o dentro del plazo de entrada) para operar en GDF. Verifica fechas de inicio/fin o si tu asignación está activa.'
                    );
                }
            }
        }

        /**
         * ============================================================
         * 4) Validar contexto (session) si la ruta lo exige
         * ============================================================
         */
        $requiresCtx = isset($parsed['ctx']) || isset($parsed['area']);

        if ($requiresCtx) {
            $ctx     = session('gdf_context', []);
            $ctxRole = $ctx['role'] ?? null;
            $ctxArea = $ctx['area'] ?? null;

            if (!$ctxRole) {
                return $this->safeRedirect('gdf.gateway', 'Selecciona un contexto para continuar.', 'warning');
            }

            if (isset($parsed['ctx'])) {
                $allowedCtx = array_values(array_filter(array_map('trim', explode('|', (string) $parsed['ctx']))));
                if (!in_array($ctxRole, $allowedCtx, true)) {
                    return $this->safeRedirect('gdf.gateway', 'Contexto inválido para esta sección.');
                }
            }

            if (isset($parsed['area'])) {
                if ($ctxArea !== $parsed['area']) {
                    return $this->safeRedirect('gdf.gateway', 'Área incorrecta. Cambia al área requerida para ingresar.');
                }
            }
        }

        return $next($request);
    }

    /**
     * Redirect seguro: si la ruta no existe, vuelve a gdf.index o /
     */
    private function safeRedirect(string $route, string $msg, string $level = 'error')
    {
        $current = optional(request()->route())->getName();

        // Evita loops: si ya estamos en la misma ruta, mandamos a index
        if ($current === $route) {
            $route = 'gdf.index';
        }

        $fallback = Route::has($route) ? $route : (Route::has('gdf.index') ? 'gdf.index' : null);

        if ($fallback) {
            return redirect()->route($fallback)->with($level, $msg);
        }

        return redirect('/')->with($level, $msg);
    }

    /**
     * Permite entrada anticipada a GDF si:
     * - existe asignación activa (is_active=1)
     * - end_date es NULL o >= hoy
     * - start_date es NULL o <= hoy + graceDays
     */
    private function canEnterByAssignments(int $userId, int $graceDays = 8): bool
    {
        $personId = DB::table('users')->where('id', $userId)->value('person_id');
        if (!$personId) return false;

        $today      = now()->toDateString();
        $graceStart = now()->addDays($graceDays)->toDateString();

        return DB::table('person_area_budget_assignments as paba')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            // No vencidas
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')
                    ->orWhere('paba.end_date', '>=', $today);
            })
            // Pre-vigencia permitida
            ->where(function ($q) use ($graceStart) {
                $q->whereNull('paba.start_date')
                    ->orWhere('paba.start_date', '<=', $graceStart);
            })
            ->exists();
    }
}
