<?php

namespace Modules\SIGAC\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SIGAC\Entities\EnvironmentIncident;

class EnvironmentIncidentController extends Controller
{
    /**
     * Listado general de incidentes (para coordinación/mantenimiento).
     */
    public function index(Request $request)
{
    $status = $request->input('status');

    $incidents = EnvironmentIncident::with(['environment', 'instructor', 'reporter'])
        ->when($status, fn($q) => $q->where('status', $status))
        ->orderByDesc('reported_at')
        ->paginate(20);

    // 🔥 IMPORTANTE
    $titlePage = "Novedades de Ambientes";

    return view('sigac::incidents.index', compact(
        'incidents',
        'status',
        'titlePage',
        'titleView'
    ));
}


    /**
     * Crear incidente (desde ronda o desde instructor).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'environment_id' => ['required', 'integer'],
            'schedule_id'    => ['nullable', 'integer'],
            'instructor_id'  => ['nullable', 'integer'],
            'source'         => ['required', 'in:RONDAS,INSTRUCTOR'],
            'type'           => ['required', 'in:LIMPIEZA,AIRE,EQUIPO,OTRO'],
            'description'    => ['nullable', 'string'],
        ]);

        $data['reported_by'] = auth()->id();
        $data['reported_at'] = Carbon::now();

        $incident = EnvironmentIncident::create($data);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'incident' => $incident]);
        }

        return back()->with('success', 'Novedad de ambiente registrada.');
    }

    /**
     * Cambiar estado (ABIERTA / EN_PROCESO / CERRADA)
     */
    public function updateStatus(Request $request, $id)
    {
        $incident = EnvironmentIncident::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:ABIERTA,EN_PROCESO,CERRADA'],
        ]);

        $incident->update($data);

        return back()->with('success', 'Estado de la novedad actualizado.');
    }
}
