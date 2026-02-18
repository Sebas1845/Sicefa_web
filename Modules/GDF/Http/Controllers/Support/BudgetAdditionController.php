<?php

namespace Modules\GDF\Http\Controllers\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\Budget;
use Modules\GDF\Entities\BudgetAddition;
use Modules\GDF\Entities\BudgetMovement;

class BudgetAdditionController extends Controller
{
    private function userOr403(): void
    {
        if (!auth()->check()) abort(403);
    }

    private function canSupportDoAdditions(): bool
    {
        return function_exists('checkRol')
            ? (checkRol('gdf.academic_support') || checkRol('gdf.campesena_support') || checkRol('gdf.superadmin'))
            : false;
    }

    private function resolveAreaIdFromSession(): ?int
    {
        $areaKey = session('gdf.area_key'); // 'academic'|'campesena'|null
        if (!$areaKey) return null;

        $row = DB::table('areas')
            ->where('key', $areaKey)
            ->orWhere('slug', $areaKey)
            ->first();

        return $row?->id ? (int)$row->id : null;
    }

    /**
     * POST /.../budgets/{budget}/additions
     * Aplica de una vez: crea budget_additions + suma saldo + crea budget_movements
     */
    public function store(Request $request, Budget $budget)
    {
        $this->userOr403();
        if (!$this->canSupportDoAdditions()) abort(403);

        $data = $request->validate([
            'amount'        => ['required','numeric','min:0.01'],
            'justification' => ['nullable','string','max:5000'],
        ]);

        $amount = (float) $data['amount'];
        $userId = (int) Auth::id();

        // ✅ Área del movimiento:
        // 1) intenta session, 2) si no hay, usa la del budget (nunca null)
        $areaId = $this->resolveAreaIdFromSession();
        if (!$areaId) {
            $areaId = (int) ($budget->area_id ?? 0);
        }
        if ($areaId <= 0) {
            // por seguridad: si no existe area en budget, aborta para no dejar null
            abort(422, 'No se pudo resolver el área para la adición.');
        }

        DB::transaction(function () use ($budget, $amount, $data, $userId, $areaId) {

            // 🔒 Lock del budget
            $b = Budget::where('id', $budget->id)->lockForUpdate()->firstOrFail();

            // 1) Crear adición ya aprobada
            $addition = new BudgetAddition();
            $addition->budget_id       = $b->id;
            $addition->amount          = $amount;
            $addition->justification   = $data['justification'] ?? null;

            $addition->created_by      = $userId;
            $addition->created_area_id = $areaId;

            // ✅ Aprobada de una vez
            $addition->approved_by = $userId;
            $addition->approved_at = now();

            $addition->save(); // <-- importante: primero guardar para tener ID

            // 2) Sumar al saldo del budget
            $b->current_amount = (float)$b->current_amount + $amount;
            $b->save();

            // 3) Crear movimiento (NO null area_id)
            BudgetMovement::create([
                'budget_id'         => $b->id,
                'area_id'           => $areaId,
                'travel_request_id' => null,
                'module'            => 'gdf',
                'type'              => 'addition',
                'amount'            => $amount,
                'description'       => trim('Adición aplicada. ' . ($addition->justification ?? '')),
                'created_by'        => $userId,
                'source_type'       => 'budget_additions',
                'source_id'         => $addition->id,
                'created_at'        => now(),
            ]);
        });

        return back()->with('success', 'Adición aplicada: se actualizó el saldo y se registró el movimiento.');
    }
}
