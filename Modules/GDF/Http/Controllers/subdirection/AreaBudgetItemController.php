<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\BudgetItem;
use Modules\GDF\Entities\AreaBudgetItem;

class AreaBudgetItemController extends Controller
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

    private function ensureTableOrFail(string $table): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            abort(500, "Tabla requerida no existe: {$table}");
        }
    }

    /**
     * GET /gdf/subdirection/areas/{area}/rubros
     * Route param name MUST be {area}
     */
    public function edit(Request $request, int $area)
    {
        $this->guardSubdirection();

        $this->ensureTableOrFail('areas');
        $this->ensureTableOrFail('budget_items');
        $this->ensureTableOrFail('area_budget_items');
        $this->ensureTableOrFail('budgets');

        $area = Area::findOrFail($area);

        $q    = trim((string) $request->get('q', ''));
        $year = (int) ($request->get('year') ?: now()->year);

        // 1) Catálogo de rubros
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

        $rubroIds = $rubros->pluck('id')->all();

        // 2) Rubros permitidos por área (config)
        $current = AreaBudgetItem::query()
            ->where('area_id', $area->id)
            ->get()
            ->keyBy('budget_item_id');

        // 3) Vigencia por budgets: existe presupuesto activo en ese año/área/rubro
        $budgetRowsYear = collect();
        if (!empty($rubroIds)) {
            $budgetRowsYear = DB::table('budgets')
                ->select('budget_item_id', 'active', 'initial_amount', 'current_amount')
                ->where('area_id', $area->id)
                ->where('year', $year)
                ->whereIn('budget_item_id', $rubroIds)
                ->get()
                ->keyBy('budget_item_id');
        }

        $vigencyMap = collect($rubroIds)->mapWithKeys(function ($id) use ($budgetRowsYear) {
            $row = $budgetRowsYear->get($id);
            return [$id => (bool) ($row && (int) ($row->active ?? 0) === 1)];
        });

        // 4) Histórico por rubro (para badges/tabla en vista)
        $historyMap = collect();
        if (!empty($rubroIds)) {
            $historyMap = DB::table('budgets')
                ->select('budget_item_id', 'year', 'active', 'initial_amount', 'current_amount', 'area_id')
                ->where('area_id', $area->id)
                ->whereIn('budget_item_id', $rubroIds)
                ->orderByDesc('year')
                ->get()
                ->groupBy('budget_item_id');
        }

        // 5) Años disponibles para el selector (desde budgets)
        $yearsList = DB::table('budgets')
            ->where('area_id', $area->id)
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
     * POST /gdf/subdirection/areas/{area}/rubros
     * Route param name MUST be {area}
     */
    public function update(Request $request, int $area)
    {
        $this->guardSubdirection();

        $this->ensureTableOrFail('area_budget_items');
        $this->ensureTableOrFail('budgets');
        $this->ensureTableOrFail('areas');

        $area = Area::findOrFail($area);

        $data = $request->validate([
            'allowed'   => ['nullable', 'array'],
            'allowed.*' => ['integer', 'exists:budget_items,id'],
            'year'      => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int) ($data['year'] ?? now()->year);

        $allowedIds = collect($data['allowed'] ?? [])
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        // Validación: solo rubros con presupuesto activo para ese año/área
        if ($allowedIds->isNotEmpty()) {
            $vigentes = DB::table('budgets')
                ->where('area_id', $area->id)
                ->where('year', $year)
                ->whereIn('budget_item_id', $allowedIds->all())
                ->where('active', 1)
                ->pluck('budget_item_id')
                ->map(fn ($v) => (int) $v);

            $noVigentes = $allowedIds->diff($vigentes)->values();

            if ($noVigentes->isNotEmpty()) {
                return back()
                    ->withInput()
                    ->with('error', 'No puedes habilitar rubros sin presupuesto activo para el año ' . $year . ' en esta área.');
            }
        }

        DB::transaction(function () use ($area, $allowedIds) {

            // 1) Desactivar todos los existentes del área
            AreaBudgetItem::where('area_id', $area->id)->update([
                'active'     => false,
                'updated_by' => Auth::id(),
                'updated_at' => now(),
            ]);

            // 2) Activar/crear los seleccionados
            foreach ($allowedIds as $rubroId) {

                // si existe, lo reactivamos; si no, lo creamos con created_by
                $row = AreaBudgetItem::where('area_id', $area->id)
                    ->where('budget_item_id', $rubroId)
                    ->first();

                if ($row) {
                    $row->active = true;
                    $row->updated_by = Auth::id();
                    $row->updated_at = now();
                    $row->save();
                } else {
                    AreaBudgetItem::create([
                        'area_id'        => $area->id,
                        'budget_item_id' => $rubroId,
                        'active'         => true,
                        'created_by'     => Auth::id(),
                        'updated_by'     => Auth::id(),
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }
            }
        });

        // ✅ OJO: el nombre del parámetro de ruta es {area}
        return redirect()
            ->route('gdf.subdirection.areas.index', ['area' => $area->id, 'year' => $year])
            ->with('success', 'Rubros permitidos actualizados correctamente.');
    }
}
