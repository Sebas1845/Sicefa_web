<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\BudgetItem;
use Modules\GDF\Entities\AreaBudgetItem;
use Modules\GDF\Entities\BudgetItemYear; // NUEVO (tabla budget_item_years)

class AreaBudgetItemController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    private function guardSubdirection(): void
    {
        $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
        if (!$isSubdirection) abort(403);
    }

    /**
     * GET: pantalla para configurar rubros permitidos por área
     * + Filtro por año (vigencia) y carga histórico de años por rubro
     */
    public function edit(Request $request, $areaId)
    {
        $this->guardSubdirection();

        $area = Area::findOrFail($areaId);

        $q = trim((string)$request->get('q', ''));
        $year = (int)($request->get('year') ?: now()->year);

        // Rubros (catálogo)
        $rubros = BudgetItem::query()
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('active')
            ->orderBy('name')
            ->get();

        // Rubros ya asociados al área (incluye active)
        $current = AreaBudgetItem::where('area_id', $area->id)
            ->get()
            ->keyBy('budget_item_id');

        // ==========================
        // VIGENCIA POR AÑO (budget_item_years)
        // ==========================
        $rubroIds = $rubros->pluck('id')->all();

        // Estado de vigencia para el año seleccionado
        $yearRows = BudgetItemYear::query()
            ->whereIn('budget_item_id', $rubroIds)
            ->where('year', $year)
            ->get()
            ->keyBy('budget_item_id');

        // Histórico de años por rubro (para mostrar badges/tabla)
        $historyRows = BudgetItemYear::query()
            ->whereIn('budget_item_id', $rubroIds)
            ->orderByDesc('year')
            ->get()
            ->groupBy('budget_item_id');

        // Mapa: rubro_id => bool vigente en $year (si no existe fila, asumimos NO vigente)
        $vigencyMap = collect($rubroIds)->mapWithKeys(function ($id) use ($yearRows) {
            $row = $yearRows->get($id);
            return [$id => (bool)($row->active ?? false)];
        });

        // Mapa: rubro_id => Collection rows (year, active, starts_on, ends_on)
        $historyMap = $historyRows;

        // Lista de años disponibles para selector (derivada de tabla)
        // Si no hay datos aún, propone un rango alrededor del año actual.
        $yearsList = BudgetItemYear::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->values();

        if ($yearsList->isEmpty()) {
            $y = now()->year;
            $yearsList = collect([$y + 1, $y, $y - 1, $y - 2])->unique()->values();
        }

        return view('gdf::subdirection.area_rubros.edit', compact(
            'area',
            'rubros',
            'current',
            'q',
            'year',
            'yearsList',
            'vigencyMap',
            'historyMap'
        ));
    }

    /**
     * POST: guardar configuración (sync)
     * - allowed[] = ids de budget_items marcados
     *
     * Reglas:
     * - Si estás trabajando con filtro de año (vigencia), NO permite habilitar rubros
     *   que NO estén vigentes (active=true) en ese año.
     */
    public function update(Request $request, $areaId)
    {
        $this->guardSubdirection();

        $area = Area::findOrFail($areaId);

        $data = $request->validate([
            'allowed'   => ['nullable', 'array'],
            'allowed.*' => ['integer', 'exists:budget_items,id'],

            // Año usado en la pantalla (para validar vigencia)
            'year'      => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int)($data['year'] ?? now()->year);

        $allowedIds = collect($data['allowed'] ?? [])
            ->map(fn($v) => (int)$v)
            ->unique()
            ->values();

        // Validación de negocio: solo permitir rubros vigentes en ese año
        if ($allowedIds->isNotEmpty()) {
            $vigentes = BudgetItemYear::query()
                ->whereIn('budget_item_id', $allowedIds->all())
                ->where('year', $year)
                ->where('active', true)
                ->pluck('budget_item_id')
                ->map(fn($v) => (int)$v);

            $noVigentes = $allowedIds->diff($vigentes)->values();

            if ($noVigentes->isNotEmpty()) {
                // Puedes listar códigos/nombres si quieres; aquí lo dejo simple y seguro
                return back()
                    ->withInput()
                    ->with('error', 'No puedes habilitar rubros que no estén vigentes para el año ' . $year . '. Primero marca su vigencia.');
            }
        }

        DB::transaction(function () use ($area, $allowedIds) {

            // 1) Desactivar todos los existentes del área
            AreaBudgetItem::where('area_id', $area->id)->update([
                'active'     => false,
                'updated_by' => auth()->id(),
                'updated_at' => now(),
            ]);

            // 2) Activar/crear los seleccionados
            foreach ($allowedIds as $rubroId) {
                AreaBudgetItem::updateOrCreate(
                    [
                        'area_id'        => $area->id,
                        'budget_item_id' => $rubroId,
                    ],
                    [
                        'active'     => true,
                        // conserva created_by si ya existía
                        'created_by' => DB::raw('COALESCE(created_by,' . (int)auth()->id() . ')'),
                        'updated_by' => auth()->id(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return redirect()
            ->route('gdf.subdirection.area_budget_items.edit', ['areaId' => $area->id, 'year' => $year])
            ->with('success', 'Rubros permitidos actualizados correctamente.');
    }
}
