<?php

namespace Modules\GDF\Http\Controllers\Treasury;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelSegment;
use Modules\GDF\Entities\TravelCost;
use Modules\GDF\Entities\TravelAllowance;
use Modules\GDF\Entities\TravelLog;

class TreasuryController extends Controller
{

    public function index(Request $request)
    {
        $this->authorizeTreasury();

        $area   = (string) $request->get('area', 'all');   // academic|campesena|all
        $module = (string) $request->get('module', 'all'); // gdf|sitrav|all
        $q      = trim((string) $request->get('q', ''));
        $year   = (int) $request->get('year', now()->year);

        if (!in_array($area, ['academic', 'campesena', 'all'], true)) $area = 'all';
        if (!in_array($module, ['gdf', 'sitrav', 'all'], true)) $module = 'all';

        $pendingStatuses = ['pending_treasury', 'in_treasury_review', 'submitted', 'approved'];

        $areaIds = [];
        if ($area !== 'all') {
            $areaIds = config("gdf.area_groups.$area", []);
            $areaIds = is_array($areaIds) ? array_map('intval', $areaIds) : [];
        }

        $query = TravelRequest::query()->whereIn('status', $pendingStatuses);

        if ($module !== 'all') {
            $query->where(function ($w) use ($module) {
                $w->where('module', $module);
                if ($module === 'gdf') $w->orWhereNull('module');
            });
        } else {
            $query->where(function ($w) {
                $w->whereIn('module', ['gdf', 'sitrav'])->orWhereNull('module');
            });
        }

        if (!empty($areaIds)) $query->whereIn('area_id', $areaIds);

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($q, $like) {
                if (ctype_digit($q)) $sub->orWhere('id', (int)$q);
                $sub->orWhere('origin', 'like', $like)
                    ->orWhere('destination', 'like', $like)
                    ->orWhere('radicado_code', 'like', $like);
            });
        }

        $requests = $query->orderByDesc('created_at')->paginate(15)->appends($request->query());

        $pairs = $requests->getCollection()
            ->map(fn($r) => ['area_id' => (int)$r->area_id, 'budget_item_id' => (int)$r->budget_item_id])
            ->filter(fn($p) => $p['area_id'] > 0 && $p['budget_item_id'] > 0)
            ->unique(fn($p) => $p['area_id'] . '|' . $p['budget_item_id'])
            ->values()->all();

        $availMap = $this->budgetAvailabilityForRequests($pairs, $year);

        return view('gdf::treasury.dashboard', compact('area', 'module', 'q', 'year', 'requests', 'availMap'));
    }

    public function show(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $year = (int) $request->get('year', now()->year);

        /** @var \Modules\GDF\Entities\TravelRequest $r */
        $r = TravelRequest::query()
            ->with([
                'area',
                'budgetItem',
                'segments',
                'costs',
                'allowances',
            ])
            ->findOrFail($id);

        if (method_exists($this, 'repairTotals')) {
            $this->repairTotals((int) $r->id);
            $r->refresh();
        }

        $areaId = (int) ($r->area_id ?? 0);
        $itemId = (int) ($r->budget_item_id ?? 0);

        if ($itemId > 0 && empty($r->budgetItem)) {
            try {
                $r->setRelation(
                    'budgetItem',
                    \Modules\GDF\Entities\BudgetItem::query()->find($itemId)
                );
            } catch (\Throwable $e) {
            }
        }

        $pairs = [];
        if ($areaId > 0 && $itemId > 0) {
            $pairs[] = ['area_id' => $areaId, 'budget_item_id' => $itemId];
        }

        $availMap = $this->budgetAvailabilityForRequests($pairs, $year);
        $key  = $areaId . '|' . $itemId;
        $avail = $availMap[$key] ?? null;

        $canAdjust = method_exists($this, 'canTreasuryAdjust')
            ? (bool) $this->canTreasuryAdjust()
            : false;

        $radicatedByUser = null;
        try {
            $radicatedById = (int) ($r->radicated_by ?? 0);
            if ($radicatedById > 0) {
                $radicatedByUser = \App\Models\User::query()->find($radicatedById);

            }
        } catch (\Throwable $e) {
            $radicatedByUser = null;
        }

        return view('gdf::treasury.requests.show', compact(
            'r',
            'year',
            'avail',
            'canAdjust',
            'radicatedByUser'
        ));
    }



    public function adjustTransport(Request $request, int $id)
    {
        $this->authorizeTreasury();
        if (!$this->canTreasuryAdjust()) abort(403);

        $data = $request->validate([
            'year'            => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'transport_cost'  => ['required', 'numeric', 'min:0'],
            'comment'         => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->with(['segments', 'costs', 'allowances'])->findOrFail($id);

            // Solo permitir en estados revisables
            if (!in_array((string)$r->status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true)) {
                return back()->with('warning', 'La solicitud no está en estado para ajustar.');
            }

            $newTransport = (float)$data['transport_cost'];

            if (Schema::hasColumn('travel_requests', 'total_transport')) {
                $r->total_transport = $newTransport;
            }

            TravelCost::updateOrCreate(
                ['travel_request_id' => (int)$r->id, 'cost_type' => 'transport'],
                ['amount' => $newTransport, 'description' => 'Ajuste Tesorería (excepción)', 'updated_at' => now()]
            );


            $this->repairTotals((int)$r->id);

            // 5) Log
            $this->logTreasuryAction((int)$r->id, 'treasury_adjust_transport', [
                'new_transport' => $newTransport,
                'comment'       => (string)$data['comment'],
            ]);

            DB::commit();
            return back()->with('success', 'Transporte ajustado por Tesorería y totales recalculados.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo ajustar transporte: ' . $e->getMessage());
        }
    }


    public function adjustAllowance(Request $request, int $id, int $allowanceId)
    {
        $this->authorizeTreasury();
        if (!$this->canTreasuryAdjust()) abort(403);

        $data = $request->validate([
            'year'    => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'amount'  => ['required', 'numeric', 'min:0'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->with(['allowances'])->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true)) {
                return back()->with('warning', 'La solicitud no está en estado para ajustar.');
            }

            /** @var TravelAllowance $a */
            $a = TravelAllowance::query()
                ->where('travel_request_id', (int)$r->id)
                ->where('id', $allowanceId)
                ->lockForUpdate()
                ->firstOrFail();

            $new = (float)$data['amount'];

            if (Schema::hasColumn('travel_allowances', 'calculated_amount')) {
                $a->calculated_amount = $new;
            }

            if (Schema::hasColumn('travel_allowances', 'amount')) {
                $a->amount = $new;
            }

            if (Schema::hasColumn('travel_allowances', 'description')) {
                $a->description = trim((string)($a->description ?? ''));
                $a->description = $a->description !== ''
                    ? ($a->description . ' | Ajuste Tesorería (excepción)')
                    : 'Ajuste Tesorería (excepción)';
            }

            $a->updated_at = now();
            $a->save();

            $this->repairTotals((int)$r->id);

            $this->logTreasuryAction((int)$r->id, 'treasury_adjust_allowance', [
                'allowance_id' => (int)$a->id,
                'new_amount'   => $new,
                'comment'      => (string)$data['comment'],
            ]);

            DB::commit();
            return back()->with('success', 'Viático ajustado por Tesorería y totales recalculados.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo ajustar viático: ' . $e->getMessage());
        }
    }


    public function approve(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
            'year'    => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $year = (int)($data['year'] ?? now()->year);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true)) {
                return back()->with('warning', 'La solicitud ya no está pendiente de Tesorería.');
            }

            $this->repairTotals((int)$r->id);
            $r->refresh();

            $pairs = [];
            if ((int)$r->area_id > 0 && (int)$r->budget_item_id > 0) {
                $pairs[] = ['area_id' => (int)$r->area_id, 'budget_item_id' => (int)$r->budget_item_id];
            }

            $availMap = $this->budgetAvailabilityForRequests($pairs, $year);
            $key = ((int)$r->area_id) . '|' . ((int)$r->budget_item_id);
            $a = $availMap[$key] ?? ['available' => 0];

            $need = (float)($r->total_amount ?? 0);

            if ((float)($a['available'] ?? 0) < $need) {
                return back()->with('error', 'No hay recursos suficientes para aprobar.');
            }

            $r->status = 'approved_by_treasury';

            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_by')) $r->treasury_reviewed_by = Auth::id();
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_at')) $r->treasury_reviewed_at = now();
            if (Schema::hasColumn('travel_requests', 'treasury_comment'))     $r->treasury_comment = $data['comment'] ?? null;

            $r->save();

            DB::commit();
            return back()->with('success', 'Aprobado por Tesorería.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }
    }


    public function returnRequest(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true)) {
                return back()->with('warning', 'La solicitud ya no está pendiente de Tesorería.');
            }

            $r->status = 'returned';

            if (Schema::hasColumn('travel_requests', 'returned_target'))       $r->returned_target = $data['target'];
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_by')) $r->treasury_reviewed_by = Auth::id();
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_at')) $r->treasury_reviewed_at = now();
            if (Schema::hasColumn('travel_requests', 'treasury_comment'))     $r->treasury_comment = $data['comment'];

            $r->save();

            DB::commit();
            return back()->with('success', 'Solicitud devuelta.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo devolver: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true)) {
                return back()->with('warning', 'La solicitud ya no está pendiente de Tesorería.');
            }

            $r->status = 'rejected_by_treasury';

            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_by')) $r->treasury_reviewed_by = Auth::id();
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_at')) $r->treasury_reviewed_at = now();
            if (Schema::hasColumn('travel_requests', 'treasury_comment'))     $r->treasury_comment = $data['comment'];

            $r->save();

            DB::commit();
            return back()->with('success', 'Solicitud rechazada.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo rechazar: ' . $e->getMessage());
        }
    }

    public function radicate(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'radicado_code' => ['required', 'string', 'max:80'],
            'year'          => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true)) {
                return back()->with('warning', 'La solicitud no está en estado para radicar.');
            }

            $r->radicado_code = trim($data['radicado_code']);
            if (Schema::hasColumn('travel_requests', 'radicated_at')) $r->radicated_at  = now();
            if (Schema::hasColumn('travel_requests', 'radicated_by')) $r->radicated_by  = Auth::id();
            $r->save();

            DB::commit();
            return back()->with('success', 'Radicado registrado.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo radicar: ' . $e->getMessage());
        }
    }


    private function authorizeTreasury(): void
    {
        $ok = function_exists('checkRol')
            ? (checkRol('gdf.treasury') || checkRol('gdf.superadmin') || checkRol('superadmin'))
            : false;

        if (!$ok) abort(403);
    }

    private function canTreasuryAdjust(): bool
    {
        if (!function_exists('checkRol')) return false;

        return checkRol('gdf.treasury') || checkRol('gdf.superadmin') || checkRol('superadmin');
    }

    private function logTreasuryAction(int $travelRequestId, string $action, array $meta = []): void
    {
        try {
            if (class_exists(TravelLog::class)) {
                TravelLog::create([
                    'travel_request_id' => $travelRequestId,
                    'action'            => $action,
                    'description'       => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'created_by'        => Auth::id(),
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }
        } catch (\Throwable $e) {
        }
    }

    private function repairTotals(int $travelRequestId): void
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

        TravelCost::updateOrCreate(
            ['travel_request_id' => $travelRequestId, 'cost_type' => 'transport'],
            ['amount' => $transport, 'updated_at' => now()]
        );
    }

    private function budgetAvailabilityForRequests(array $pairs, int $year): array
    {
        $out = [];

        // 1) Normalizar pares y armar llaves area|item
        $keys = [];
        foreach ($pairs as $p) {
            $a = (int)($p['area_id'] ?? 0);
            $b = (int)($p['budget_item_id'] ?? 0);
            if ($a > 0 && $b > 0) {
                $keys["$a|$b"] = ['area_id' => $a, 'budget_item_id' => $b];
            }
        }
        if (empty($keys)) return $out;

        $areaIds = array_values(array_unique(array_map(fn($x) => $x['area_id'], $keys)));
        $itemIds = array_values(array_unique(array_map(fn($x) => $x['budget_item_id'], $keys)));

        // 2) Traer presupuestos activos del año para esos pares
        //    ✅ OJO: LEFT JOIN para que NO “desaparezca” el budget si falta el budget_item
        $budgets = DB::table('budgets as b')
            ->leftJoin('budget_items as bi', 'bi.id', '=', 'b.budget_item_id')
            ->select([
                'b.id as budget_id',
                'b.area_id',
                'b.budget_item_id',
                'b.initial_amount',
                'b.current_amount',
                'b.active',
                'bi.code as item_code',
                'bi.name as item_name',
            ])
            ->where('b.year', $year)
            ->where('b.active', 1)
            ->whereIn('b.area_id', $areaIds)
            ->whereIn('b.budget_item_id', $itemIds)
            ->get();

        $budgetByKey = [];
        $budgetIds = [];

        foreach ($budgets as $b) {
            $k = ((int)$b->area_id) . '|' . ((int)$b->budget_item_id);
            $budgetByKey[$k] = $b;
            $budgetIds[] = (int)$b->budget_id;
        }
        $budgetIds = array_values(array_unique($budgetIds));

        // 3) Adiciones aprobadas
        $additionsByBudget = [];
        if (!empty($budgetIds) && Schema::hasTable('budget_additions')) {
            $addRows = DB::table('budget_additions')
                ->select('budget_id', DB::raw('SUM(amount) as total'))
                ->whereIn('budget_id', $budgetIds)
                ->whereNotNull('approved_at')
                ->groupBy('budget_id')
                ->get();

            foreach ($addRows as $r) {
                $additionsByBudget[(int)$r->budget_id] = (float)$r->total;
            }
        }

        // 4) Movimientos por tipo
        $moves = [
            'addition'   => [],
            'adjustment' => [],
            'commitment' => [],
            'reversal'   => [],
            'execute'    => [],
        ];

        if (!empty($budgetIds) && Schema::hasTable('budget_movements')) {
            $movRows = DB::table('budget_movements')
                ->select('budget_id', 'type', DB::raw('SUM(amount) as total'))
                ->whereIn('budget_id', $budgetIds)
                ->groupBy('budget_id', 'type')
                ->get();

            foreach ($movRows as $r) {
                $bid = (int)$r->budget_id;
                $t   = (string)$r->type;
                if (isset($moves[$t])) $moves[$t][$bid] = (float)$r->total;
            }
        }

        // 5) Construir salida por cada par pedido
        foreach ($keys as $k => $meta) {
            $b = $budgetByKey[$k] ?? null;

            if (!$b) {
                $out[$k] = [
                    'exists'    => false,
                    'budget_id' => null,
                    'name'      => 'Sin presupuesto activo',
                    'initial'   => 0,
                    'additions' => 0,
                    'm_add'     => 0,
                    'm_adj'     => 0,
                    'm_com'     => 0,
                    'm_rev'     => 0,
                    'm_exe'     => 0,
                    'available' => 0,
                    'current'   => 0,
                ];
                continue;
            }

            $bid = (int)$b->budget_id;

            $initial   = (float)($b->initial_amount ?? 0);
            $current   = (float)($b->current_amount ?? 0);
            $additions = (float)($additionsByBudget[$bid] ?? 0);

            $mAdd = (float)($moves['addition'][$bid] ?? 0);
            $mAdj = (float)($moves['adjustment'][$bid] ?? 0);
            $mCom = (float)($moves['commitment'][$bid] ?? 0);
            $mRev = (float)($moves['reversal'][$bid] ?? 0);
            $mExe = (float)($moves['execute'][$bid] ?? 0);

            // Regla de disponible (misma que venías usando)
            $available = max(0, ($initial + $additions + $mAdd + $mAdj + $mRev) - ($mCom + $mExe));

            // Label rubro: si falta budget_items, no rompas la UI
            $code = (string)($b->item_code ?? '');
            $name = (string)($b->item_name ?? '');
            $label = trim(($code !== '' ? ($code . ' - ') : '') . ($name !== '' ? $name : 'Rubro'));

            $out[$k] = [
                'exists'    => true,
                'budget_id' => $bid,
                'name'      => $label,
                'initial'   => $initial,
                'additions' => $additions,
                'm_add'     => $mAdd,
                'm_adj'     => $mAdj,
                'm_com'     => $mCom,
                'm_rev'     => $mRev,
                'm_exe'     => $mExe,
                'available' => $available,
                'current'   => $current,
            ];
        }

        return $out;
    }
}
