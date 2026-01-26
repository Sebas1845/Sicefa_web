<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Http\Request;
use Modules\GDF\Entities\TravelRequest;

class SitravOfficialController extends BaseOfficialController
{
    /* ======================
     | DASHBOARD SITRAV
     * ====================== */
    public function dashboard(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        [$col,$val] = $this->ownerFilter();

        $stats = [
            'draft' => TravelRequest::where('module','sitrav')
                ->where($col,$val)->where('status','draft')->count(),

            'radicated' => TravelRequest::where('module','sitrav')
                ->whereNotNull('radicado_code')->count(),
        ];

        return view('gdf::official.sitrav.dashboard', compact('ctx','stats'));
    }

    /* ======================
     | CREAR SITRAV
     * ====================== */
    public function store(Request $request)
    {
        $data = $request->validate([
            'program_request_id' => ['required','integer'],
            'hours' => ['required','integer','min:1'],
        ]);

        [$col,$val] = $this->ownerFilter();

        $req = new TravelRequest();
        $req->module = 'sitrav';
        $req->source = 'sigac';
        $req->source_request_id = $data['program_request_id'];
        $req->{$col} = $val;

        $req->status = 'submitted';
        $req->save();

        return redirect()
            ->route('gdf.official.sitrav.show', $req)
            ->with('success','Solicitud SITRAV creada.');
    }
}
