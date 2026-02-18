<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CatalogController extends BaseOfficialController
{
    public function budgetItems(Request $request)
    {
        $ctx = session('gdf_context', []);
        $this->assertOfficialContext($ctx);

        $personId = $this->personId();
        if (!$personId) return response()->json([]);


        $allowedAreaIds = $this->areaIdsByKey($ctx['area']);
        if (empty($allowedAreaIds)) return response()->json([]);

        $areaId = (int) $request->query('area_id');

        // Si no mandan area_id -> usar primer área del grupo (carga "de una")
        if (!$areaId) {
            $areaId = (int) ($allowedAreaIds[0] ?? 0);
        }

        if (!$areaId || !in_array($areaId, $allowedAreaIds, true)) {
            abort(403);
        }

        // Rubros asignados al instructor + habilitados en esa área
        $rows = DB::table('person_area_budget_assignments as paba')
            ->join('area_budget_items as abi', function ($j) {
                $j->on('abi.area_id', '=', 'paba.area_id')
                    ->on('abi.budget_item_id', '=', 'paba.budget_item_id');
            })
            ->join('budget_items as bi', 'bi.id', '=', 'paba.budget_item_id')
            ->where('paba.person_id', $personId)
            ->where('paba.is_active', 1)
            ->where('paba.area_id', $areaId)
            ->where('abi.active', 1)
            ->select('bi.id', DB::raw("COALESCE(bi.code,'') as code"), 'bi.name')
            ->orderBy('bi.name')
            ->distinct()
            ->get();

        return response()->json($rows);
    }

    public function departments(Request $request)
    {
        $countryId = (int) config('gdf.country_id', 25);

        $rows = DB::table('departments')
            ->select('id', 'name')
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }

    public function municipalities(Request $request)
    {
        $departmentId = (int) $request->query('department_id');
        if (!$departmentId) return response()->json([]);

        $rows = DB::table('municipalities')
            ->select('id', 'name')
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }

    public function villages(Request $request)
    {
        $municipalityId = (int) $request->query('municipality_id');
        if (!$municipalityId) return response()->json([]);

        $rows = DB::table('villages')
            ->select('id', 'name')
            ->where('municipality_id', $municipalityId)
            ->orderBy('name')
            ->get();

        return response()->json($rows);
    }
    public function transportRate(Request $request)
    {
        $placeType      = $request->query('place_type');        // municipio|vereda
        $transport      = $request->query('transport_mode');    // bus|van|air|motorcycle
        $municipalityId = (int) $request->query('municipality_id');
        $villageId      = (int) $request->query('village_id');

        if (!in_array($placeType, ['municipio', 'vereda'], true)) {
            return response()->json(['message' => 'place_type inválido'], 422);
        }
        if (!in_array($transport, ['bus', 'van', 'air', 'motorcycle'], true)) {
            return response()->json(['message' => 'transport_mode inválido'], 422);
        }
        if ($municipalityId <= 0) {
            return response()->json(['message' => 'municipality_id requerido'], 422);
        }
        if ($placeType === 'vereda' && $villageId <= 0) {
            return response()->json(['message' => 'village_id requerido'], 422);
        }

        $rate = null;
        $source = null;

        if ($placeType === 'vereda') {
            $rate = DB::table('village_rates')
                ->where('village_id', $villageId)
                ->where('active', 1)
                ->orderByDesc('id')
                ->first();
            $source = 'village_rates';
        } else {
            $rate = DB::table('municipality_rates')
                ->where('municipality_id', $municipalityId)
                ->where('active', 1)
                ->orderByDesc('id')
                ->first();
            $source = 'municipality_rates';
        }

        $baseAmount = 0.0;
        if ($rate) {
            $baseAmount = match ($transport) {
                'bus'        => (float) ($rate->bus_amount ?? 0),
                'van'        => (float) ($rate->van_amount ?? 0),
                'motorcycle' => (float) ($rate->motorcycle_amount ?? 0),
                'air'        => (float) ($rate->air_amount ?? 0), // municipality_rates sí tiene air_amount
                default      => 0.0,
            };
        }

        return response()->json([
            'base_amount'      => $baseAmount,     // tarifa control (base)
            'suggested_amount' => $baseAmount,     // sugerida para autollenar
            'place_type'       => $placeType,
            'transport_mode'   => $transport,
            'source'           => $source,
            'has_rate'         => (bool) $rate,
            'rate_id'          => (int) ($rate->id ?? 0),
        ]);
    }
}
