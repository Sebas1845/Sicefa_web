<?php

namespace Modules\GDF\Http\Controllers\Support;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use Carbon\Carbon;

// AJUSTA: modelo real
use Modules\GDF\Entities\GdfRequest;

class GdfSupportRequestsController extends Controller
{
    public function index(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupportByArea($areaKey);

        $routePrefix = $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';
        $q = trim((string)$request->get('q',''));
        $status = $request->get('status','pending_treasury');

        $query = GdfRequest::query()
            ->where('area_key', $areaKey);

        if ($status !== 'all') $query->where('status', $status);

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function($qq) use ($q,$like){
                if (ctype_digit($q)) $qq->orWhere('id',(int)$q);
                $qq->orWhere('origin','like',$like)
                   ->orWhere('destination','like',$like)
                   ->orWhere('request_type','like',$like);
            });
        }

        $requests = $query->orderByDesc('id')->paginate(10)->appends($request->query());

        return view('gdf::support.requests.index', compact('requests','q','status','areaKey','routePrefix'));
    }

    public function create(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupportByArea($areaKey);

        $routePrefix = $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';

        // AJUSTA: aquí normalmente traes lista de personas asignadas al área
        $people = collect(); // mañana conectamos a SICA Person/Employee/Contractor si quieres

        $budgetItems = collect(); // AJUSTA

        return view('gdf::support.requests.create', compact('areaKey','routePrefix','people','budgetItems'));
    }

    public function store(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupportByArea($areaKey);

        $routePrefix = $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';

        $data = $request->validate([
            // Persona solicitante (si apoyo crea por otros)
            'applicant_person_id' => ['nullable','integer'], // AJUSTA si usas person_id real
            'budget_item_id'      => ['nullable','integer'],
            'origin'             => ['required','string','max:255'],
            'destination'        => ['required','string','max:255'],
            'start_date'         => ['required','date'],
            'end_date'           => ['required','date','after_or_equal:start_date'],
            'request_type'       => ['required','string','max:50'],
            'person_type'        => ['nullable','string','max:50'],
            'amount'             => ['nullable','numeric','min:0'],
            'notes'              => ['nullable','string','max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $row = new GdfRequest();
            $row->area_key       = $areaKey;
            $row->budget_item_id = $data['budget_item_id'] ?? null;

            // solicitante (si no seleccionan, apoyo crea como “apoyo”)
            if (!empty($data['applicant_person_id']) && $this->propertyExists($row,'applicant_person_id')) {
                $row->applicant_person_id = $data['applicant_person_id'];
            }

            $row->origin        = $data['origin'];
            $row->destination   = $data['destination'];
            $row->start_date    = $data['start_date'];
            $row->end_date      = $data['end_date'];
            $row->request_type  = $data['request_type'];
            $row->person_type   = $data['person_type'] ?? 'instructor';

            $row->created_by    = Auth::id(); // quien registró (apoyo)
            if ($this->propertyExists($row,'created_as')) $row->created_as = 'support';

            $row->amount        = $data['amount'] ?? 0;
            $row->notes         = $data['notes'] ?? null;

            $row->status        = 'pending_treasury';

            $row->save();

            DB::commit();
            return redirect()->route($routePrefix.'.requests.show', $row->id)
                ->with('success','Solicitud creada por Apoyo y enviada a Tesorería.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error','No se pudo crear: '.$e->getMessage());
        }
    }

    public function show(Request $request, $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupportByArea($areaKey);

        $routePrefix = $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';

        $row = GdfRequest::findOrFail($id);
        if ($row->area_key !== $areaKey) abort(403);

        return view('gdf::support.requests.show', ['r'=>$row,'areaKey'=>$areaKey,'routePrefix'=>$routePrefix]);
    }

    public function cancel(Request $request, $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupportByArea($areaKey);

        $row = GdfRequest::findOrFail($id);
        if ($row->area_key !== $areaKey) abort(403);

        if (!in_array($row->status, ['pending_treasury','returned_to_support'], true)) {
            return back()->with('warning','No se puede cancelar en este estado.');
        }

        $row->status = 'cancelled';
        $row->save();

        return back()->with('success','Solicitud cancelada.');
    }

    public function uploadDocuments(Request $request, $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupportByArea($areaKey);

        $row = GdfRequest::findOrFail($id);
        if ($row->area_key !== $areaKey) abort(403);

        $request->validate([
            'documents.*' => ['required','file','max:5120'],
        ]);

        $paths = [];
        foreach ((array)$request->file('documents', []) as $f) {
            $paths[] = $f->store("gdf/requests/{$row->id}", 'public');
        }

        if (property_exists($row,'documents_json') || array_key_exists('documents_json',$row->getAttributes())) {
            $prev = json_decode($row->documents_json ?? '[]', true) ?: [];
            $row->documents_json = json_encode(array_values(array_merge($prev, $paths)));
            $row->save();
        }

        return back()->with('success','Documentos cargados.');
    }

    private function areaKeyFromPath(Request $request): string
    {
        return str_contains($request->path(), 'gdf/campesena') ? 'campesena' : 'academic';
    }

    private function authorizeSupportByArea(string $areaKey): void
    {
        $ok = function_exists('checkRol')
            ? ($areaKey === 'campesena'
                ? checkRol('gdf.campesena_support')
                : checkRol('gdf.academic_support'))
            : true;

        if(!$ok) abort(403);
    }

    private function propertyExists($model, string $prop): bool
    {
        try {
            $attrs = method_exists($model,'getAttributes') ? array_keys($model->getAttributes()) : [];
            return property_exists($model,$prop) || in_array($prop,$attrs,true);
        } catch (\Throwable $e) {
            return property_exists($model,$prop);
        }
    }
}
