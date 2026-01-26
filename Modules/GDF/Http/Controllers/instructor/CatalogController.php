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

        $personId = (int) (Auth::user()->person_id ?? 0);
        if (!$personId) abort(403);

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
        $placeType = $request->query('place_type'); // municipio|vereda
        $transport = $request->query('transport_mode'); // bus|van|air|motorcycle
        $municipalityId = $request->query('municipality_id');
        $villageId = $request->query('village_id');

        if (!in_array($placeType, ['municipio', 'vereda'], true)) {
            return response()->json(['message' => 'place_type inválido'], 422);
        }
        if (!in_array($transport, ['bus', 'van', 'air', 'motorcycle'], true)) {
            return response()->json(['message' => 'transport_mode inválido'], 422);
        }
        if (empty($municipalityId)) {
            return response()->json(['message' => 'municipality_id requerido'], 422);
        }
        if ($placeType === 'vereda' && empty($villageId)) {
            return response()->json(['message' => 'village_id requerido'], 422);
        }

        // 1) Resolver nombres desde IDs (según tus tablas reales)
        $munName = DB::table('municipalities')->where('id', $municipalityId)->value('name');
        if (!$munName) {
            return response()->json(['message' => 'municipio no encontrado'], 404);
        }

        $vilName = null;
        if ($placeType === 'vereda') {
            $vilName = DB::table('villages')->where('id', $villageId)->value('name');
            if (!$vilName) {
                return response()->json(['message' => 'vereda no encontrada'], 404);
            }
        }

        // 2) Obtener monto desde tablas actuales (por ahora solo transport_amount)
        // Cuando cambies a campos por modo (aereo/bus/moto/camioneta), aquí ajustas.
        $amount = 0;

        if ($placeType === 'vereda') {
            $amount = (float) DB::table('village_rates')
                ->where('active', 1)
                ->whereRaw('LOWER(village_name) = ?', [mb_strtolower(trim($vilName))])
                ->orderByDesc('id')
                ->value('transport_amount') ?? 0;
        } else {
            $amount = (float) DB::table('municipality_rates')
                ->where('active', 1)
                ->whereRaw('LOWER(municipality_name) = ?', [mb_strtolower(trim($munName))])
                ->orderByDesc('id')
                ->value('transport_amount') ?? 0;
        }

        // 3) Gasolina sugerida: por ahora 0 (hasta que tengas consumo/tarifa)
        // Cuando metas fuel por moto, aquí lo devuelves.
        $fuelAmount = 0;

        return response()->json([
            'amount' => $amount,
            'fuel_amount' => $fuelAmount,
            'place_type' => $placeType,
            'transport_mode' => $transport,
        ]);
    }
}
