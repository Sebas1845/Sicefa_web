<?php

namespace Modules\GDF\Http\Controllers\Support;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\Motorcycle;
use Modules\GDF\Entities\MotorcycleAssignment;

class SupportMotorcyclesController extends Controller
{
    private function authorizeSupport(string $areaKey): void
    {
        $ok = function_exists('checkRol') && (
            ($areaKey === 'academic'  && checkRol('gdf.academic_support')) ||
            ($areaKey === 'campesena' && checkRol('gdf.campesena_support')) ||
            checkRol('gdf.superadmin')
        );
        if (!$ok) abort(403);
    }

    private function areaKeyFromPath(Request $request): string
    {
        return str_contains($request->path(), 'gdf/campesena') ? 'campesena' : 'academic';
    }

    public function queue(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $areaId = (int) $request->get('area_id', 0); // opcional si quieres filtrar

        $items = MotorcycleAssignment::query()
            ->with(['person', 'area', 'motorcycle'])
            ->whereIn('status', ['approved', 'delivered']) // lo que apoyo opera
            ->when($areaId > 0, fn($q) => $q->where('area_id', $areaId))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('gdf::support.motorcycles.queue', compact('items', 'areaKey', 'areaId'));
    }

    public function assign(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        // En este diseño, la asignación (moto + approved) ya quedó en el store().
        // Este endpoint puede quedar para “re-asignar moto” si lo deseas.
        return back()->with('info', 'La asignación directa ya deja la moto asignada. (assign opcional)');
    }

    public function deliver(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'odometer_out' => ['required', 'integer', 'min:0'],
            'observations_out' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!in_array($assignment->status, ['approved'], true)) {
            return back()->with('error', 'Solo se puede entregar una asignación en estado approved.');
        }

        DB::transaction(function () use ($assignment, $data) {
            $assignment->delivered_at = now();
            $assignment->odometer_out = (int) $data['odometer_out'];
            $assignment->observations_out = $data['observations_out'] ?? $assignment->observations_out;
            $assignment->status = 'delivered';
            $assignment->managed_by = Auth::id();
            $assignment->save();

            if ($assignment->motorcycle_id) {
                Motorcycle::where('id', (int)$assignment->motorcycle_id)->update(['status' => 'delivered']);
            }
        });

        return back()->with('success', 'Moto entregada correctamente.');
    }

    public function return(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'odometer_in' => ['required', 'integer', 'min:0'],
            'observations_in' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!in_array($assignment->status, ['delivered'], true)) {
            return back()->with('error', 'Solo se puede devolver una asignación en estado delivered.');
        }

        if (!is_null($assignment->odometer_out) && (int)$data['odometer_in'] < (int)$assignment->odometer_out) {
            return back()->with('error', 'El odómetro de entrada no puede ser menor al de salida.');
        }

        DB::transaction(function () use ($assignment, $data) {
            $assignment->returned_at = now();
            $assignment->odometer_in = (int) $data['odometer_in'];
            $assignment->observations_in = $data['observations_in'] ?? null;
            $assignment->status = 'returned';
            $assignment->managed_by = Auth::id();
            $assignment->save();

            if ($assignment->motorcycle_id) {
                Motorcycle::where('id', (int)$assignment->motorcycle_id)->update(['status' => 'available']);
            }
        });

        return back()->with('success', 'Moto devuelta correctamente.');
    }

    public function cancel(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if (!in_array($assignment->status, ['approved'], true)) {
            return back()->with('error', 'Solo se puede cancelar una asignación en estado approved.');
        }

        DB::transaction(function () use ($assignment, $data) {
            $assignment->status = 'cancelled';
            $assignment->managed_by = Auth::id();
            $assignment->observations_out = trim(($assignment->observations_out ?? '') . "\nCANCEL: " . ($data['reason'] ?? ''));
            $assignment->save();

            if ($assignment->motorcycle_id) {
                Motorcycle::where('id', (int)$assignment->motorcycle_id)->update(['status' => 'available']);
            }
        });

        return back()->with('success', 'Asignación cancelada.');
    }
    public function createMotorcycle(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        return view('gdf::support.motorcycles.create', compact('areaKey'));
    }

    public function storeMotorcycle(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'plate' => ['required', 'string', 'max:20', 'unique:motorcycles,plate'],
            'brand' => ['nullable', 'string', 'max:60'],
            'model' => ['nullable', 'string', 'max:60'],
            'current_odometer' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // opcional: adjunto (foto/tarjeta/etc)
            'attachment' => ['nullable', 'file', 'max:5120'], // 5MB
        ]);

        DB::transaction(function () use ($data, $areaKey, $request) {
            $m = new Motorcycle();
            $m->plate = strtoupper(trim($data['plate']));
            $m->brand = $data['brand'] ?? null;
            $m->model = $data['model'] ?? null;
            $m->current_odometer = (int) $data['current_odometer'];
            $m->status = 'available';
            $m->current_area_id = ($areaKey === 'campesena') ? 1 : 2; // si quieres que nazca ubicada
            // si tienes campo notes/observations en motorcycles:
            if (property_exists($m, 'notes')) $m->notes = $data['notes'] ?? null;
            $m->save();

            if ($request->hasFile('attachment')) {
                $path = $request->file('attachment')->store('gdf/motorcycles', 'public');
                // Guarda ruta si tienes columna (ej: attachment_path). Si no existe, crea una.
                if (schema_has_column('motorcycles', 'attachment_path')) {
                    $m->attachment_path = $path;
                    $m->save();
                }
            }
        });

        $prefix = 'gdf.' . ($areaKey === 'campesena' ? 'campesena' : 'academic');
        return redirect()->route($prefix . '.motorcycles.index')->with('success', 'Moto creada correctamente.');
    }
}
