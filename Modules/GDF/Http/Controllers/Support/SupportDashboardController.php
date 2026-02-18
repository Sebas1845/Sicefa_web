<?php

namespace Modules\GDF\Http\Controllers\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelSegment;
use Modules\GDF\Entities\TravelAllowance;
use Modules\GDF\Entities\TravelCost;
use Modules\GDF\Entities\TravelLog;
use Modules\SIGAC\Entities\ProgramRequest;

use Modules\GDF\Imports\MunicipalityRatesImport;
use Modules\GDF\Imports\VillageRatesImport;
use Modules\GDF\Exports\MissingMunicipalityRatesExport;
use Modules\GDF\Exports\MissingVillageRatesExport;

use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use Illuminate\Validation\ValidationException;

class SupportDashboardController extends Controller
{
    /* ============================================================
     * Helpers: área + prefijos + auth + schema
     * ============================================================ */

    private function areaKeyFromRequest(Request $request): string
    {
        return Str::contains($request->path(), 'support/campesena') ? 'campesena' : 'academic';
    }

    private function areaKeyFromPath(Request $request): string
    {
        $path = strtolower($request->path());

        if (Str::contains($path, 'campesena')) return 'campesena';
        if (Str::contains($path, 'academica') || Str::contains($path, 'academic')) return 'academic';

        return 'academic';
    }

    private function routePrefix(string $areaKey): string
    {
        return $areaKey === 'campesena'
            ? 'gdf.support.campesena'
            : 'gdf.support.academic';
    }

