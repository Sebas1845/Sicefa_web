<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\Motorcycle;
use Modules\GDF\Entities\MotorcycleAreaTransfer;
use Modules\GDF\Entities\Area;

class SubdirectionMotorcyclesController extends Controller
{
    private function authorizeRole(): void
    {
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);
    }

    // -------------------------
    // LISTADO INVENTARIO
    // -------------------------
    public function index(Request $request)
    {
        $this->authorizeRole();

        $q      = trim((string)$request->get('q', ''));
        $areaId = $request->get('area_id');
        $status = $request->get('status');

        $areas = Area::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $motorcycles = Motorcycle::query()
            ->select(['id', 'plate', 'brand', 'model', 'current_area_id', 'status', 'current_odometer'])
            ->with(['currentArea:id,name'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('plate', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%");
                });
            })
            ->when($areaId, fn($qq) => $qq->where('current_area_id', $areaId))
            ->when($status, fn($qq) => $qq->where('status', $status))
            ->orderBy('plate')
            ->paginate(15)
            ->withQueryString();

        $statsRaw = Motorcycle::query()
            ->selectRaw("
            COUNT(*) as total,
            SUM(status='available') as available,
            SUM(status='assigned') as assigned,
            SUM(status='maintenance') as maintenance,
            SUM(status='retired') as retired
        ")
            ->first();

        $stats = [
            'total'       => (int)($statsRaw->total ?? 0),
            'available'   => (int)($statsRaw->available ?? 0),
            'assigned'    => (int)($statsRaw->assigned ?? 0),
            'maintenance' => (int)($statsRaw->maintenance ?? 0),
            'retired'     => (int)($statsRaw->retired ?? 0),
        ];

        $quotaMeta = [];
        $year = (int) $request->get('year', now()->year);

        return view('gdf::subdirection.motorcycles.index', compact(
            'motorcycles',
            'areas',
            'q',
            'areaId',
            'status',
            'stats',
            'quotaMeta',
            'year'
        ));
    }



    // -------------------------
    // CREAR MOTO
    // -------------------------
    public function create()
    {
        $this->authorizeRole();
        $areas = Area::orderBy('name')->get();
        return view('gdf::subdirection.motorcycles.create', compact('areas'));
    }

    public function store(Request $request)
    {
        $this->authorizeRole();

        $data = $request->validate([
            'plate'            => ['required', 'string', 'max:30', 'unique:motorcycles,plate'],
            'brand'            => ['nullable', 'string', 'max:60'],
            'model'            => ['nullable', 'string', 'max:80'],
            'entry_date'       => ['nullable', 'date'],
            'current_area_id'  => ['nullable', 'integer', 'exists:areas,id'],
            'current_odometer' => ['nullable', 'integer', 'min:0'],
            'status'           => ['required', 'string', 'in:available,assigned,maintenance,retired'],
        ]);

        $m = Motorcycle::create([
            'plate'            => $data['plate'],
            'brand'            => $data['brand'] ?? null,
            'model'            => $data['model'] ?? null,
            'entry_date'       => $data['entry_date'] ?? null,
            'current_area_id'  => $data['current_area_id'] ?? null,
            'current_odometer' => $data['current_odometer'] ?? 0,
            'status'           => $data['status'],
            'created_by'       => Auth::id(),
        ]);

        // Si se asignó área inicial, registra transferencia inicial
        if (!empty($data['current_area_id'])) {
            MotorcycleAreaTransfer::create([
                'motorcycle_id' => $m->id,
                'from_area_id'  => null,
                'to_area_id'    => (int)$data['current_area_id'],
                'assigned_by'   => Auth::id(),
                'assigned_at'   => now(),
                'notes'         => 'Initial assignment on creation',
            ]);
        }

        return redirect()->route('gdf.subdirection.motorcycles.index')
            ->with('success', 'Moto creada correctamente.');
    }

    // -------------------------
    // TRANSFERIR ENTRE ÁREAS
    // -------------------------
    public function transfer(Request $request, Motorcycle $motorcycle)
    {
        $this->authorizeRole();

        $data = $request->validate([
            'to_area_id' => ['required', 'integer', 'exists:areas,id'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ]);

        // Regla recomendada: no transferir si está asignada a persona
        if ($motorcycle->status === 'assigned') {
            return back()->with('error', 'No se puede transferir una moto asignada. Debe devolverse primero.');
        }

        $from = $motorcycle->current_area_id;

        DB::transaction(function () use ($motorcycle, $data, $from) {
            MotorcycleAreaTransfer::create([
                'motorcycle_id' => $motorcycle->id,
                'from_area_id'  => $from,
                'to_area_id'    => (int)$data['to_area_id'],
                'assigned_by'   => Auth::id(),
                'assigned_at'   => now(),
                'notes'         => $data['notes'] ?? null,
            ]);

            $motorcycle->current_area_id = (int)$data['to_area_id'];
            $motorcycle->save();
        });

        return back()->with('success', 'Moto transferida correctamente.');
    }
}
