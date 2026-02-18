<?php

namespace Modules\GDF\Http\Controllers\Treasury;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

use Modules\GDF\Entities\TravelRequest;

class TreasuryController extends Controller
{
    /* =========================
     * LISTA / DASHBOARD
     * ========================= */
    public function index(Request $request)
    {
        $this->authorizeTreasury();

        $area   = (string) $request->get('area', 'all');    // academic|campesena|all
        $module = (string) $request->get('module', 'all');  // gdf|sitrav|all
        $q      = trim((string) $request->get('q', ''));
        $year   = (int) $request->get('year', now()->year);

        if (!in_array($area,   ['academic', 'campesena', 'all'], true)) $area = 'all';
        if (!in_array($module, ['gdf', 'sitrav', 'all'], true))         $module = 'all';

        // ✅ Estados “en tesorería”
        $pendingStatuses = ['pending_treasury', 'in_treasury_review', 'submitted'];

        // AreaIds por grupo (si aplica)
        $areaIds = [];
        if ($area !== 'all') {
            $areaIds = config("gdf.area_groups.$area", []);
            $areaIds = is_array($areaIds) ? array_map('intval', $areaIds) : [];
        }

        $query = TravelRequest::query()
            ->whereIn('status', $pendingStatuses);

        // módulo
        if ($module === 'all') {
            $query->whereIn('module', ['gdf', 'sitrav']);
        } else {
            $query->where('module', $module);
        }

        // áreas
        if (!empty($areaIds)) {
            $query->whereIn('area_id', $areaIds);
        }

        // búsqueda
        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($sub) use ($q, $like) {
                if (ctype_digit($q)) $sub->orWhere('id', (int)$q);
                $sub->orWhere('origin', 'like', $like)
                    ->orWhere('destination', 'like', $like)
                    ->orWhere('radicado_code', 'like', $like);
            });
        }

        $requests = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends($request->query());

        // Pares area|rubro para batch presupuesto
        $pairs = $requests->getCollection()
            ->map(fn($r) => [
                'area_id'        => (int) $r->area_id,
                'budget_item_id' => (int) $r->budget_item_id,
            ])
            ->filter(fn($p) => $p['area_id'] > 0 && $p['budget_item_id'] > 0)
            ->unique(fn($p) => $p['area_id'].'|'.$p['budget_item_id'])
            ->values()
            ->all();

        $availMap = $this->budgetAvailabilityForRequests($pairs, $year);

        return view('gdf::treasury.requests.index', compact(
            'area','module','q','year','requests','availMap'
        ));
    }

    /* =========================
     * SHOW
     * ========================= */
    public function show(int $id, Request $request)
    {
        $this->authorizeTreasury();

        $r = TravelRequest::findOrFail($id);
        $year = (int) $request->get('year', (int)\Carbon\Carbon::parse($r->start_date ?? now())->format('Y'));

        $pairs = [[
            'area_id'        => (int)$r->area_id,
            'budget_item_id' => (int)$r->budget_item_id,
        ]];

        $availMap = $this->budgetAvailabilityForRequests($pairs, $year);
        $key = ((int)$r->area_id).'|'.((int)$r->budget_item_id);
        $a = $availMap[$key] ?? null;

        return view('gdf::treasury.dashboard.show', compact('r','year','a'));
    }

    /* =========================
     * APROBAR
     * ========================= */
    public function approve(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted','in_treasury_review','pending_treasury'], true)) {
                return back()->with('warning', 'La solicitud ya no está pendiente de Tesorería.');
            }

            $year = (int) $request->get('year', (int)\Carbon\Carbon::parse($r->start_date ?? now())->format('Y'));

            $pairs = [[
                'area_id'        => (int)$r->area_id,
                'budget_item_id' => (int)$r->budget_item_id,
            ]];

            $availMap = $this->budgetAvailabilityForRequests($pairs, $year);
            $k = ((int)$r->area_id).'|'.((int)$r->budget_item_id);
            $a = $availMap[$k] ?? null;

            $available = (float)($a['available'] ?? 0);
            $need      = (float)($r->total_amount ?? 0);

            if ($available < $need) {
                return back()->with('error', 'No hay recursos suficientes para aprobar.');
            }

            $r->status = 'approved_by_treasury';

            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_by')) $r->treasury_reviewed_by = Auth::id();
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_at')) $r->treasury_reviewed_at = now();
            if (Schema::hasColumn('travel_requests', 'treasury_comment')) $r->treasury_comment = $data['comment'] ?? null;

            $r->save();

            DB::commit();
            return back()->with('success', 'Aprobado por Tesorería.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }
    }

    /* =========================
     * DEVOLVER
     * ========================= */
    public function return(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted','in_treasury_review','pending_treasury'], true)) {
                return back()->with('warning', 'La solicitud ya no está pendiente de Tesorería.');
            }

            $r->status = 'returned';

            if (Schema::hasColumn('travel_requests', 'returned_target')) $r->returned_target = $data['target'];
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_by')) $r->treasury_reviewed_by = Auth::id();
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_at')) $r->treasury_reviewed_at = now();
            if (Schema::hasColumn('travel_requests', 'treasury_comment')) $r->treasury_comment = $data['comment'];

            $r->save();

            DB::commit();
            return back()->with('success', 'Solicitud devuelta.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo devolver: ' . $e->getMessage());
        }
    }

    /* =========================
     * RECHAZAR
     * ========================= */
    public function reject(Request $request, int $id)
    {
        $this->authorizeTreasury();

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        DB::beginTransaction();
        try {
            $r = TravelRequest::lockForUpdate()->findOrFail($id);

            if (!in_array((string)$r->status, ['submitted','in_treasury_review','pending_treasury'], true)) {
                return back()->with('warning', 'La solicitud ya no está pendiente de Tesorería.');
            }

            $r->status = 'rejected_by_treasury';

            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_by')) $r->treasury_reviewed_by = Auth::id();
            if (Schema::hasColumn('travel_requests', 'treasury_reviewed_at')) $r->treasury_reviewed_at = now();
            if (Schema::hasColumn('travel_requests', 'treasury_comment')) $r->treasury_comment = $data['comment'];

            $r->save();

            DB::commit();
            return back()->with('success', 'Solicitud rechazada por Tesorería.');
        } catch (Throwable $e) {
            DB::rollBack();
            report($e);
            return back()->with('error', 'No se pudo rechazar: ' . $e->getMessage());
        }
    }

    /* ========================= HELPERS ========================= */

    private function authorizeTreasury(): void
    {
        $ok = function_exists('checkRol') ? (checkRol('gdf.treasury') || checkRol('gdf.superadmin')) : false;
        if (!$ok) abort(403);
    }

    /**
     * Devuelve disponibilidad por key "area_id|budget_item_id"
     * Usa:
     * - budgets (year+area+rubro, active=1)
     * - budget_area_allocations (budget_id+area_id, active=1) como BASE si existe
     * - budget_movements por budget_id (+ opcional area_id)
     */
    private function budgetAvailabilityForRequests(array $pairs, int $year): array
    {
        $out = [];

        // Normaliza keys
        $keys = [];
        foreach ($pairs as $p) {
            $a = (int)($p['area_id'] ?? 0);
            $b = (int)($p['budget_item_id'] ?? 0);
            if ($a > 0 && $b > 0) $keys["$a|$b"] = ['area_id' => $a, 'budget_item_id' => $b];
        }
        if (empty($keys)) return $out;

        $areaIds = array_values(array_unique(array_map(fn($x) => $x['area_id'], $keys)));
        $itemIds = array_values(array_unique(array_map(fn($x) => $x['budget_item_id'], $keys)));

        // 1) budgets activos del año
        $budgets = DB::table('budgets as b')
            ->join('budget_items as bi', 'bi.id', '=', 'b.budget_item_id')
            ->select([
                'b.id as budget_id',
                'b.area_id',
                'b.budget_item_id',
                'b.initial_amount',
                'b.current_amount',
                'bi.code as item_code',
                'bi.name as item_name',
            ])
            ->where('b.year', $year)
            ->where('b.active', 1)
            ->whereIn('b.area_id', $areaIds)
            ->whereIn('b.budget_item_id', $itemIds)
            ->get();

        $budgetByKey = [];
        $budgetIds   = [];
        foreach ($budgets as $b) {
            $k = ((int)$b->area_id).'|'.((int)$b->budget_item_id);
            $budgetByKey[$k] = $b;
            $budgetIds[] = (int)$b->budget_id;
        }
        $budgetIds = array_values(array_unique($budgetIds));

        // 2) Base por budget_area_allocations (si existe)
        $allocBase = []; // key: "area_id|budget_id" => allocated_amount
        if (!empty($budgetIds) && Schema::hasTable('budget_area_allocations')) {
            $rows = DB::table('budget_area_allocations')
                ->select('budget_id','area_id','allocated_amount')
                ->whereIn('budget_id', $budgetIds)
                ->whereIn('area_id', $areaIds)
                ->where('active', 1)
                ->get();

            foreach ($rows as $r) {
                $allocBase[((int)$r->area_id).'|'.((int)$r->budget_id)] = (float)$r->allocated_amount;
            }
        }

        // 3) Movimientos por budget_id (si tu tabla guarda area_id, puedes filtrar por area)
        $moves = [
            'addition'   => [],
            'adjustment' => [],
            'commitment' => [],
            'reversal'   => [],
            'execute'    => [],
        ];

        if (!empty($budgetIds)) {
            $movQ = DB::table('budget_movements')
                ->select('budget_id','type', DB::raw('SUM(amount) as total'))
                ->whereIn('budget_id', $budgetIds)
                ->groupBy('budget_id','type')
                ->get();

            foreach ($movQ as $m) {
                $bid = (int)$m->budget_id;
                $t   = (string)$m->type;
                if (isset($moves[$t])) $moves[$t][$bid] = (float)$m->total;
            }
        }

        // 4) Armar salida por cada key area|item
        foreach ($keys as $k => $meta) {
            $b = $budgetByKey[$k] ?? null;

            if (!$b) {
                $out[$k] = [
                    'exists'    => false,
                    'budget_id' => null,
                    'name'      => 'Sin presupuesto activo',
                    'base'      => 0,
                    'available' => 0,
                ];
                continue;
            }

            $bid     = (int)$b->budget_id;
            $areaId  = (int)$meta['area_id'];

            // ✅ Base: si hay allocation, manda; si no, usa current_amount (o initial_amount)
            $base = $allocBase["$areaId|$bid"] ?? (float)($b->current_amount ?? $b->initial_amount ?? 0);

            $mAdd = (float)($moves['addition'][$bid] ?? 0);
            $mAdj = (float)($moves['adjustment'][$bid] ?? 0);
            $mRev = (float)($moves['reversal'][$bid] ?? 0);
            $mCom = (float)($moves['commitment'][$bid] ?? 0);
            $mExe = (float)($moves['execute'][$bid] ?? 0);

            $available = max(0, ($base + $mAdd + $mAdj + $mRev) - ($mCom + $mExe));

            $out[$k] = [
                'exists'    => true,
                'budget_id' => $bid,
                'name'      => trim(($b->item_code ?? '').' - '.($b->item_name ?? 'Rubro')),
                'base'      => $base,
                'm_add'     => $mAdd,
                'm_adj'     => $mAdj,
                'm_rev'     => $mRev,
                'm_com'     => $mCom,
                'm_exe'     => $mExe,
                'available' => $available,
            ];
        }

        return $out;
    }
}
