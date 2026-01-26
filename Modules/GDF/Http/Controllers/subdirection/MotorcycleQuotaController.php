<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\Motorcycle;
use Modules\GDF\Entities\MotorcycleAreaQuota;

class MotorcycleQuotaController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    private function guardSubdirection(): void
    {
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);
    }

    public function index(Request $request)
    {
        $this->guardSubdirection();

        $year = (int) $request->get('year', now()->year);

        $areas = Area::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $usedByArea = Motorcycle::query()
            ->selectRaw('current_area_id as area_id, COUNT(*) as used')
            ->whereNotNull('current_area_id')
            ->groupBy('current_area_id')
            ->pluck('used', 'area_id'); // [area_id => used]

        // ✅ LEER cupos guardados
        $quotas = MotorcycleAreaQuota::query()
            ->select(['area_id', 'quota_total'])
            ->where('year', $year)
            ->where('active', 1)
            ->get()
            ->keyBy('area_id');

        $quotasByArea = [];
        foreach ($areas as $a) {
            $quota = (int) ($quotas[$a->id]->quota_total ?? 0);
            $used  = (int) ($usedByArea[$a->id] ?? 0);

            $quotasByArea[$a->id] = [
                'quota_total' => $quota,
                'used'        => $used,
            ];
        }

        $years = collect(range(now()->year - 2, now()->year + 1));

        return view('gdf::subdirection.motorcycles.quotas', compact(
            'areas',
            'year',
            'years',
            'quotasByArea'
        ));
    }

    public function store(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'year'        => ['required', 'integer', 'min:2000', 'max:2100'],
            'area_id'     => ['required', 'integer', 'exists:areas,id'],
            'quota_total' => ['required', 'integer', 'min:0'],
            'notes'       => ['nullable', 'string', 'max:2000'],
        ]);

        MotorcycleAreaQuota::updateOrCreate(
            [
                'year'    => (int) $data['year'],
                'area_id' => (int) $data['area_id'],
            ],
            [
                'quota_total' => (int) $data['quota_total'],
                'active'      => 1,
                'set_by'      => Auth::id(),
                'notes'       => $data['notes'] ?? null,
            ]
        );

        return back()->with('success', 'Cupo actualizado correctamente.');
    }
}
