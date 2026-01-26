<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controller;
use Modules\GDF\Entities\MotorcycleAssignment;

class MotorcycleController extends BaseOfficialController
{
    public function index(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $personId = (int) (Auth::user()->person_id ?? 0);
        if (!$personId) abort(403, 'User has no person_id.');

        $areaIds = $this->areaIdsByKey($ctx['area']);

        $active = MotorcycleAssignment::with(['motorcycle', 'area', 'budgetItem'])
            ->where('person_id', $personId)
            ->whereIn('area_id', $areaIds)
            ->whereIn('status', ['approved','delivered'])
            ->latest()
            ->first();

        $pending = MotorcycleAssignment::with(['area', 'budgetItem'])
            ->where('person_id', $personId)
            ->whereIn('area_id', $areaIds)
            ->where('status', 'pending')
            ->latest()
            ->first();

        return view('gdf::instructor.motorcycle.index', [
            'ctx' => $ctx,
            'active' => $active,
            'pending' => $pending,
            'areaIds' => $areaIds,
        ]);
    }

    public function storeRequest(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $personId = (int) (Auth::user()->person_id ?? 0);
        if (!$personId) abort(403);

        $areaIds = $this->areaIdsByKey($ctx['area']);

        $hasBlocking = MotorcycleAssignment::where('person_id', $personId)
            ->whereIn('area_id', $areaIds)
            ->whereIn('status', ['pending','approved','delivered'])
            ->exists();

        if ($hasBlocking) {
            return back()->with('error', 'Ya tienes una solicitud/asignación de moto pendiente o activa.');
        }

        $data = $request->validate([
            'area_id' => ['required', 'integer', 'in:' . implode(',', $areaIds)],
            'budget_item_id' => ['nullable', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        MotorcycleAssignment::create([
            'motorcycle_id' => null,
            'person_id' => $personId,
            'area_id' => (int) $data['area_id'],
            'budget_item_id' => $data['budget_item_id'] ?? null,
            'status' => 'pending',
            'requested_by' => (int) Auth::id(),
            'observations_out' => $this->buildRequestMetaNotes($data),
        ]);

        return redirect()->route('gdf.instructor.motorcycle.index')
            ->with('success', 'Solicitud de moto registrada.');
    }

    public function cancel(Request $request, MotorcycleAssignment $assignment)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $personId = (int) (Auth::user()->person_id ?? 0);
        if (!$personId) abort(403);

        $areaIds = $this->areaIdsByKey($ctx['area']);

        if ((int)$assignment->person_id !== $personId || !in_array((int)$assignment->area_id, $areaIds, true)) {
            abort(403);
        }

        if ($assignment->status !== 'pending') {
            return back()->with('error', 'Solo puedes cancelar solicitudes pendientes.');
        }

        $assignment->status = 'cancelled';
        $assignment->save();

        return redirect()->route('gdf.instructor.motorcycle.index')
            ->with('success', 'Solicitud cancelada.');
    }

    /**
     * Endpoint simple (opcional) para UI: ¿tiene moto activa?
     */
    public function active(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $personId = (int) (Auth::user()->person_id ?? 0);
        if (!$personId) abort(403);

        $areaIds = $this->areaIdsByKey($ctx['area']);

        $active = DB::table('motorcycle_assignments')
            ->where('person_id', $personId)
            ->whereIn('area_id', $areaIds)
            ->whereIn('status', ['approved','delivered'])
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'has_moto' => (bool) $active,
            'assignment_id' => $active->id ?? null,
            'motorcycle_id' => $active->motorcycle_id ?? null,
        ]);
    }

    private function buildRequestMetaNotes(array $data): string
    {
        $parts = [];
        if (!empty($data['start_date'])) $parts[] = 'Inicio solicitado: '.$data['start_date'];
        if (!empty($data['end_date'])) $parts[] = 'Fin solicitado: '.$data['end_date'];
        $parts[] = 'Motivo: '.$data['reason'];
        return implode("\n", $parts);
    }
}
