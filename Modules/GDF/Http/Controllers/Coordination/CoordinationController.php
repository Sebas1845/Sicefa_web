<?php

namespace Modules\GDF\Http\Controllers\Coordination;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelReview;

class CoordinationController extends Controller
{
    /**
     * GET /gdf/academic/review
     * GET /gdf/campesena/review
     *
     * Renderiza la bandeja de Coordinación usando UNA sola vista:
     * Modules/GDF/Resources/views/coordination/dashboard.blade.php
     */
    public function index(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request); // academic|campesena
        $this->authorizeByArea($areaKey);

        $q   = trim((string) $request->get('q', ''));
        $tab = $request->get('tab', 'gdf'); // gdf|sitrav
        if (!in_array($tab, ['gdf', 'sitrav'], true)) $tab = 'gdf';

        $areaIds = (array) config("gdf.area_groups.$areaKey", []);
        if (empty($areaIds)) {
            abort(403, "Área {$areaKey} no configurada en gdf.area_groups.$areaKey");
        }

        // Rubros permitidos por grupo (si aplica)
        $budgetItemIds = (array) config("gdf.budget_groups.$areaKey", []);

        /* ===========================
     | 1) QUERY GDF (SIEMPRE)
     =========================== */
        $gdfQuery = TravelRequest::query()
            ->where('status', 'approved_by_treasury')
            ->whereIn('area_id', $areaIds)
            ->when(!empty($budgetItemIds), fn($qq) => $qq->whereIn('budget_item_id', $budgetItemIds));

        if ($q !== '') {
            $gdfQuery->where(function ($sub) use ($q) {
                $sub->where('origin', 'like', "%{$q}%")
                    ->orWhere('destination', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        // Conteo para badge (sin paginar)
        $gdfCount = (clone $gdfQuery)->count();

        /* ===========================
     | 2) QUERY SITRAV (PLACEHOLDER / REAL)
     =========================== */

        // Conteo / lista por defecto
        $sitravCount = 0;
        $sitravRequests = collect(); // por si no está conectado

        /**
         * OPCIÓN A (YA TIENES MODEL SITRAV):
         * Ej: ProgramRequest::query()...
         *
         * IMPORTANTE:
         * - Ajusta nombres de columnas/estados a tu SITRAV real.
         * - Lo ideal: mapear SITRAV a "area_id" y "budget_item_id" (si ya lo guardas).
         */
    /*
    $sitravQuery = \Modules\SITRAV\Entities\ProgramRequest::query()
        ->where('status', 'approved_by_treasury') // AJUSTAR
        ->whereIn('area_id', $areaIds)
        ->when(!empty($budgetItemIds), fn($qq) => $qq->whereIn('budget_item_id', $budgetItemIds));

    if ($q !== '') {
        $sitravQuery->where(function ($sub) use ($q) {
            $sub->where('origin', 'like', "%{$q}%")
                ->orWhere('destination', 'like', "%{$q}%")
                ->orWhere('id', $q)
                ->orWhere('document_number', 'like', "%{$q}%");
        });
    }

    $sitravCount = (clone $sitravQuery)->count();
    */

        /**
         * OPCIÓN B (AÚN NO HAY MODEL / NO ESTÁ LISTO):
         * - Deja $sitravRequests vacío, pero ya queda el tab montado.
         */
        // $sitravCount = 0;
        // $sitravRequests = collect();

        /* ===========================
     | 3) PAGINACIÓN POR TAB ACTIVO
     =========================== */
        if ($tab === 'gdf') {
            $requests = $gdfQuery
                ->latest('updated_at')
                ->paginate(15)
                ->appends(['q' => $q, 'tab' => $tab]);

            // En este tab, SITRAV no pagina (solo badge y placeholder)
            // $sitravRequests se queda como collect()
        } else {
            // Si SITRAV está conectado (Opción A), pagínalo aquí
            /*
        $sitravRequests = $sitravQuery
            ->latest('updated_at')
            ->paginate(15)
            ->appends(['q' => $q, 'tab' => $tab]);
        */

            // Mientras tanto (Opción B), para que no reviente la vista:
            $sitravRequests = $sitravRequests; // collect()

            // GDF queda como collection vacía en este tab (para no romper la vista)
            $requests = collect();
        }

        $routePrefix = $areaKey === 'academic' ? 'gdf.academic' : 'gdf.campesena';
        $title = $areaKey === 'academic' ? 'Coordinación Académica' : 'Coordinación Campesena';

        return view('gdf::coordination.dashboard', [
            'areaKey'        => $areaKey,
            'routePrefix'    => $routePrefix,
            'title'          => $title,
            'q'              => $q,
            'tab'            => $tab,

            // GDF
            'requests'       => $requests,
            'gdfCount'       => $gdfCount,

            // SITRAV
            'sitravRequests' => $sitravRequests,
            'sitravCount'    => $sitravCount,
        ]);
    }


    /**
     * POST /gdf/academic/review/{id}/approve
     * POST /gdf/campesena/review/{id}/approve
     */
    public function approve(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $r = TravelRequest::findOrFail($id);

        if ($r->status !== 'approved_by_treasury') {
            return back()->with('error', 'La solicitud no está lista para revisión de Coordinación.');
        }

        $this->ensureRequestMatchesArea($r, $areaKey);

        DB::transaction(function () use ($r, $data) {
            $r->status = 'approved_by_coordinator';
            $r->coordinator_reviewed_by = Auth::id();
            $r->coordinator_reviewed_at = now();
            $r->coordinator_comment = $data['comment'] ?? null;
            $r->save();

            TravelReview::create([
                'gdf_request_id' => $r->id,
                'reviewer_id'    => Auth::id(),
                'role'           => 'coordination',
                'action'         => 'approved',
                'target'         => null,
                'comments'       => $data['comment'] ?? null,
            ]);
        });

        return back()->with('success', 'Aprobado por Coordinación.');
    }

    /**
     * POST /gdf/academic/review/{id}/return
     * POST /gdf/campesena/review/{id}/return
     */
    public function return(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:1500'],
        ]);

        $r = TravelRequest::findOrFail($id);

        if ($r->status !== 'approved_by_treasury') {
            return back()->with('error', 'La solicitud no está lista para revisión de Coordinación.');
        }

        $this->ensureRequestMatchesArea($r, $areaKey);

        DB::transaction(function () use ($r, $data) {
            $r->status = 'returned';
            $r->returned_target = $data['target'];
            $r->coordinator_reviewed_by = Auth::id();
            $r->coordinator_reviewed_at = now();
            $r->coordinator_comment = $data['comment'];
            $r->is_locked = false; // desbloquea para corrección
            $r->save();

            TravelReview::create([
                'gdf_request_id' => $r->id,
                'reviewer_id'    => Auth::id(),
                'role'           => 'coordination',
                'action'         => 'returned',
                'target'         => $data['target'],
                'comments'       => $data['comment'],
            ]);
        });

        return back()->with('success', 'Solicitud devuelta correctamente.');
    }

    /**
     * POST /gdf/academic/review/{id}/reject
     * POST /gdf/campesena/review/{id}/reject
     */
    public function reject(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1500'],
        ]);

