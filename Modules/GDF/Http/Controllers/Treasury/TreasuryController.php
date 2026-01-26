<?php

namespace Modules\GDF\Http\Controllers\Treasury;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// AJUSTA estos imports a tus entidades reales:
use Modules\GDF\Entities\GdfRequest;   // Tabla de solicitudes GDF (la que sea)
use Modules\GDF\Entities\BudgetItem;   // Rubros
use Modules\GDF\Entities\GdfLog;       // Logs (opcional)

class TreasuryController extends Controller
{
    /**
     * GET /gdf/treasury/review?group=academic|campesena&q=...
     */
    public function index(Request $request)
    {
        $this->authorizeTreasury();

        $group = $request->get('group', 'academic');
        if (!in_array($group, ['academic', 'campesena'], true)) $group = 'academic';

        $q = trim((string) $request->get('q', ''));

        // Mapeo de areas para tabs fijos (IDs), en config/gdf.php
        $areaIds = config("gdf.area_groups.$group", []);
        if (!is_array($areaIds)) $areaIds = [];

        $query = GdfRequest::query()
            // AJUSTA: columna real para area o rubro
            ->when(!empty($areaIds), fn($qq) => $qq->whereIn('area_id', $areaIds))
            // Estados “pendientes tesorería” (ajusta a tu enum real)
            ->whereIn('status', ['submitted', 'in_treasury_review']);

        if ($q !== '') {
            $query->where(function($sub) use ($q) {
                // AJUSTA: campos reales
                $sub->where('code', 'like', "%{$q}%")
                    ->orWhere('applicant_document', 'like', "%{$q}%")
                    ->orWhere('applicant_name', 'like', "%{$q}%");
            });
        }

        $requests = $query->latest('created_at')->paginate(15)->appends([
            'group' => $group,
            'q'     => $q,
        ]);

        // Disponibilidad por solicitud (budget_item_id)
        $availability = [];
        foreach ($requests as $r) {
            $availability[$r->id] = $this->getBudgetAvailability($r->budget_item_id);
        }

        return view('gdf::treasury.review.index', [
            'group'        => $group,
            'q'            => $q,
            'requests'     => $requests,
            'availability' => $availability,
        ]);
    }

    /**
     * POST /gdf/treasury/review/{id}/approve
     * Aprueba recursos y pasa a Coordinador.
     */
    public function approve(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:1500'],
        ]);

        $r = GdfRequest::findOrFail($id);

        if (!in_array($r->status, ['submitted', 'in_treasury_review'], true)) {
            return back()->with('error', 'La solicitud no está en revisión por Tesorería.');
        }

        $avail = $this->getBudgetAvailability($r->budget_item_id);
        if (($avail['available'] ?? 0) < (float) $r->total_amount) { // AJUSTA total_amount al campo real
            return back()->with('error', 'No hay recursos suficientes para aprobar.');
        }

        DB::transaction(function() use ($r, $data) {

            $r->status = 'approved_by_treasury'; // AJUSTA a tu estado real
            $r->treasury_reviewed_by = Auth::id();
            $r->treasury_reviewed_at = now();
            $r->treasury_comment = $data['comment'] ?? null;
            // Bloqueo de edición (si manejas flags)
            // $r->is_locked = true;

            $r->save();

            // Log opcional
            if (class_exists(GdfLog::class)) {
                GdfLog::create([
                    'gdf_request_id' => $r->id,
                    'user_id'        => Auth::id(),
                    'action'         => 'treasury_approved',
                    'description'    => $data['comment'] ?? 'Aprobado por Tesorería (recursos).',
                ]);
            }
        });

        return back()->with('success', 'Aprobado por Tesorería y enviado a Coordinación.');
    }

    /**
     * POST /gdf/treasury/review/{id}/return
     * Devuelve a apoyo o a la persona (editable por quien corresponda).
     */
    public function return(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:1500'],
        ]);

        $r = GdfRequest::findOrFail($id);

        if (!in_array($r->status, ['submitted', 'in_treasury_review'], true)) {
            return back()->with('error', 'La solicitud no está en revisión por Tesorería.');
        }

        DB::transaction(function() use ($r, $data) {

            $r->status = 'returned';                 // AJUSTA a tu estado real
            $r->returned_target = $data['target'];   // support|applicant (campo recomendado)
            $r->treasury_reviewed_by = Auth::id();
            $r->treasury_reviewed_at = now();
            $r->treasury_comment = $data['comment'];

            // Control de edición según target (si manejas flags)
            // $r->editable_by_support   = ($data['target'] === 'support');
            // $r->editable_by_applicant = ($data['target'] === 'applicant');

            $r->save();

            if (class_exists(GdfLog::class)) {
                GdfLog::create([
                    'gdf_request_id' => $r->id,
                    'user_id'        => Auth::id(),
                    'action'         => $data['target'] === 'support' ? 'treasury_returned_support' : 'treasury_returned_applicant',
                    'description'    => $data['comment'],
                ]);
            }
        });

        return back()->with('success', 'Solicitud devuelta correctamente.');
    }

    /**
     * POST /gdf/treasury/review/{id}/close
     * Cierra solicitud (no editable; deben crear otra si aplica).
     */
    public function close(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:1500'],
        ]);

        $r = GdfRequest::findOrFail($id);

        if (!in_array($r->status, ['submitted', 'in_treasury_review'], true)) {
            return back()->with('error', 'La solicitud no está en revisión por Tesorería.');
        }

        DB::transaction(function() use ($r, $data) {

            $r->status = 'closed_by_treasury'; // AJUSTA a tu estado real
            $r->treasury_reviewed_by = Auth::id();
            $r->treasury_reviewed_at = now();
            $r->treasury_comment = $data['comment'];

            // Bloqueo total (si manejas flags)
            // $r->is_locked = true;

            $r->save();

            if (class_exists(GdfLog::class)) {
                GdfLog::create([
                    'gdf_request_id' => $r->id,
                    'user_id'        => Auth::id(),
                    'action'         => 'treasury_closed',
                    'description'    => $data['comment'],
                ]);
            }
        });

        return back()->with('success', 'Solicitud cerrada por Tesorería.');
    }

    /* ================= Helpers ================= */

    private function authorizeTreasury(): void
    {
        $isTreasury = function_exists('checkRol') ? checkRol('gdf.treasury') : false;
        if (!$isTreasury) abort(403);
    }

    private function getBudgetAvailability(?int $budgetItemId): array
    {
        if (!$budgetItemId) {
            return ['name' => 'Sin rubro', 'allocated' => 0, 'executed' => 0, 'available' => 0];
        }

        $item = BudgetItem::find($budgetItemId);
        if (!$item) {
            return ['name' => 'Rubro no encontrado', 'allocated' => 0, 'executed' => 0, 'available' => 0];
        }

        // AJUSTA a columnas reales de budget_items
        $allocated = (float) ($item->allocated_amount ?? 0);
        $executed  = (float) ($item->executed_amount ?? 0);

        return [
            'name'      => (string)($item->name ?? 'Rubro'),
            'allocated' => $allocated,
            'executed'  => $executed,
            'available' => max(0, $allocated - $executed),
        ];
    }
}
