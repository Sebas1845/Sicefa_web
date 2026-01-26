<?php

namespace Modules\GDF\Http\Controllers\Treasury;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// AJUSTA: modelo real
use Modules\GDF\Entities\GdfRequest;

class GdfTreasuryRequestsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeTreasury();

        $q      = trim((string)$request->get('q',''));
        $area   = $request->get('area','all'); // academic|campesena|all
        $status = $request->get('status','pending_treasury'); // default bandeja

        $query = GdfRequest::query();

        if ($area !== 'all') $query->where('area_key', $area);
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

        $requests = $query->orderByDesc('id')->paginate(12)->appends($request->query());

        // KPIs simples (opcionales)
        $kpis = [
            'pending' => (clone $query)->where('status','pending_treasury')->count(),
        ];

        return view('gdf::treasury.requests.index', compact('requests','q','area','status','kpis'));
    }

    public function show(Request $request, $id)
    {
        $this->authorizeTreasury();

        $row = GdfRequest::findOrFail($id);

        return view('gdf::treasury.requests.show', ['r'=>$row]);
    }

    public function approve(Request $request, $id)
    {
        $this->authorizeTreasury();

        DB::beginTransaction();
        try {
            $row = GdfRequest::lockForUpdate()->findOrFail($id);

            if ($row->status !== 'pending_treasury') {
                return back()->with('warning','La solicitud ya no está pendiente de Tesorería.');
            }

            // Aquí iría tu validación real de presupuesto disponible (mañana lo conectamos a budgets)
            // if(!$this->hasFunds($row)) return back()->with('warning','No hay recursos disponibles.');

            $row->status = 'approved_by_treasury';

            if ($this->propertyExists($row,'treasury_by')) $row->treasury_by = Auth::id();
            if ($this->propertyExists($row,'treasury_at')) $row->treasury_at = Carbon::now();

            $row->save();

            DB::commit();
            return redirect()->route('gdf.treasury.requests.show',$row->id)
                ->with('success',"Solicitud #{$row->id} aprobada por Tesorería.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error','No se pudo aprobar: '.$e->getMessage());
        }
    }

    public function return(Request $request, $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'target'  => ['required','in:support,applicant'], // devolver a apoyo o solicitante
            'comment' => ['required','string','max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $row = GdfRequest::lockForUpdate()->findOrFail($id);

            if ($row->status !== 'pending_treasury') {
                return back()->with('warning','La solicitud ya no está pendiente de Tesorería.');
            }

            $row->status = $data['target']==='support' ? 'returned_to_support' : 'returned_to_applicant';

            if ($this->propertyExists($row,'treasury_comment')) $row->treasury_comment = $data['comment'];
            elseif ($this->propertyExists($row,'comment')) $row->comment = $data['comment'];

            if ($this->propertyExists($row,'treasury_by')) $row->treasury_by = Auth::id();
            if ($this->propertyExists($row,'treasury_at')) $row->treasury_at = Carbon::now();

            $row->save();

            DB::commit();
            return back()->with('info',"Solicitud #{$row->id} devuelta a ".($data['target']==='support'?'Apoyo':'Solicitante').'.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error','No se pudo devolver: '.$e->getMessage());
        }
    }

    public function reject(Request $request, $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['required','string','max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $row = GdfRequest::lockForUpdate()->findOrFail($id);

            if ($row->status !== 'pending_treasury') {
                return back()->with('warning','La solicitud ya no está pendiente de Tesorería.');
            }

            $row->status = 'rejected_by_treasury';

            if ($this->propertyExists($row,'treasury_comment')) $row->treasury_comment = $data['comment'];
            elseif ($this->propertyExists($row,'comment')) $row->comment = $data['comment'];

            if ($this->propertyExists($row,'treasury_by')) $row->treasury_by = Auth::id();
            if ($this->propertyExists($row,'treasury_at')) $row->treasury_at = Carbon::now();

            $row->save();

            DB::commit();
            return back()->with('success',"Solicitud #{$row->id} rechazada por Tesorería.");
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error','No se pudo rechazar: '.$e->getMessage());
        }
    }

    private function authorizeTreasury(): void
    {
        $ok = function_exists('checkRol') ? checkRol('gdf.treasury') : true;
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