        $r = TravelRequest::findOrFail($id);

        if ($r->status !== 'approved_by_treasury') {
            return back()->with('error', 'La solicitud no está lista para revisión de Coordinación.');
        }

        $this->ensureRequestMatchesArea($r, $areaKey);

        DB::transaction(function () use ($r, $data) {
            $r->status = 'rejected';
            $r->coordinator_reviewed_by = Auth::id();
            $r->coordinator_reviewed_at = now();
            $r->coordinator_comment = $data['comment'];
            $r->is_locked = true;
            $r->save();

            TravelReview::create([
                'gdf_request_id' => $r->id,
                'reviewer_id'    => Auth::id(),
                'role'           => 'coordination',
                'action'         => 'rejected',
                'target'         => null,
                'comments'       => $data['comment'],
            ]);
        });

        return back()->with('success', 'Solicitud rechazada por Coordinación.');
    }

    /* ===================== Helpers ===================== */

    private function authorizeAcademicOrSupport(): void
    {
        if (!function_exists('checkRol')) abort(403);
        if (!checkRol('gdf.academic_coordinator') && !checkRol('gdf.academic_support')) abort(403);
    }

    private function authorizeCampesenaOrSupport(): void
    {
        if (!function_exists('checkRol')) abort(403);
        if (!checkRol('gdf.campesena_coordinator') && !checkRol('gdf.campesena_support')) abort(403);
    }

    private function authorizeByArea(string $areaKey): void
    {
        if ($areaKey === 'campesena') $this->authorizeCampesenaOrSupport();
        else $this->authorizeAcademicOrSupport();
    }

    private function areaKeyFromPath(Request $request): string
    {
        return str_contains($request->path(), 'gdf/campesena') ? 'campesena' : 'academic';
    }

    private function ensureRequestMatchesArea(TravelRequest $r, string $areaKey): void
    {
        $areaIds = (array) config("gdf.area_groups.$areaKey", []);
        if (!in_array((int)$r->area_id, array_map('intval', $areaIds), true)) {
            abort(403, 'La solicitud no pertenece al área actual.');
        }
    }
}
