<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\GDF\Entities\TravelRequest;

class OfficialController extends BaseOfficialController
{
    public function dashboard(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $areaKey = $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }

        [$col, $val] = $this->ownerFilter();
        $areaIds = $this->areaIdsByKey($areaKey);

        $stats = [
            'draft'    => TravelRequest::where('module', 'gdf')->where($col, $val)->whereIn('area_id', $areaIds)->where('status', 'draft')->count(),
            'sent'     => TravelRequest::where('module', 'gdf')->where($col, $val)->whereIn('area_id', $areaIds)->whereIn('status', ['submitted', 'in_review'])->count(),
            'approved' => TravelRequest::where('module', 'gdf')->where($col, $val)->whereIn('area_id', $areaIds)->where('status', 'approved')->count(),
            'rejected' => TravelRequest::where('module', 'gdf')->where($col, $val)->whereIn('area_id', $areaIds)->whereIn('status', ['rejected', 'returned'])->count(),
        ];

        $recent = TravelRequest::where('module', 'gdf')
            ->where($col, $val)
            ->whereIn('area_id', $areaIds)
            ->latest()
            ->limit(8)
            ->get();

        return view('gdf::official.dashboard', compact('ctx', 'areaKey', 'areaIds', 'stats', 'recent', 'gate'));
    }

    public function index(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $areaKey = $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }

        [$col, $val] = $this->ownerFilter();
        $areaIds = $this->areaIdsByKey($areaKey);

        $q = trim((string) $request->get('q', ''));

        $query = TravelRequest::where('module', 'gdf')
            ->where($col, $val)
            ->whereIn('area_id', $areaIds);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('origin', 'like', "%{$q}%")
                    ->orWhere('destination', 'like', "%{$q}%")
                    ->orWhere('id', $q);
            });
        }

        $requests = $query->latest()->paginate(15)->appends(['q' => $q]);

        return view('gdf::official.requests.index', compact('ctx', 'areaKey', 'areaIds', 'requests', 'q', 'gate'));
    }

    public function create(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $areaKey = $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }
        if (!$gate['can_create']) {
            return redirect()->route('gdf.instructor.dashboard')->with('warning', $gate['message']);
        }

        $areaIds = $this->areaIdsByKey($areaKey);
        $defaultAreaId = $areaIds[0] ?? null;

        $personId = (int) (auth()->user()->person_id ?? 0);

        // Rubros permitidos (catálogo se carga por fetch; esto lo dejas por si lo necesitas en server-side)
        $budgetItems = $this->allowedBudgetItemsForPersonInAreaIds($personId, [$defaultAreaId]);

        // Bloqueo moto
        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
        $motoLock = [
            'has_moto' => (bool) $activeMoto,
            'message'  => $activeMoto
                ? 'Tienes una moto asignada. En Huila se puede forzar moto; fuera de Huila no aplica.'
                : null
        ];

        // Regla rubro obligatorio/opcional (ajusta a tu lógica)
        $budgetRule = [
            'required' => true,
            'message'  => null,
        ];

        // Catálogos
        $countryId = (int) config('gdf.country_id', 25);

        $departments = DB::table('departments')
            ->select('id', 'name')
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->get();

        // Si conoces el ID de Huila (según lo que dijiste)
        $HUILA_ID = 421;

        // Intenta dejar Huila por defecto si existe; si no, el primero.
        $defaultDepartmentId = (int) (
            $departments->firstWhere('id', $HUILA_ID)->id
            ?? ($departments->first()->id ?? 0)
        );

        // Coordenadas por defecto: Centro de Formación (origen) y Neiva (destino)
        // RECOMENDADO: mueve esto a config('gdf.places...') o a tabla gdf_places en el futuro.
        $defaultOrigin = [
            'label' => 'Centro de Formación (por defecto)',
            'lat'   => 2.615508,
            'lng'   => -75.359011,
        ];

        $origins = [
            ['key' => 'center_default', 'label' => 'Centro de Formación', 'lat' => 2.615508, 'lng' => -75.359011],
            // Terminal Neiva (coords buscadas)
            ['key' => 'terminal_neiva', 'label' => 'Terminal Neiva', 'lat' => 2.91681, 'lng' => -75.28191],
        ];

        $hasMoto = (bool) ($motoLock['has_moto'] ?? false);

        // Determinar si es planta (viáticos)
        // Ajusta estos nombres de tabla/campos a tu BD real.
        $userId = (int) (auth()->id() ?? 0);

        $isEmployee = DB::table('employees')->where('person_id', $personId)->exists();
        $isContractor = DB::table('contractors')->where('person_id', $personId)->exists();

        // Regla solicitada: planta sí viáticos, contratista no viáticos (por ahora)
        $isPlant = $isEmployee && !$isContractor;

        $vanAllowed = false;

        return view('gdf::official.requests.create', compact(
            'ctx',
            'areaKey',
            'areaIds',
            'defaultAreaId',
            'gate',
            'budgetItems',
            'motoLock',
            'budgetRule',
            'countryId',
            'departments',
            'defaultDepartmentId',
            'origins',
            'defaultOrigin',
            'hasMoto',
            'isPlant',
            'HUILA_ID',
            'vanAllowed'
        ));
    }


    public function store(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $areaKey = $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }
        if (!$gate['can_create']) {
            return back()->with('warning', $gate['message'])->withInput();
        }

        $areaIds = $this->areaIdsByKey($areaKey);
        $defaultAreaId = $areaIds[0] ?? null;

        $personId = (int) (auth()->user()->person_id ?? 0);

        // Bloqueo moto: si tiene moto, forzar transport_mode=motorcycle
        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
        $hasMoto = (bool) $activeMoto;

        $rules = [
            'area_id'          => ['required', 'integer', 'in:' . implode(',', $areaIds)],
            'budget_item_id'   => ['nullable', 'integer'],
            'origin'    => ['required', 'string', 'max:50'],
            'municipality_id'  => ['nullable', 'integer'],
            'village_id'       => ['nullable', 'integer'],
            'destination_lat'  => ['required', 'numeric'],
            'destination_lng'  => ['required', 'numeric'],
            'start_date'       => ['required', 'date'],
            'end_date'         => ['required', 'date', 'after_or_equal:start_date'],
            'estimated_value'  => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:5000'],
            'transport_mode'   => ['nullable', 'in:bus,car,motorcycle'],
        ];

        $data = $request->validate($rules);

        // Forzar area_id al default si quieres que NO sea editable:
        // $data['area_id'] = $defaultAreaId;

        if ($hasMoto) {
            $data['transport_mode'] = 'motorcycle';
        } else {
            $data['transport_mode'] = $data['transport_mode'] ?? 'bus';
        }

        // validar rubro permitido (si mandó uno)
        if (!empty($data['budget_item_id'])) {
            $allowed = $this->allowedBudgetItemsForPersonInAreaIds($personId, [$data['area_id']]);
            $allowedIds = array_map(fn($x) => is_array($x) ? ($x['id'] ?? null) : ($x->id ?? null), $allowed);
            if (!in_array((int)$data['budget_item_id'], array_map('intval', $allowedIds), true)) {
                return back()->with('error', 'El rubro seleccionado no está permitido para tu asignación/área.')->withInput();
            }
        }

        [$col, $val] = $this->ownerFilter();

        $req = new TravelRequest();
        $req->module = 'gdf';
        $req->{$col} = $val;

        // Mapea a tus columnas reales
        $req->area_id = (int) $data['area_id'];
        $req->budget_item_id = $data['budget_item_id'] ?? null;
        $req->origin = $data['origin'];
        $req->municipality_id = $data['municipality_id'] ?? null;
        $req->village_id = $data['village_id'] ?? null;

        $req->destination_lat = $data['destination_lat'];
        $req->destination_lng = $data['destination_lng'];

        $req->start_date = $data['start_date'];
        $req->end_date = $data['end_date'];

        $req->estimated_value = $data['estimated_value'] ?? null;
        $req->notes = $data['notes'] ?? null;

        $req->transport_mode = $data['transport_mode'];

        $req->status = 'submitted';
        $req->save();

        return redirect()
            ->route('gdf.instructor.requests.show', $req->id)
            ->with('success', 'Solicitud enviada.');
    }

    public function show(TravelRequest $gdfRequest)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $areaKey = $ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);

        [$col, $val] = $this->ownerFilter();

        if ($gdfRequest->module !== 'gdf' || $gdfRequest->{$col} !== $val || !in_array((int)$gdfRequest->area_id, $areaIds, true)) {
            abort(403);
        }

        $gate = $this->creationGateForCtxArea($areaKey);

        return view('gdf::official.requests.show', compact('ctx', 'areaKey', 'areaIds', 'gdfRequest', 'gate'));
    }

    public function cancel(TravelRequest $gdfRequest)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $areaKey = $ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);

        [$col, $val] = $this->ownerFilter();

        if ($gdfRequest->module !== 'gdf' || $gdfRequest->{$col} !== $val || !in_array((int)$gdfRequest->area_id, $areaIds, true)) {
            abort(403);
        }

        if (!in_array($gdfRequest->status, ['draft', 'submitted'], true)) {
            return back()->with('error', 'No se puede cancelar en este estado.');
        }

        $gdfRequest->status = 'cancelled';
        $gdfRequest->save();

        return redirect()->route('gdf.instructor.requests.index')->with('success', 'Solicitud cancelada.');
    }
}