    private function userOr403()
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);
        return $user;
    }

    // Alias defensivo si quedaron rutas/métodos viejos
    protected function guardSubdirection(): void
    {
        $areaKey = $this->areaKeyFromRequest(request());
        $this->authorizeSupport($areaKey);
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function colExists(string $table, string $col): bool
    {
        return Schema::hasColumn($table, $col);
    }

    private function authorizeSupport(string $areaKey): void
    {
        $user = Auth::user();
        if (!$user) $this->deny(401, 'No autenticado');

        $areaKey = strtolower(trim($areaKey));

        if (!function_exists('checkRol')) $this->deny(403, 'Sin verificación de rol');

        if (checkRol('gdf.superadmin') || checkRol('superadmin')) return;

        if ($areaKey === 'campesena') {
            if (!checkRol('gdf.campesena_support')) $this->deny(403, 'Sin permisos (campesena)');
            return;
        }

        if ($areaKey === 'academic' || $areaKey === 'academica') {
            if (!checkRol('gdf.academic_support')) $this->deny(403, 'Sin permisos (academic)');
            return;
        }

        $this->deny(403, 'Área inválida');
    }

    private function blockedByStatus(TravelRequest $tr): bool
    {
        $st = (string) ($tr->status ?? '');
        return in_array($st, ['executed', 'confirmed'], true);
    }

    protected function motoBlockingStatuses(): array
    {
        return ['approved', 'delivered'];
    }

    private function requestHasMoto(int $travelRequestId): bool
    {
        return TravelSegment::query()
            ->where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->where('transport_type', 'moto')
            ->exists();
    }

    private function activeRequestMotoAssignment(int $travelRequestId): ?object
    {
        if (!Schema::hasTable('motorcycle_assignments')) return null;

        return DB::table('motorcycle_assignments')
            ->where('travel_requestable_type', TravelRequest::class)
            ->where('travel_requestable_id', $travelRequestId)
            ->whereNull('returned_at')
            ->orderByDesc('id')
            ->first();
    }

    private function closeActiveMotoAssignment(int $travelRequestId, string $note = ''): void
    {
        $active = $this->activeRequestMotoAssignment($travelRequestId);
        if (!$active) return;

        $upd = [
            'returned_at' => now(),
            'updated_at'  => now(),
        ];

        // Tu tabla NO tiene notes. Opcional: guardar nota en observations_in si existe.
        if ($note !== '' && $this->colExists('motorcycle_assignments', 'observations_in')) {
            $prev = (string) ($active->observations_in ?? '');
            $upd['observations_in'] = trim($prev . "\n" . $note);
        }

        DB::table('motorcycle_assignments')
            ->where('id', $active->id)
            ->update($upd);
    }

    private function assignMotoToRequestRange(
        TravelRequest $tr,
        int $motorcycleId,
        ?string $startAt,
        ?string $endAt,
        string $status = 'approved',
        string $note = ''
    ): void {
        if (!Schema::hasTable('motorcycle_assignments')) {
            throw new \RuntimeException('No existe la tabla motorcycle_assignments.');
        }

        $active = $this->activeRequestMotoAssignment($tr->id);

        // ya está esa misma moto activa
        if ($active && (int) $active->motorcycle_id === (int) $motorcycleId) {
            return;
        }

        // cerrar anterior
        if ($active) {
            $this->closeActiveMotoAssignment($tr->id, 'Cierre automático por nueva asignación.');
        }

        $insert = [
            'assignment_year' => (int) now()->year,
            'motorcycle_id'   => (int) $motorcycleId,
            'person_id'       => (int) ($tr->person_id ?? 0),
            'area_id'         => (int) ($tr->area_id ?? 0),
            'budget_item_id'  => (int) ($tr->budget_item_id ?? 0),

            'travel_requestable_type' => TravelRequest::class,
            'travel_requestable_id'   => (int) $tr->id,

            'start_at'   => $startAt,
            'end_at'     => $endAt,
            'status'     => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Opcional: guardar nota en observations_out si existe (tu tabla no tiene notes)
        if ($note !== '' && $this->colExists('motorcycle_assignments', 'observations_out')) {
            $insert['observations_out'] = $note;
        }

        DB::table('motorcycle_assignments')->insert($insert);
    }

    private function buildSegmentMotoMap(TravelRequest $tr, $segments): array
    {
        $map = [];

        $active = $this->activeRequestMotoAssignment($tr->id);
        $assignedId = $active ? (int) $active->motorcycle_id : null;

        $assignedPlate = null;
        if ($assignedId && Schema::hasTable('motorcycles')) {
            $m = DB::table('motorcycles')->where('id', $assignedId)->first();
            $assignedPlate = $m->plate ?? null;
        }

        $availableQ = DB::table('motorcycles')
            ->where('current_area_id', $tr->area_id)
            ->where('status', 'available');

        $hasAvailable = (clone $availableQ)->exists();

        // opciones: available + (si existe) la asignada aunque no esté "available"
        $optionsQ = DB::table('motorcycles')
            ->where('current_area_id', $tr->area_id)
            ->orderBy('plate');

        $optionsQ->where(function ($w) use ($assignedId) {
            $w->where('status', 'available');
            if ($assignedId) $w->orWhere('id', $assignedId);
        });

        $options = $optionsQ->get();

        $canUseMoto = $hasAvailable || !empty($assignedId);

        foreach ($segments as $seg) {
            $map[$seg->id] = [
                'assigned_id'    => $assignedId,
                'assigned_plate' => $assignedPlate,
                'options'        => $options,
                'has_available'  => $hasAvailable,
                'can_use_moto'   => $canUseMoto,
                'options_count'  => (int) $options->count(),
            ];
        }

        return $map;
    }


    private function recalcRequestTotals(int $travelRequestId): void
    {
        $transport = (float) TravelSegment::where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->sum('transport_cost');

        $allowTotal = (float) TravelAllowance::where('travel_request_id', $travelRequestId)
            ->whereIn('status', ['draft', 'liquidated', 'approved'])
            ->sum('calculated_amount');

        TravelRequest::where('id', $travelRequestId)->update([
            'total_transport' => $transport,
            'total_per_diem'  => $allowTotal,
            'total_other'     => 0,
            'total_amount'    => $transport + $allowTotal,
            'updated_at'      => now(),
        ]);
    }
    private function recalcTotals(int $travelRequestId): void
    {
        $segments = TravelSegment::query()
            ->where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->get();

        $transport  = (float) $segments->sum('transport_cost');
        $perDiemSeg = (float) $segments->sum('per_diem_cost');
        $otherSeg   = (float) $segments->sum('other_cost');

        $allowTotal = (float) TravelAllowance::query()
            ->where('travel_request_id', $travelRequestId)
            ->whereIn('status', ['draft', 'liquidated', 'approved'])
            ->sum('calculated_amount');

        $totalPerDiem = $perDiemSeg + $allowTotal;
        $totalOther   = $otherSeg;
        $totalAmount  = $transport + $totalPerDiem + $totalOther;

        TravelRequest::query()
            ->where('id', $travelRequestId)
            ->update([
                'total_transport' => $transport,
                'total_per_diem'  => $totalPerDiem,
                'total_other'     => $totalOther,
                'total_amount'    => $totalAmount,
                'updated_at'      => now(),
            ]);
    }

    private function suggestFuelRate(TravelRequest $tr): array
    {
        if (!$this->requestHasMoto($tr->id)) return [0, 'Sin moto'];

        // Placeholder si no tienes tabla/logic real para consumo:
        $rate = 50000;
        return [$rate, 'Sugerido'];
    }

    protected function suggestedFuelForRequest(TravelRequest $tr): array
    {
        $rateSuggested = 0.0;
        $rateLabel = null;

        $first = TravelSegment::query()
            ->where('travel_request_id', $tr->id)
            ->where('is_cancelled', 0)
            ->orderBy('departure_at')
            ->first();

        if (!$first) return [$rateSuggested, $rateLabel];

        // vereda
        if ((string) ($first->destination_type ?? '') === 'village' && !empty($first->village_id)) {
            $vr = DB::table('village_rates')
                ->where('active', 1)
                ->where('village_id', (int) $first->village_id)
                ->first();

            if ($vr) {
                $rateSuggested = (float) ($vr->motorcycle_amount ?? 0);
                $rateLabel = 'Vereda: ' . ($vr->village_name ?? 'N/D');
            }

            return [$rateSuggested, $rateLabel];
        }

        // municipio
        if (!empty($first->municipality_id)) {
            $mr = DB::table('municipality_rates')
                ->where('active', 1)
                ->where('municipality_id', (int) $first->municipality_id)
                ->first();

            if ($mr) {
                $rateSuggested = (float) ($mr->motorcycle_amount ?? 0);
                $rateLabel = 'Municipio: ' . ($mr->municipality_name ?? 'N/D');
            }
        }

        return [$rateSuggested, $rateLabel];
    }

    private function resolveRateForSegment(TravelSegment $seg, string $mode): float
    {
        $col = match ($mode) {
            'van'        => 'van_amount',
            'motorcycle' => 'motorcycle_amount',
            default      => 'bus_amount',
        };

        if ((string) ($seg->destination_type ?? '') === 'village' && !empty($seg->village_id)) {
            $amount = DB::table('village_rates')
                ->where('active', 1)
                ->where('village_id', (int) $seg->village_id)
                ->value($col);

            return $amount !== null ? (float) $amount : 0.0;
        }

        if (!empty($seg->municipality_id)) {
            $amount = DB::table('municipality_rates')
                ->where('active', 1)
                ->where('municipality_id', (int) $seg->municipality_id)
                ->value($col);

            return $amount !== null ? (float) $amount : 0.0;
        }

        return 0.0;
    }


    public function index(Request $request)
    {
        $this->userOr403();

        $areaKey = $this->areaKeyFromRequest($request);
        $tab  = (string) $request->get('tab', 'pending');
        $q    = trim((string) $request->get('q', ''));
        $year = (int) $request->get('year', now()->year);

        $this->authorizeSupport($areaKey);

        $routePrefix = $this->routePrefix($areaKey);
        $title       = $areaKey === 'campesena' ? 'Apoyo Campesena' : 'Apoyo Coordinación Académica';

        $base = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('source', 'sigac')
            ->whereYear('start_date', $year);

        $tab = in_array($tab, ['pending', 'seen', 'returned', 'treasury', 'executed', 'all'], true) ? $tab : 'pending';

        if ($tab === 'pending') {
            $base->where('status', 'submitted');
        } elseif ($tab === 'seen') {
            $base->where('status', 'approved');
        } elseif ($tab === 'returned') {
            $base->where('status', 'returned');
        } elseif ($tab === 'treasury') {
            $base->where('status', 'pending_treasury');
        } elseif ($tab === 'executed') {
            $base->where('status', 'executed');
        }

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q);
                    $w->orWhere('source_request_id', (int) $q);
                }
                $w->orWhere('radicado_code', 'like', "%{$q}%")
                    ->orWhere('origin', 'like', "%{$q}%")
                    ->orWhere('destination', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        $quickRequests = (clone $base)->orderByDesc('id')->limit(8)->get();

        $requests = (clone $base)
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        $programIds = $requests->getCollection()
            ->pluck('source_request_id')
            ->filter(fn($v) => (int) $v > 0)
            ->unique()
            ->values()
            ->all();

        $programsById = [];
        if (!empty($programIds)) {
            $programs = ProgramRequest::query()
                ->with(['person', 'area', 'budgetItem', 'municipality', 'village', 'program'])
                ->whereIn('id', $programIds)
                ->get();
            $programsById = $programs->keyBy('id')->all();
        }

        $kpisBase = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('source', 'sigac')
            ->whereYear('start_date', $year);

        $kpis = [
            'pending_support'  => (clone $kpisBase)->where('status', 'submitted')->count(),
            'seen_support'     => (clone $kpisBase)->where('status', 'approved')->count(),
            'returned'         => (clone $kpisBase)->where('status', 'returned')->count(),
            'pending_treasury' => (clone $kpisBase)->where('status', 'pending_treasury')->count(),
            'scheduled'        => (clone $kpisBase)->where('status', 'executed')->count(),
        ];


        $areaIds = [];
        if (Schema::hasTable('areas')) {
            $areaIds = DB::table('areas')
                ->where('name', 'like', $areaKey === 'campesena' ? '%CAMPESENA%' : '%ACAD%')
                ->pluck('id')
                ->map(fn($v) => (int) $v)
                ->all();
        }

        $actionBudgetIds = [];
        $assignedBudgetItemIds = [];

        $personId = (int) (auth()->user()->person->id ?? 0);

        // (5.1) Rubros asignados (si la tabla y la columna existen)
        if ($personId > 0 && Schema::hasTable('person_area_budget_assignments')) {
            try {
                $cols = Schema::getColumnListing('person_area_budget_assignments');
                if (in_array('budget_item_id', $cols, true)) {
                    $assignedBudgetItemIds = DB::table('person_area_budget_assignments')
                        ->where('person_id', $personId)
                        ->where('active', 1)
                        ->pluck('budget_item_id')
                        ->map(fn($v) => (int)$v)
                        ->all();
                }
            } catch (\Throwable $e) {
                $assignedBudgetItemIds = [];
            }
        }

        if (Schema::hasTable('budgets')) {
            $actionBudgetIds = DB::table('budgets')
                ->where('year', $year)
                ->where('active', 1)
                ->when(!empty($areaIds), fn($qq) => $qq->whereIn('area_id', $areaIds))
                ->when(!empty($assignedBudgetItemIds), fn($qq) => $qq->whereIn('budget_item_id', $assignedBudgetItemIds))
                ->pluck('id')
                ->map(fn($v) => (int)$v)
                ->all();
        }

        $available = 0.0;
        $executed  = 0.0;

        if (Schema::hasTable('budgets')) {
            $available = (float) DB::table('budgets')
                ->where('year', $year)
                ->where('active', 1)
                ->when(!empty($actionBudgetIds), fn($qq) => $qq->whereIn('id', $actionBudgetIds))
                ->sum('current_amount');
        }

        if (Schema::hasTable('budget_movements') && Schema::hasTable('budgets')) {
            $executed = (float) DB::table('budget_movements as bm')
                ->join('budgets as b', 'b.id', '=', 'bm.budget_id')
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->where('bm.type', 'execute')
                ->when(!empty($actionBudgetIds), fn($qq) => $qq->whereIn('b.id', $actionBudgetIds))
                ->sum('bm.amount');
        }

        $total = $available + $executed;

        $availableBudget = (int) round($available);
        $executedBudget  = (int) round($executed);
        $totalBudget     = (int) round($total);

        $budgetChart = [
            'executed'  => $executedBudget,
            'available' => $availableBudget,
            'total'     => $totalBudget,
        ];
        $budgetOptions = collect();

        if (Schema::hasTable('budgets') && Schema::hasTable('budget_items') && Schema::hasTable('areas')) {
            $budgetOptions = DB::table('budgets')
                ->join('areas', 'areas.id', '=', 'budgets.area_id')
                ->join('budget_items', 'budget_items.id', '=', 'budgets.budget_item_id')
                ->select(
                    'budgets.id',
                    'areas.name as area_name',
                    'budget_items.code as item_code',
                    'budget_items.name as item_name',
                    'budgets.current_amount'
                )
                ->where('budgets.year', $year)
                ->where('budgets.active', 1)
                ->when(!empty($actionBudgetIds), fn($qq) => $qq->whereIn('budgets.id', $actionBudgetIds))
                ->orderBy('budget_items.code')
                ->get();
        }

        $areaAllocChart = ['labels' => [], 'values' => [], 'total' => 0];

        if (Schema::hasTable('budget_area_allocations') && Schema::hasTable('budgets') && Schema::hasTable('areas')) {
            $rows = DB::table('budget_area_allocations as baa')
                ->join('budgets as b', 'b.id', '=', 'baa.budget_id')
                ->join('areas as a', 'a.id', '=', 'baa.area_id')
                ->selectRaw("a.name as area_name, SUM(baa.allocated_amount) as allocated_sum")
                ->where('b.year', $year)
                ->where('b.active', 1)
                ->where('baa.active', 1)
                ->when(!empty($actionBudgetIds), fn($qq) => $qq->whereIn('b.id', $actionBudgetIds))
                ->groupBy('a.name')
                ->orderByDesc('allocated_sum')
                ->get();

            $labels = [];
            $values = [];
            $tot = 0.0;

            foreach ($rows as $r) {
                $labels[] = (string) $r->area_name;
                $val = (float) ($r->allocated_sum ?? 0);
                $values[] = (int) round($val);
                $tot += $val;
            }

            $areaAllocChart = [
                'labels' => $labels,
                'values' => $values,
                'total'  => (int) round($tot),
            ];
        }

        $confirmedByAreaChart = ['labels' => [], 'values' => [], 'total' => 0];

        if (Schema::hasTable('travel_requests') && Schema::hasTable('areas')) {
            $cAreaRows = DB::table('travel_requests as tr')
                ->join('areas as a', 'a.id', '=', 'tr.area_id')
                ->where('tr.module', 'sitrav')
                ->where('tr.source', 'sigac')
                ->whereYear('tr.start_date', $year)
                ->where('tr.status', 'confirmed')
                ->when(!empty($areaIds), fn($qq) => $qq->whereIn('tr.area_id', $areaIds))
                ->selectRaw("a.name as area_name, SUM(tr.total_amount) as total_confirmed")
                ->groupBy('a.name')
                ->orderByDesc('total_confirmed')
                ->get();

            $labels = [];
            $values = [];
            $tot = 0.0;

            foreach ($cAreaRows as $r) {
                $labels[] = (string) $r->area_name;
                $val = (float) ($r->total_confirmed ?? 0);
                $values[] = (int) round($val);
                $tot += $val;
            }

            $confirmedByAreaChart = [
                'labels' => $labels,
                'values' => $values,
                'total'  => (int) round($tot),
            ];
        }

        $confirmedByItemChart = ['labels' => [], 'values' => [], 'actionBudgets' => [], 'total' => 0];
        $topConfirmedRubros   = collect();

        if (Schema::hasTable('travel_requests') && Schema::hasTable('budget_items')) {

            $confirmedItemRows = DB::table('travel_requests as tr')
                ->join('budget_items as bi', 'bi.id', '=', 'tr.budget_item_id')
                ->where('tr.module', 'sitrav')
                ->where('tr.source', 'sigac')
                ->whereYear('tr.start_date', $year)
                ->where('tr.status', 'confirmed')
                ->when(!empty($areaIds), fn($qq) => $qq->whereIn('tr.area_id', $areaIds))
                ->selectRaw("tr.budget_item_id, bi.code, bi.name, SUM(tr.total_amount) as total_confirmed")
                ->groupBy('tr.budget_item_id', 'bi.code', 'bi.name')
                ->orderByDesc('total_confirmed')
                ->limit(12)
                ->get();

            $actionBudgetByItem = [];

            if (Schema::hasTable('budgets')) {
                $bmExists = Schema::hasTable('budget_movements');

                $bRows = DB::table('budgets as b')
                    ->join('budget_items as bi', 'bi.id', '=', 'b.budget_item_id')
                    ->where('b.year', $year)
                    ->where('b.active', 1)
                    ->when(!empty($actionBudgetIds), fn($qq) => $qq->whereIn('b.id', $actionBudgetIds))
                    ->when($bmExists, function ($qq) {
                        $qq->leftJoin('budget_movements as bm', function ($j) {
                            $j->on('bm.budget_id', '=', 'b.id')
                                ->where('bm.type', '=', 'execute');
                        });
                    })
                    ->selectRaw("
                    b.budget_item_id,
                    SUM(b.current_amount) as sum_current,
                    " . ($bmExists ? "COALESCE(SUM(bm.amount),0)" : "0") . " as sum_executed
                ")
                    ->groupBy('b.budget_item_id')
                    ->get();

                foreach ($bRows as $r) {
                    $actionBudgetByItem[(int) $r->budget_item_id] =
                        (float) ($r->sum_current ?? 0) + (float) ($r->sum_executed ?? 0);
                }
            }

            $labels = [];
            $values = [];
            $action = [];
            $tot = 0.0;

            foreach ($confirmedItemRows as $r) {
                $id = (int) $r->budget_item_id;

                $label = trim(($r->code ?? '') . ' ' . ($r->name ?? 'Rubro'));
                $confirmed = (float) ($r->total_confirmed ?? 0);
                $actionB   = (float) ($actionBudgetByItem[$id] ?? 0);

                // ✅ solo rubros con acción real
                if ($actionB <= 0 && $confirmed <= 0) continue;

                $labels[] = $label;
                $values[] = (int) round($confirmed);
                $action[] = (int) round($actionB);
                $tot += $confirmed;
            }

            $confirmedByItemChart = [
                'labels'        => $labels,
                'values'        => $values,
                'actionBudgets' => $action,
                'total'         => (int) round($tot),
            ];

            $topConfirmedRubros = collect($confirmedItemRows)->map(function ($r) use ($actionBudgetByItem) {
                $id = (int) $r->budget_item_id;
                $confirmed = (float) ($r->total_confirmed ?? 0);
                $actionB = (float) ($actionBudgetByItem[$id] ?? 0);

                if ($actionB <= 0 && $confirmed <= 0) return null;

                $pct = $actionB > 0 ? round(($confirmed / $actionB) * 100, 1) : null;

                return (object) [
                    'budget_item_id' => $id,
                    'code' => (string) ($r->code ?? ''),
                    'name' => (string) ($r->name ?? ''),
                    'confirmed' => $confirmed,
                    'action_budget' => $actionB,
                    'pct' => $pct,
                ];
            })->filter()->sortByDesc('confirmed')->take(10)->values();
        }

        return view('gdf::support.dashboard', compact(
            'areaKey',
            'routePrefix',
            'title',

            'requests',
            'quickRequests',
            'tab',
            'q',
            'year',
            'kpis',
            'programsById',

            'availableBudget',
            'executedBudget',
            'totalBudget',
            'budgetChart',

            'budgetOptions',
            'areaAllocChart',

            'confirmedByAreaChart',
            'confirmedByItemChart',
            'topConfirmedRubros'
        ));
    }



    public function show(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::with(['person'])->findOrFail($travelRequest);

        $areaKey = (string) ($request->get('areaKey') ?: (str_contains($request->path(), 'campesena') ? 'campesena' : 'academic'));
        $this->authorizeSupport($areaKey);

        $routePrefix = $this->routePrefix($areaKey);

        // ✅ Cargar SIGAC si aplica
        $pr = null;
        if (($tr->module ?? '') === 'sitrav' && ($tr->source ?? '') === 'sigac' && !empty($tr->source_request_id)) {
            $pr = \Modules\SIGAC\Entities\ProgramRequest::query()
                ->with(['person', 'area', 'budgetItem', 'municipality', 'village', 'program'])
                ->find((int) $tr->source_request_id);
        }

        $segments = TravelSegment::query()
            ->where('travel_request_id', $tr->id)
            ->orderBy('departure_at')
            ->get();

        $allowances = TravelAllowance::query()
            ->where('travel_request_id', $tr->id)
            ->orderByDesc('id')
            ->get();

        $segmentMoto = $this->buildSegmentMotoMap($tr, $segments);

        [$rateSuggested, $rateLabel] = $this->suggestFuelRate($tr);

        $logs = \Modules\GDF\Entities\TravelLog::query()
            ->where('travel_request_id', $tr->id)
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        // ✅ NUEVO: traducir acción + descripción con service
        $translator = app(\Modules\GDF\Services\TravelLogTranslator::class);

        $logs = $logs->map(function ($lg) use ($translator) {
            $lg->action_label = $translator->actionLabel($lg->action ?? null);
            $lg->description_label = $translator->descriptionLabel($lg->description ?? null);
            return $lg;
        });

        return view('gdf::support.dashboard.show', compact(
            'tr',
            'pr',
            'segments',
            'allowances',
            'segmentMoto',
            'areaKey',
            'routePrefix',
            'rateSuggested',
            'rateLabel',
            'logs'
        ));
    }



    public function markSeen(Request $request, TravelRequest $travelRequest)
    {
        $this->userOr403();

        $areaKey = $this->areaKeyFromRequest($request);
        $routePrefix = $this->routePrefix($areaKey);

        if ($travelRequest->status !== 'submitted') {
            return back()->with('error', 'Solo puedes marcar visto una solicitud en estado submitted.');
        }

        DB::transaction(function () use ($travelRequest) {
            $travelRequest->status = 'approved';
            $travelRequest->approved_at = $travelRequest->approved_at ?: now();
            $travelRequest->save();

            if (class_exists(\Modules\GDF\Entities\TravelLog::class)) {
                TravelLog::create([
                    'travel_request_id' => $travelRequest->id,
                    'user_id' => Auth::id(),
                    'action' => 'support_seen',
                    'description' => 'Apoyo marcó Visto/Validado.',
                ]);
            }
        });

        return redirect()
            ->route($routePrefix . '.requests.show', $travelRequest->id)
            ->with('success', 'Solicitud marcada como VISTO/VALIDADA (status: approved).');
    }

    public function returnToInstructor(Request $request, TravelRequest $travelRequest)
    {
        $this->userOr403();

        $areaKey = $this->areaKeyFromRequest($request);
        $routePrefix = $this->routePrefix($areaKey);

        $v = Validator::make($request->all(), [
            'support_notes' => ['required', 'string', 'min:5', 'max:4000'],
        ], [], [
            'support_notes' => 'motivo',
        ]);

        if ($v->fails()) return back()->withErrors($v)->withInput();

        DB::transaction(function () use ($travelRequest, $request) {
            $travelRequest->status = 'returned';
            $travelRequest->save();

            $this->logAction(
                (int) $travelRequest->id,
                'support_returned',
                (string) $request->support_notes
            );
        });

        return redirect()
            ->route($routePrefix . '.requests.show', $travelRequest->id)
            ->with('success', 'Solicitud devuelta al instructor (status: returned).');
    }

    private function logAction(int $travelRequestId, string $action, ?string $description = null, ?int $userId = null): void
    {
        // si no existe la entidad, usa DB::table directo
        if (class_exists(\Modules\GDF\Entities\TravelLog::class)) {
            \Modules\GDF\Entities\TravelLog::create([
                'travel_request_id' => $travelRequestId,
                'user_id'           => $userId ?? (int) Auth::id(),
                'action'            => $action,
                'description'       => $description,
            ]);
            return;
        }

        // fallback por si no hay modelo
        DB::table('travel_logs')->insert([
            'travel_request_id' => $travelRequestId,
            'user_id'           => $userId ?? (int) Auth::id(),
            'action'            => $action,
            'description'       => $description,
            'created_at'        => now(),
        ]);
    }


    public function sendToTreasury(Request $request, TravelRequest $travelRequest)
    {
        $this->userOr403();

        $areaKey = $this->areaKeyFromRequest($request);
        $routePrefix = $this->routePrefix($areaKey);

        $status = (string) ($travelRequest->status ?? '');
        if ($status !== 'approved') {
            return back()->with('error', "Primero valida (approved). Estado actual: {$status}");
        }

        DB::transaction(function () use ($travelRequest) {
            $travelRequest->status = 'pending_treasury';

            if (Schema::hasColumn('travel_requests', 'sent_to_treasury_at')) {
                $travelRequest->sent_to_treasury_at = now();
            }

            $travelRequest->save();

            // ✅ bitácora (NO tocar notes)
            $this->logAction(
                (int) $travelRequest->id,
                'support_to_treasury',
                'Apoyo envió a Tesorería.'
            );
        });

        return redirect()
            ->route($routePrefix . '.requests.index', ['tab' => 'treasury'])
            ->with('success', 'Enviado a Tesorería (status: pending_treasury).');
    }



    public function segmentsUpdate(TravelRequest $travelRequest, Request $request, int $segmentId)
    {
        $this->userOr403();

        $areaKey     = $this->areaKeyFromRequest($request);
        $routePrefix = $this->routePrefix($areaKey);

        /** @var \Modules\GDF\Entities\TravelRequest $tr */
        $tr = TravelRequest::query()->findOrFail($travelRequest->id);

        /** @var \Modules\GDF\Entities\TravelSegment $seg */
        $seg = TravelSegment::query()
            ->where('travel_request_id', $tr->id)
            ->where('id', $segmentId)
            ->firstOrFail();

        // ✅ Validación: one_way_cost solo se exige cuando NO es moto
        $data = $request->validate([
            'is_cancelled'   => ['nullable', 'boolean'],
            'change_reason'  => ['nullable', 'string', 'max:255'],
            'transport_type' => ['required', 'in:terrestre,aereo,camioneta,moto'],

            // si es moto, no lo exijas
            'one_way_cost'   => ['nullable', 'numeric', 'min:0', 'required_if:transport_type,terrestre,aereo,camioneta'],

            'motorcycle_id'  => ['nullable', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($tr, $seg, $data) {

            // =========================
            // 1) Flags / cancelación
            // =========================
            $seg->is_cancelled  = !empty($data['is_cancelled']) ? 1 : 0;
            $seg->change_reason = $seg->is_cancelled ? ($data['change_reason'] ?? $seg->change_reason) : null;

            // trips según trip_type
            $tripType = (string) ($seg->trip_type ?? 'round_trip');
            $seg->trips = match ($tripType) {
                'one_way'    => 1,
                'two_way'    => 1,
                'round_trip' => 2,
                default      => 2,
            };

            // Si cancelas, todo en 0 (pero igual recalculamos al final del transaction)
            if ($seg->is_cancelled) {
                $seg->transport_type = $data['transport_type'];
                $seg->transport_cost = 0;
                $seg->per_diem_cost  = 0;
                $seg->other_cost     = 0;
                $seg->total_cost     = 0;
                $seg->save();

                // ✅ Recalcular TR + sync TravelCost transport
                $this->recalcRequestTotals($tr->id);
                $this->syncTransportCostRow($tr->id);

                return;
            }

            // =========================
            // 2) Transporte
            // =========================
            $seg->transport_type = $data['transport_type'];

            if ($data['transport_type'] === 'moto') {

                $active = $this->activeRequestMotoAssignment($tr->id);
                $assignedId = $active ? (int) $active->motorcycle_id : 0;

                $motoId = (int) ($data['motorcycle_id'] ?? 0);
                if ($motoId <= 0 && $assignedId > 0) {
                    $motoId = $assignedId;
                }

                if ($motoId <= 0) {
                    $availableCount = DB::table('motorcycles')
                        ->where('current_area_id', $tr->area_id)
                        ->where('status', 'available')
                        ->count();

                    if ($availableCount <= 0) {
                        throw ValidationException::withMessages([
                            'motorcycle_id' => 'No hay motos disponibles en esta área (y la solicitud no tiene una moto asignada).',
                        ]);
                    }

                    throw ValidationException::withMessages([
                        'motorcycle_id' => 'Debes seleccionar una moto cuando el modo es Moto.',
                    ]);
                }

                $belongsToArea = DB::table('motorcycles')
                    ->where('id', $motoId)
                    ->where('current_area_id', $tr->area_id)
                    ->exists();

                if (!$belongsToArea && $motoId !== $assignedId) {
                    throw ValidationException::withMessages([
                        'motorcycle_id' => 'La moto seleccionada no pertenece al área de la solicitud.',
                    ]);
                }

                $startAt = $seg->departure_at ? Carbon::parse($seg->departure_at)->format('Y-m-d H:i:s') : null;
                $endAt   = $seg->return_at    ? Carbon::parse($seg->return_at)->format('Y-m-d H:i:s') : null;

                if (!$startAt || !$endAt) {
                    throw ValidationException::withMessages([
                        'motorcycle_id' => 'El segmento debe tener departure_at y return_at para asignar moto.',
                    ]);
                }

                $blockingStatuses = $this->motoBlockingStatuses();

                $hasOverlap = DB::table('motorcycle_assignments as a')
                    ->where('a.motorcycle_id', $motoId)
                    ->whereNull('a.returned_at')
                    ->whereIn('a.status', $blockingStatuses)
                    ->whereNotNull('a.start_at')
                    ->whereNotNull('a.end_at')
                    ->where(function ($w) use ($startAt, $endAt) {
                        $w->whereBetween('a.start_at', [$startAt, $endAt])
                            ->orWhereBetween('a.end_at', [$startAt, $endAt])
                            ->orWhere(function ($ww) use ($startAt, $endAt) {
                                $ww->where('a.start_at', '<=', $startAt)
                                    ->where('a.end_at', '>=', $endAt);
                            });
                    })
                    ->exists();

                if ($hasOverlap) {
                    throw ValidationException::withMessages([
                        'motorcycle_id' => 'Esa moto ya está asignada en el rango del segmento.',
                    ]);
                }

                $this->assignMotoToRequestRange(
                    $tr,
                    $motoId,
                    $startAt,
                    $endAt,
                    'approved',
                    'Apoyo: asignación por segmento (moto)'
                );

                // Moto => transporte 0
                $seg->transport_cost = 0;
            } else {

                $oneWay = (float) ($data['one_way_cost'] ?? 0);
                $seg->transport_cost = $oneWay * (int) $seg->trips;
            }

            $seg->total_cost = (float) $seg->transport_cost
                + (float) $seg->per_diem_cost
                + (float) $seg->other_cost;

            $seg->save();

            $this->recalcRequestTotals($tr->id);
            $this->syncTransportCostRow($tr->id);
        });

        return redirect()
            ->route($routePrefix . '.requests.show', ['travelRequest' => $tr->id])
            ->with('success', 'Segmento actualizado y recalculado correctamente.');
    }



    public function suggestSegmentRate(Request $request, TravelRequest $travelRequest, $segmentId)
    {
        $this->userOr403();

        $segment = TravelSegment::query()
            ->where('id', (int) $segmentId)
            ->where('travel_request_id', $travelRequest->id)
            ->firstOrFail();

        $v = Validator::make($request->all(), [
            'mode' => ['required', 'in:bus,van,motorcycle'],
        ]);

        if ($v->fails()) {
            return response()->json(['ok' => false, 'errors' => $v->errors()], 422);
        }

        $amount = $this->resolveRateForSegment($segment, (string) $request->mode);

        return response()->json([
            'ok' => true,
            'suggested_amount' => (float) $amount
        ]);
    }

    /* ============================================================
     * TARIFAS (INDEX/UPSERT/EXPORT/IMPORT)
     * ============================================================ */

    public function ratesIndex(Request $request)
    {
        $this->userOr403();

        $areaKey     = $this->areaKeyFromRequest($request);
        $routePrefix = $this->routePrefix($areaKey);

        $countryId = (int) config('gdf.country_id', 25);
        $departmentId = (int) $request->get('department_id', 0);

        $departments = DB::table('departments')
            ->select('id', 'name')
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->get();

        $municipalities = DB::table('municipality_rates as r')
            ->join('municipalities as m', 'm.id', '=', 'r.municipality_id')
            ->join('departments as d', 'd.id', '=', 'm.department_id')
            ->where('r.active', 1)
            ->where('d.country_id', $countryId)
            ->when($departmentId > 0, fn($q) => $q->where('m.department_id', $departmentId))
            ->select(
                'r.id as rate_id',
                'm.id as municipality_id',
                'm.name',
                'm.department_id',
                'd.name as department_name',
                'r.bus_amount',
                'r.van_amount',
                'r.motorcycle_amount',
                'r.air_amount'
            )
            ->orderBy('d.name')
            ->orderBy('m.name')
            ->get();

        $villages = DB::table('village_rates as r')
            ->join('villages as v', 'v.id', '=', 'r.village_id')
            ->join('municipalities as m', 'm.id', '=', 'v.municipality_id')
            ->join('departments as d', 'd.id', '=', 'm.department_id')
            ->where('r.active', 1)
            ->where('d.country_id', $countryId)
            ->when($departmentId > 0, fn($q) => $q->where('m.department_id', $departmentId))
            ->select(
                'r.id as rate_id',
                'v.id as village_id',
                'v.name',
                'm.id as municipality_id',
                'm.name as municipality_name',
                'm.department_id',
                'd.name as department_name',
                'r.bus_amount',
                'r.van_amount',
                'r.motorcycle_amount'
            )
            ->orderBy('d.name')
            ->orderBy('m.name')
            ->orderBy('v.name')
            ->get();

        return view('gdf::support.rates.index', compact(
            'routePrefix',
            'municipalities',
            'villages',
            'areaKey',
            'departments',
            'departmentId'
        ));
    }

    public function ratesUpsert(Request $request)
    {
        $this->userOr403();

        $data = $request->validate([
            'type' => ['required', 'in:municipality,village'],
            'id'   => ['required', 'integer', 'min:1'],
            'bus_amount'        => ['nullable', 'numeric', 'min:0'],
            'van_amount'        => ['nullable', 'numeric', 'min:0'],
            'motorcycle_amount' => ['nullable', 'numeric', 'min:0'],
            'air_amount'        => ['nullable', 'numeric', 'min:0'],
        ]);

        $now = now();
        $bus  = (float) ($data['bus_amount'] ?? 0);
        $van  = (float) ($data['van_amount'] ?? 0);
        $moto = (float) ($data['motorcycle_amount'] ?? 0);
        $air  = (float) ($data['air_amount'] ?? 0);

        DB::beginTransaction();
        try {
            if ($data['type'] === 'municipality') {
                $municipalityId = (int) $data['id'];

                $munName = DB::table('municipalities')->where('id', $municipalityId)->value('name');
                if (!$munName) return back()->with('error', 'Municipio no encontrado.');

                DB::table('municipality_rates')
                    ->where('municipality_id', $municipalityId)
                    ->where('active', 1)
                    ->update(['active' => 0, 'updated_at' => $now]);

                DB::table('municipality_rates')->insert([
                    'municipality_id'   => $municipalityId,
                    'municipality_name' => $munName,
                    'bus_amount'        => $bus,
                    'van_amount'        => $van,
                    'motorcycle_amount' => $moto,
                    'air_amount'        => $air,
                    'active'            => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            } else {
                $villageId = (int) $data['id'];

                $row = DB::table('villages as v')
                    ->leftJoin('municipalities as m', 'm.id', '=', 'v.municipality_id')
                    ->select('v.id', 'v.name as village_name', 'v.municipality_id', 'm.name as municipality_name')
                    ->where('v.id', $villageId)
                    ->first();

                if (!$row) return back()->with('error', 'Vereda no encontrada.');

                DB::table('village_rates')
                    ->where('village_id', $villageId)
                    ->where('active', 1)
                    ->update(['active' => 0, 'updated_at' => $now]);

                DB::table('village_rates')->insert([
                    'municipality_id'   => (int) $row->municipality_id,
                    'village_id'        => $villageId,
                    'village_name'      => (string) $row->village_name,
                    'municipality_name' => (string) ($row->municipality_name ?? ''),
                    'bus_amount'        => $bus,
                    'van_amount'        => $van,
                    'motorcycle_amount' => $moto,
                    'active'            => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            DB::commit();
            return back()->with('success', 'Tarifa guardada.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ratesUpsert error', ['msg' => $e->getMessage()]);
            return back()->with('error', app()->environment('local') ? $e->getMessage() : 'No se pudo guardar.');
        }
    }

    public function exportMissing(Request $request)
    {
        $this->userOr403();

        $type         = (string) $request->query('type', 'municipality');
        $departmentId = (int) $request->query('department_id', 0);

        if (!in_array($type, ['municipality', 'village'], true)) abort(422, 'type inválido');

        $CO_MIN = 405;
        $CO_MAX = 436;

        if ($departmentId > 0 && ($departmentId < $CO_MIN || $departmentId > $CO_MAX)) {
            return back()->with('error', 'Departamento fuera del rango Colombia (405–436).');
        }

        if (ob_get_length()) @ob_end_clean();

        if ($type === 'municipality') {
            $rows = DB::table('municipalities as m')
                ->join('departments as d', 'd.id', '=', 'm.department_id')
                ->leftJoin('municipality_rates as r', function ($j) {
                    $j->on('r.municipality_id', '=', 'm.id')->where('r.active', '=', 1);
                })
                ->when(
                    $departmentId > 0,
                    fn($q) => $q->where('m.department_id', $departmentId),
                    fn($q) => $q->whereBetween('m.department_id', [$CO_MIN, $CO_MAX])
                )
                ->whereNull('r.id')
                ->select(
                    'm.id as municipality_id',
                    'm.name as municipality_name',
                    'm.department_id',
                    'd.name as department_name'
                )
                ->selectRaw('0 as bus_amount, 0 as van_amount, 0 as motorcycle_amount, 0 as air_amount')
                ->orderBy('d.name')
                ->orderBy('m.name')
                ->get();

            $name = 'faltantes_municipios_colombia' . ($departmentId ? "_depto_{$departmentId}" : '') . '.xlsx';

            return Excel::download(new MissingMunicipalityRatesExport($rows), $name, ExcelWriter::XLSX);
        }

        $rows = DB::table('villages as v')
            ->join('municipalities as m', 'm.id', '=', 'v.municipality_id')
            ->join('departments as d', 'd.id', '=', 'm.department_id')
            ->leftJoin('village_rates as r', function ($j) {
                $j->on('r.village_id', '=', 'v.id')->where('r.active', '=', 1);
            })
            ->when(
                $departmentId > 0,
                fn($q) => $q->where('m.department_id', $departmentId),
                fn($q) => $q->whereBetween('m.department_id', [$CO_MIN, $CO_MAX])
            )
            ->whereNull('r.id')
            ->select(
                'v.id as village_id',
                'v.name as village_name',
                'm.id as municipality_id',
                'm.name as municipality_name',
                'm.department_id',
                'd.name as department_name'
            )
            ->selectRaw('0 as bus_amount, 0 as van_amount, 0 as motorcycle_amount')
            ->orderBy('d.name')
            ->orderBy('m.name')
            ->orderBy('v.name')
            ->get();

        $name = 'faltantes_veredas_colombia' . ($departmentId ? "_depto_{$departmentId}" : '') . '.xlsx';

        return Excel::download(new MissingVillageRatesExport($rows), $name, ExcelWriter::XLSX);
    }

    public function importRates(Request $request)
    {
        $this->userOr403();

        $data = $request->validate([
            'type' => ['required', 'in:municipality,village'],
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        try {
            if ($data['type'] === 'municipality') {
                Excel::import(new MunicipalityRatesImport(), $request->file('file'));
            } else {
                Excel::import(new VillageRatesImport(), $request->file('file'));
            }

            return back()->with('success', 'Excel importado correctamente.');
        } catch (\Throwable $e) {
            Log::error('importRates error', ['msg' => $e->getMessage()]);
            return back()->with('error', app()->environment('local') ? $e->getMessage() : 'No se pudo importar el Excel.');
        }
    }

    /* ============================================================
     * VIÁTICOS (SOPORTE)
     * ============================================================ */

    public function perDiemLiquidate(Request $request, TravelRequest $travelRequest)
    {
        $this->userOr403();

        $tr = TravelRequest::findOrFail($travelRequest->id);

        $data = $request->validate([
            'applies_to'   => ['required', 'in:staff'],
            'units'        => ['required', 'integer', 'min:1', 'max:60'],
            'unit_amount'  => ['required', 'numeric', 'min:0'],
            'description'  => ['nullable', 'string', 'max:255'],
            'confirm'      => ['accepted'],
        ]);

        $units = (int) $data['units'];
        $unitAmount = (float) $data['unit_amount'];
        $total = (float) round($units * $unitAmount);

        DB::transaction(function () use ($tr, $data, $units, $unitAmount, $total) {
            TravelAllowance::updateOrCreate(
                ['travel_request_id' => $tr->id, 'allowance_type' => 'per_diem'],
                [
                    'status'            => 'draft',
                    'applies_to'        => $data['applies_to'],
                    'units'             => $units,
                    'unit_amount'       => $unitAmount,
                    'calculated_amount' => $total,
                    'description'       => $data['description'] ?? 'Viático',
                    'updated_at'        => now(),
                ]
            );
        });

        $this->recalcRequestTotals($tr->id);

        return back()->with('success', 'Viático guardado y totales recalculados.');
    }

    /* ============================================================
     * LISTADO SOPORTE (requestsIndex) - si lo usas en otra vista
     * ============================================================ */

    public function requestsIndex(Request $request)
    {
        $this->userOr403();

        $areaKey = $this->areaKeyFromRequest($request);
        $this->authorizeSupport($areaKey);

        $routePrefix = $this->routePrefix($areaKey);

        $tab  = (string) $request->get('tab', 'pending');
        $q    = trim((string) $request->get('q', ''));
        $year = (int) $request->get('year', now()->year);
        $mod  = (string) $request->get('module', 'all');

        $areaIds = (array) config("gdf.area_groups.{$areaKey}", []);
        $tab = in_array($tab, ['pending', 'returned', 'treasury', 'executed', 'all'], true) ? $tab : 'pending';
        $mod = in_array($mod, ['all', 'gdf', 'sitrav'], true) ? $mod : 'all';

        $base = TravelRequest::query()
            ->whereYear('start_date', $year)
            ->when(!empty($areaIds), fn($qq) => $qq->whereIn('area_id', $areaIds))
            ->when($mod !== 'all', fn($qq) => $qq->where('module', $mod))
            ->with(['person', 'area', 'budgetItem']);

        if ($tab === 'pending')   $base->where('status', 'submitted');
        if ($tab === 'returned')  $base->where('status', 'returned');
        if ($tab === 'treasury')  $base->where('status', 'pending_treasury');
        if ($tab === 'executed')  $base->where('status', 'executed');

        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q)->orWhere('source_request_id', (int) $q);
                }
                $w->orWhere('radicado_code', 'like', "%{$q}%")
                    ->orWhere('origin', 'like', "%{$q}%")
                    ->orWhere('destination', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        $kpisBase = TravelRequest::query()
            ->whereYear('start_date', $year)
            ->when(!empty($areaIds), fn($qq) => $qq->whereIn('area_id', $areaIds))
            ->when($mod !== 'all', fn($qq) => $qq->where('module', $mod));

        $kpis = [
            'pending'  => (clone $kpisBase)->where('status', 'submitted')->count(),
            'returned' => (clone $kpisBase)->where('status', 'returned')->count(),
            'treasury' => (clone $kpisBase)->where('status', 'pending_treasury')->count(),
            'executed' => (clone $kpisBase)->where('status', 'executed')->count(),
        ];

        $requests = (clone $base)
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        return view('gdf::support.requests.index', compact(
            'routePrefix',
            'tab',
            'q',
            'year',
            'mod',
            'kpis',
            'requests',
            'areaKey'
        ));
    }

    /* ============================================================
     * MÉTODOS “legacy” que tenías (return/liquidate/upsert/destroy/reject)
     * ============================================================ */

    public function return(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::with(['person'])->findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'support_notes' => ['required', 'string', 'min:5', 'max:800'],
        ]);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }

        DB::transaction(function () use ($tr, $data) {
            $tr->status = 'returned';

            $line = '[APOYO] DEVUELTA: ' . trim($data['support_notes']) . ' (' . now()->format('Y-m-d H:i') . ')';
            $tr->notes = trim(($tr->notes ?? '') . "\n" . $line);

            $tr->save();
        });

        return back()->with('success', 'Solicitud devuelta al instructor.');
    }

    public function liquidatePerDiem(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }

        $data = $request->validate([
            'applies_to'  => ['required', 'in:staff'],
            'units'       => ['required', 'integer', 'min:1', 'max:60'],
            'unit_amount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'confirm'     => ['required', 'in:1'],
        ]);

        DB::transaction(function () use ($tr, $data) {
            $al = TravelAllowance::query()
                ->where('travel_request_id', $tr->id)
                ->where('allowance_type', 'per_diem')
                ->orderByDesc('id')
                ->first();

            if (!$al) {
                $al = new TravelAllowance();
                $al->travel_request_id = $tr->id;
                $al->allowance_type = 'per_diem';
            }

            $al->applies_to = 'staff';
            $al->units = (int) $data['units'];
            $al->unit_amount = (float) $data['unit_amount'];
            $al->calculated_amount = $al->units * $al->unit_amount;
            $al->description = $data['description'] ?? 'Viático';
            $al->status = 'draft';
            $al->save();

            $this->recalcTotals($tr->id);
        });

        return back()->with('success', 'Per diem guardado (draft).');
    }

    public function upsertAllowance(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }

        $data = $request->validate([
            'allowance_type' => ['required', 'in:meals,lodging,fuel'],
            'units'          => ['required', 'integer', 'min:1', 'max:200'],
            'unit_amount'    => ['required', 'numeric', 'min:0'],
            'description'    => ['nullable', 'string', 'max:255'],
            'confirm'        => ['required', 'in:1'],
        ]);

        if ($data['allowance_type'] === 'fuel' && !$this->requestHasMoto($tr->id)) {
            return back()->withInput()->with('warning', 'Gasolina solo aplica si existe al menos 1 segmento en moto.');
        }

        DB::transaction(function () use ($tr, $data) {
            $al = TravelAllowance::query()
                ->where('travel_request_id', $tr->id)
                ->where('allowance_type', $data['allowance_type'])
                ->orderByDesc('id')
                ->first();

            if (!$al) {
                $al = new TravelAllowance();
                $al->travel_request_id = $tr->id;
                $al->allowance_type = $data['allowance_type'];
            }

            $al->applies_to = 'staff';
            $al->units = (int) $data['units'];
            $al->unit_amount = (float) $data['unit_amount'];
            $al->calculated_amount = $al->units * $al->unit_amount;
            $al->description = $data['description'] ?? Str::title($data['allowance_type']);
            $al->status = 'draft';
            $al->save();

            $this->recalcTotals($tr->id);
        });

        return back()->with('success', 'Allowance guardado (draft).');
    }

    public function destroyAllowance(Request $request, int $travelRequest, int $allowance)
    {
        $tr = TravelRequest::findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }

        $al = TravelAllowance::query()
            ->where('travel_request_id', $tr->id)
            ->where('id', $allowance)
            ->firstOrFail();

        DB::transaction(function () use ($tr, $al) {
            $al->delete();
            $this->recalcTotals($tr->id);
        });

        return back()->with('success', 'Allowance eliminado.');
    }

    public function rejectAllowances(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'include_per_diem' => ['nullable', 'in:1'],
        ]);

        DB::transaction(function () use ($tr, $data) {
            $q = TravelAllowance::query()
                ->where('travel_request_id', $tr->id)
                ->whereIn('status', ['draft', 'liquidated', 'approved']);

            if (empty($data['include_per_diem'])) {
                $q->where('allowance_type', '!=', 'per_diem');
            }

            $q->update([
                'status' => 'rejected',
                'description' => DB::raw("CONCAT(COALESCE(description,''), ' | RECHAZADO: " . addslashes($data['reason']) . "')"),
                'updated_at' => now(),
            ]);

            $this->recalcTotals($tr->id);
        });

        return back()->with('success', 'Allowances rechazados.');
    }

    public function sendTreasury(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }
        if ((string) $tr->status !== 'approved') {
            return back()->with('warning', 'Solo aplica cuando TR está en approved.');
        }

        $tr->status = 'pending_treasury';
        $tr->save();

        return back()->with('success', 'Enviado a Tesorería (pending_treasury).');
    }

    public function seen(Request $request, int $travelRequest)
    {
        $tr = TravelRequest::findOrFail($travelRequest);

        $areaKey = ((int) $tr->area_id === 1) ? 'campesena' : 'academic';
        $this->authorizeSupport($areaKey);

        if ($this->blockedByStatus($tr)) {
            return back()->with('warning', 'Bloqueado por estado.');
        }
        if ((string) $tr->status !== 'submitted') {
            return back()->with('warning', 'Solo aplica cuando TR está en submitted.');
        }

        $tr->status = 'approved';
        $tr->approved_at = now();
        $tr->save();

        return back()->with('success', 'Validado: TR pasó a approved.');
    }


    public function documentsIndex($travelRequest)
    {
        // ✅ Debug temporal
        \Log::info('documentsIndex called', [
            'request_id' => $travelRequest,
            'user' => auth()->id(),
            'path' => request()->path(),
            'ajax' => request()->ajax(),
            'xhr'  => request()->header('X-Requested-With'),
        ]);

        try {
            /** @var \Modules\GDF\Entities\TravelRequest $tr */
            $tr = \Modules\GDF\Entities\TravelRequest::findOrFail($travelRequest);

            // Contexto por path
            $areaKey = str_contains(request()->path(), 'campesena') ? 'campesena' : 'academic';
            $routePrefix = ($areaKey === 'campesena')
                ? 'gdf.support.campesena'
                : 'gdf.support.academic';

            // Flags
            $isGdfManual   = (($tr->module ?? '') === 'gdf') && (($tr->source ?? '') === 'manual');
            $isSitravSigac = (($tr->module ?? '') === 'sitrav') && (($tr->source ?? '') === 'sigac') && !empty($tr->source_request_id);

            $titleId = $tr->id;

            // ✅ Docs GDF (travel_request_documents) por relación Eloquent
            $docs = $tr->documents()->orderByDesc('created_at')->get();

            // ✅ Docs SIGAC (program_request_documents) cuando es SITRAV desde SIGAC
            $sigacDocs = collect();
            if ($isSitravSigac) {
                $sigacDocs = DB::table('program_request_documents')
                    ->where('program_request_id', (int) $tr->source_request_id)
                    ->whereNull('deleted_at')
                    ->orderByDesc('created_at')
                    ->get(['id', 'program_request_id', 'name', 'path', 'created_at', 'updated_at']);
            }

            // ✅ Respuesta AJAX: body del modal
            if (request()->ajax() || request()->wantsJson() || request()->header('X-Requested-With') === 'XMLHttpRequest') {
                \Log::info('Returning AJAX view', [
                    'docs_count' => $docs->count(),
                    'sigac_docs_count' => $sigacDocs->count(),
                    'is_gdf_manual' => $isGdfManual,
                    'is_sitrav_sigac' => $isSitravSigac,
                ]);

                return view('gdf::support.documents_modal_body', compact(
                    'docs',
                    'sigacDocs',
                    'areaKey',
                    'routePrefix',
                    'isGdfManual',
                    'isSitravSigac',
                    'titleId',
                    'tr'
                ));
            }

            // No AJAX: redirige a show
            \Log::info('Not AJAX, redirecting');
            return redirect()->route("{$routePrefix}.requests.show", $tr->id);
        } catch (\Throwable $e) {
            \Log::error('Error en documentsIndex:', [
                'request_id' => $travelRequest,
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            if (request()->ajax() || request()->wantsJson()) {
                return response()->view('gdf::support.documents-error', [
                    'message' => 'Error: ' . $e->getMessage(),
                ], 500);
            }

            abort(500, 'Error al cargar documentos');
        }
    }
    private function deny(int $code = 403, string $msg = 'No autorizado'): void
    {
        if (request()->ajax() || request()->wantsJson()) {
            abort($code, $msg); // ok, pero mejor aún sería return response(...)->throwResponse()
        }
        abort($code, $msg);
    }


    public function documentsDownload(Request $request, int $documentId)
    {
        $this->userOr403();
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $doc = DB::table('travel_request_documents')->where('id', $documentId)->first();
        if (!$doc) abort(404);

        $path = $doc->path ?? $doc->file_path ?? null;
        $name = $doc->original_name ?? $doc->filename ?? ('documento_' . $documentId);
        if (!$path) abort(404, 'Documento sin ruta');

        $path = ltrim($path, '/');
        $pathPublic = preg_replace('#^public/#', '', $path);

        // ✅ 1) si está en disk public (storage/app/public)
        if (Storage::disk('public')->exists($pathPublic)) {
            return Storage::disk('public')->download($pathPublic, $name);
        }

        // ✅ 2) si está en disk local (storage/app)
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $name);
        }

        abort(404, "No existe el archivo en storage: {$path}");
    }

    public function documentsDownloadSigac(Request $request, int $documentId)
    {
        $this->userOr403();
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $doc = DB::table('program_request_documents')->where('id', $documentId)->first();
        if (!$doc) abort(404);

        $path = ltrim((string)$doc->path, '/');
        $name = $doc->name ?? ('documento_sigac_' . $documentId);

        $pathPublic = preg_replace('#^public/#', '', $path);

        if (Storage::disk('public')->exists($pathPublic)) {
            return Storage::disk('public')->download($pathPublic, $name);
        }
        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $name);
        }

        abort(404, 'Documento SIGAC no encontrado.');
    }

    public function documentsPreview(Request $request, int $documentId)
    {
        $this->userOr403();
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $doc = DB::table('travel_request_documents')->where('id', $documentId)->first();
        if (!$doc) abort(404);

        $path = $doc->path ?? $doc->file_path ?? null;
        $name = $doc->original_name ?? $doc->filename ?? ('documento_' . $documentId);
        if (!$path) abort(404, 'Documento sin ruta');

        $path = ltrim($path, '/');
        $pathPublic = preg_replace('#^public/#', '', $path);

        // 1) disk public
        if (Storage::disk('public')->exists($pathPublic)) {
            $abs = Storage::disk('public')->path($pathPublic);
            return response()->file($abs, [
                'Content-Disposition' => 'inline; filename="' . $name . '"',
            ]);
        }

        // 2) disk local
        if (Storage::disk('local')->exists($path)) {
            $abs = Storage::disk('local')->path($path);
            return response()->file($abs, [
                'Content-Disposition' => 'inline; filename="' . $name . '"',
            ]);
        }

        abort(404, "No existe el archivo en storage: {$path}");
    }
    public function documentsPreviewSigac(Request $request, int $documentId)
    {
        $this->userOr403();
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $doc = DB::table('program_request_documents')->where('id', $documentId)->first();
        if (!$doc) abort(404);

        $path = ltrim((string)$doc->path, '/');
        $name = $doc->name ?? ('documento_sigac_' . $documentId);

        $pathPublic = preg_replace('#^public/#', '', $path);

        if (Storage::disk('public')->exists($pathPublic)) {
            $abs = Storage::disk('public')->path($pathPublic);
            return response()->file($abs, [
                'Content-Disposition' => 'inline; filename="' . $name . '"',
            ]);
        }

        if (Storage::disk('local')->exists($path)) {
            $abs = Storage::disk('local')->path($path);
            return response()->file($abs, [
                'Content-Disposition' => 'inline; filename="' . $name . '"',
            ]);
        }

        abort(404, 'Documento SIGAC no encontrado.');
    }
    public function documentsReview(Request $request, int $documentId)
    {
        $this->userOr403();
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $doc = DB::table('travel_request_documents')->where('id', $documentId)->first();
        if (!$doc) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        $action = $request->input('action'); // 'approve' o 'reject'
        $reason = $request->input('reason', null);

        if ($action === 'approve') {
            DB::table('travel_request_documents')
                ->where('id', $documentId)
                ->update([
                    'status' => 'approved',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'review_notes' => 'Aprobado por soporte',
                    'updated_at' => now(),
                ]);

            return response()->json(['success' => true, 'message' => 'Documento aprobado']);
        }

        if ($action === 'reject') {
            if (empty($reason)) {
                return response()->json(['error' => 'Debe proporcionar un motivo'], 422);
            }

            DB::table('travel_request_documents')
                ->where('id', $documentId)
                ->update([
                    'status' => 'rejected',
                    'reviewed_by' => auth()->id(),
                    'reviewed_at' => now(),
                    'review_notes' => $reason,
                    'updated_at' => now(),
                ]);

            return response()->json(['success' => true, 'message' => 'Documento rechazado']);
        }

        return response()->json(['error' => 'Acción no válida'], 400);
    }
    private function syncTransportCostRow(int $travelRequestId): void
    {
        $sumSegTransport = (float) TravelSegment::query()
            ->where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->sum('transport_cost');

        TravelCost::query()->updateOrCreate(
            [
                'travel_request_id' => $travelRequestId,
                'cost_type'         => 'transport',
            ],
            [
                'amount'     => $sumSegTransport,
                'updated_at' => now(),
            ]
        );
    }
}
