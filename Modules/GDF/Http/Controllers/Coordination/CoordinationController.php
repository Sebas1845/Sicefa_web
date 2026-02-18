<?php

namespace Modules\GDF\Http\Controllers\Coordination;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Modules\GDF\Entities\TravelAllowance;
use Modules\GDF\Entities\TravelCost;
use Modules\GDF\Entities\TravelSegment;
use Modules\SICA\Entities\Program;
use Modules\SIGAC\Entities\ProgramRequest;


use Modules\GDF\Entities\TravelRequest;

class CoordinationController extends Controller
{

    public function index(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request); // academic|campesena
        $this->authorizeByArea($areaKey);

        $routePrefix = $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';
        $title       = $areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica';

        $q = trim((string) $request->get('q', ''));

        $tab = (string) $request->get('tab', 'all'); // all|gdf|sitrav
        if (!in_array($tab, ['all', 'gdf', 'sitrav'], true)) $tab = 'all';

        $pendingStatuses = ['approved_by_treasury'];

        $query = \Modules\GDF\Entities\TravelRequest::query();

   
        $with = [];

        if (method_exists(\Modules\GDF\Entities\TravelRequest::class, 'person')) {
            $with[] = 'person:id,first_name,first_last_name,second_last_name,document_number';
        }
        if (method_exists(\Modules\GDF\Entities\TravelRequest::class, 'municipality')) {
            $with[] = 'municipality:id,name';
        }
        if (method_exists(\Modules\GDF\Entities\TravelRequest::class, 'village')) {
            $with[] = 'village:id,name';
        }
        if (method_exists(\Modules\GDF\Entities\TravelRequest::class, 'budgetItem')) {
            $with[] = 'budgetItem:id,code,name';
        }

        if (method_exists(\Modules\GDF\Entities\TravelRequest::class, 'allowances')) {
            $with[] = 'allowances:id,travel_request_id,allowance_type,status,unit_amount,units,calculated_amount,approved_amount,description';
        }

        if (method_exists(\Modules\GDF\Entities\TravelRequest::class, 'costs')) {
            $with[] = 'costs:id,travel_request_id,cost_type,description,amount';
        }

        if (!empty($with)) $query->with($with);

       

        $this->applyAreaFilter($query, $areaKey);

        if ($this->hasColumn('travel_requests', 'status')) {
            $query->whereIn('status', $pendingStatuses);
        }

        if ($this->hasColumn('travel_requests', 'module')) {
            if ($tab === 'all') {
                $query->where(function ($w) {
                    $w->whereNull('module')
                        ->orWhereRaw('LOWER(module) IN ("gdf","sitrav")');
                });
            } else {
                $query->whereRaw('LOWER(COALESCE(module,"gdf")) = ?', [strtolower($tab)]);
            }
        }

        if ($q !== '') {
            $like = '%' . $q . '%';

            $query->where(function ($sub) use ($q, $like) {
                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int)$q);
                }

                foreach (['origin', 'destination', 'request_type', 'person_type', 'radicado_code'] as $col) {
                    if ($this->hasColumn('travel_requests', $col)) {
                        $sub->orWhere($col, 'like', $like);
                    }
                }

                foreach (['document_number', 'applicant_name', 'instructor_name'] as $col) {
                    if ($this->hasColumn('travel_requests', $col)) {
                        $sub->orWhere($col, 'like', $like);
                    }
                }
            });
        }

        if ($this->hasColumn('travel_requests', 'updated_at')) $query->orderByDesc('updated_at');
        else $query->orderByDesc('id');

        $gdfCount    = $this->countPendingByModule($areaKey, 'gdf', $pendingStatuses);
        $sitravCount = $this->countPendingByModule($areaKey, 'sitrav', $pendingStatuses);
        $allCount    = $gdfCount + $sitravCount;

        $requests = $query->paginate(15)->appends($request->query());

       
        $budgetMap = [];

        if (!method_exists(\Modules\GDF\Entities\TravelRequest::class, 'budgetItem')) {
            $budgetIds = $requests->getCollection()
                ->pluck('budget_item_id')
                ->filter(fn($v) => (int)$v > 0)
                ->unique()
                ->values()
                ->all();

            if (!empty($budgetIds) && \Illuminate\Support\Facades\Schema::hasTable('budget_items')) {
                $rows = \Illuminate\Support\Facades\DB::table('budget_items')
                    ->select('id', 'code', 'name')
                    ->whereIn('id', $budgetIds)
                    ->get();

                foreach ($rows as $bi) {
                    $budgetMap[(int)$bi->id] = trim(($bi->code ?? '') . ' - ' . ($bi->name ?? ''));
                }
            }
        }

       
        $requests->getCollection()->transform(function ($r) use ($budgetMap) {

            // Persona
            $p = $r->person ?? null;
            $full = $p ? trim(($p->first_name ?? '') . ' ' . ($p->first_last_name ?? '') . ' ' . ($p->second_last_name ?? '')) : '';

            if ($full === '') $full = (string)($r->applicant_name ?? $r->instructor_name ?? '');
            if ($full === '') {
                $pid = (int)($r->person_id ?? 0);
                $full = $pid > 0 ? 'Persona #' . $pid : '—';
            }
            $r->person_display = $full;

            // Rubro
            if (isset($r->budgetItem) && $r->budgetItem) {
                $r->rubro_display = trim(($r->budgetItem->code ?? '') . ' - ' . ($r->budgetItem->name ?? 'Rubro'));
            } else {
                $bid = (int)($r->budget_item_id ?? 0);
                $r->rubro_display = $budgetMap[$bid] ?? ($bid > 0 ? 'Rubro #' . $bid : '—');
            }

            $costTotal = 0.0;
            if (isset($r->costs) && $r->costs) {
                $costTotal = (float)$r->costs->sum('amount');
            }

            $allowTotal = 0.0;
            if (isset($r->allowances) && $r->allowances) {
                $active = $r->allowances->whereIn('status', ['draft', 'liquidated', 'approved']);

                $allowTotal = (float)$active->sum(function ($a) {
                    $v = $a->approved_amount;
                    if ($v === null || $v === '') $v = $a->calculated_amount;
                    return (float)$v;
                });
            }

            $r->computed_costs = $costTotal;
            $r->computed_allow = $allowTotal;
            $r->computed_total = $costTotal + $allowTotal;

            $st = (string)($r->status ?? '');
            $r->status_code    = $st !== '' ? $st : '—';
            $r->status_display = \Modules\GDF\Status\TravelStatus::label($st);
            $r->status_badge   = 'text-bg-' . \Modules\GDF\Status\TravelStatus::badge($st);

            return $r;
        });

        return view('gdf::coordination.review', [
            'areaKey'     => $areaKey,
            'routePrefix' => $routePrefix,
            'title'       => $title,
            'q'           => $q,
            'tab'         => $tab,
            'gdfCount'    => $gdfCount,
            'sitravCount' => $sitravCount,
            'allCount'    => $allCount,
            'requests'    => $requests,
        ]);
    }
    private function recalcRequestTotals(int $travelRequestId): void
    {
        $costTotal = (float) TravelCost::where('travel_request_id', $travelRequestId)
            ->when(Schema::hasColumn('travel_costs', 'is_cancelled'), fn($q) => $q->where('is_cancelled', 0))
            ->sum(Schema::hasColumn('travel_costs', 'amount') ? 'amount' : 'value');

        $allowTotal = (float) TravelAllowance::where('travel_request_id', $travelRequestId)
            ->whereIn('status', ['draft', 'liquidated', 'approved'])
            ->sum('calculated_amount');

        $grandTotal = $costTotal + $allowTotal;

        $update = [];
        if ($this->hasColumn('travel_requests', 'total_costs'))    $update['total_costs']    = $costTotal;
        if ($this->hasColumn('travel_requests', 'total_per_diem')) $update['total_per_diem'] = $allowTotal;

        if ($this->hasColumn('travel_requests', 'total_amount'))   $update['total_amount']   = $grandTotal;
        if ($this->hasColumn('travel_requests', 'total'))          $update['total']          = $grandTotal;

        if ($update) {
            TravelRequest::where('id', $travelRequestId)->update($update);
        }
    }
    public function show(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $with = [
            'person:id,first_name,first_last_name,second_last_name,document_number',
            'area:id,name',
        ];

        if (method_exists(TravelRequest::class, 'municipality')) $with[] = 'municipality:id,name';
        if (method_exists(TravelRequest::class, 'village'))      $with[] = 'village:id,name';
        if (method_exists(TravelRequest::class, 'budgetItem'))   $with[] = 'budgetItem:id,code,name';

        $r = TravelRequest::query()->with($with)->findOrFail($id);
        $this->ensureRequestMatchesArea($r, $areaKey);

        $module   = strtolower(trim((string)($r->module ?? 'gdf')));
        $isSitrav = $module === 'sitrav';

        $pr         = null;
        $program    = null;
        $sigacRubro = null;

        if ($isSitrav && !empty($r->source_request_id)) {
            $pr = ProgramRequest::query()
                ->with([
                    'municipality:id,name',
                    'village:id,name',
                    'budgetItem:id,code,name',
                    'program:id,name,sofia_code,training_type,program_type,modality,priority_bets',
                ])
                ->find((int)$r->source_request_id);

            if ($pr) {
                $program    = $pr->program ?? null;
                $sigacRubro = $pr->budgetItem ?? null;

                if (!$program && !empty($pr->program_id) && class_exists(Program::class)) {
                    $program = Program::select(
                        'id',
                        'name',
                        'sofia_code',
                        'training_type',
                        'program_type',
                        'modality',
                        'priority_bets'
                    )->find((int)$pr->program_id);
                }
            }
        }

        $allowances = TravelAllowance::where('travel_request_id', $r->id)->orderBy('id')->get();
        $segments   = TravelSegment::where('travel_request_id', $r->id)->orderBy('id')->get();
        $costs      = TravelCost::where('travel_request_id', $r->id)->orderBy('id')->get();

        $costTotal = (float)$costs->sum('amount');

        $activeAllowances = $allowances->whereIn('status', ['draft', 'liquidated', 'approved']);
        $allowTotal = (float)$activeAllowances->sum(function ($a) {
            $v = $a->approved_amount;
            if ($v === null || $v === '') $v = $a->calculated_amount;
            return (float)$v;
        });

        $transport = (float)TravelSegment::where('travel_request_id', $r->id)
            ->when(Schema::hasColumn('travel_segments', 'is_cancelled'), fn($q) => $q->where('is_cancelled', 0))
            ->sum('transport_cost');

        foreach ($segments as $s) {
            if (empty($s->origin))      $s->origin = $r->origin;
            if (empty($s->destination)) $s->destination = $r->destination;
        }

        $docs = collect();
        $docsSource = null;

        if ($isSitrav && $pr && Schema::hasTable('program_request_documents')) {

            $docs = DB::table('program_request_documents')
                ->selectRaw('id, program_request_id, name AS name, path, created_at')
                ->whereNull('deleted_at')
                ->where('program_request_id', (int)$pr->id)
                ->orderByDesc('id')
                ->get();

            $docsSource = 'sigac';
        } elseif (!$isSitrav && Schema::hasTable('travel_request_documents')) {

            $docs = DB::table('travel_request_documents')
                ->selectRaw('id, travel_request_id, COALESCE(title, original_name, stored_name) AS name, path, created_at')
                ->whereNull('deleted_at')
                ->where('travel_request_id', (int)$r->id)
                ->orderByDesc('id')
                ->get();

            $docsSource = 'gdf';
        }

        return view('gdf::coordination.show', [
            'areaKey'     => $areaKey,
            'r'           => $r,
            'pr'          => $pr,
            'program'     => $program,
            'sigacRubro'  => $sigacRubro,
            'allowances'  => $allowances,
            'segments'    => $segments,
            'costs'       => $costs,
            'allowTotal'  => $allowTotal,
            'costTotal'   => $costTotal,
            'transport'   => $transport,
            'grandTotal'  => $allowTotal + $costTotal,
            'docs'        => $docs,
            'docsSource'  => $docsSource,
        ]);
    }



    public function return(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $areaKey, $data) {
                $r = TravelRequest::lockForUpdate()->findOrFail($id);
                $this->ensureRequestMatchesArea($r, $areaKey);

                if ((string)$r->status !== 'approved_by_treasury') {
                    throw new \RuntimeException("La solicitud ya no está pendiente (status={$r->status}).");
                }

                $r->status = 'returned';

                if ($this->hasColumn('travel_requests', 'returned_target')) $r->returned_target = $data['target'];
                if ($this->hasColumn('travel_requests', 'coordination_comment')) $r->coordination_comment = $data['comment'];

                if ($this->hasColumn('travel_requests', 'approved_at')) $r->approved_at = null;
                if ($this->hasColumn('travel_requests', 'approved_by')) $r->approved_by = null;

                $r->save();

                $this->maybeCreateReview($r->id, 'returned', $data['comment']);
            });

            return back()->with('success', 'Solicitud devuelta correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo devolver: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $areaKey, $data) {
                $r = TravelRequest::lockForUpdate()->findOrFail($id);
                $this->ensureRequestMatchesArea($r, $areaKey);

                if ((string)$r->status !== 'approved_by_treasury') {
                    throw new \RuntimeException("La solicitud ya no está pendiente (status={$r->status}).");
                }

                $r->status = 'rejected';

                if ($this->hasColumn('travel_requests', 'coordination_comment')) $r->coordination_comment = $data['comment'];
                if ($this->hasColumn('travel_requests', 'approved_at')) $r->approved_at = null;
                if ($this->hasColumn('travel_requests', 'approved_by')) $r->approved_by = null;

                $r->save();

                $this->maybeCreateReview($r->id, 'rejected', $data['comment']);
            });

            return back()->with('success', 'Solicitud rechazada por Coordinación.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo rechazar: ' . $e->getMessage());
        }
    }

    /* ========================= HELPERS ========================= */

    protected function hasColumn(string $table, string $column): bool
    {
        static $cache = [];

        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $cache[$key] = Schema::hasColumn($table, $column);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }

    protected function areaKeyFromPath(Request $request): string
    {
        $path = (string) $request->path();
        return str_contains($path, 'campesena') ? 'campesena' : 'academic';
    }

    protected function authorizeByArea(string $areaKey): void
    {
        // Si ya lo cubre middleware, esto puede quedar simple:
        if (!function_exists('checkRol')) {
            return;
        }

        $ok = $areaKey === 'campesena'
            ? (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support') || checkRol('gdf.superadmin'))
            : (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support') || checkRol('gdf.superadmin'));

        if (!$ok) {
            abort(403);
        }
    }
    public function approveAllowance(Request $request, int $id, int $allowanceId)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);


        $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $allowanceId, $areaKey, $request) {
                $r = TravelRequest::lockForUpdate()->findOrFail($id);
                $this->ensureRequestMatchesArea($r, $areaKey);

                if ((string)$r->status !== 'approved_by_treasury') {
                    throw new \RuntimeException("La solicitud no está en revisión (status={$r->status}).");
                }

                $a = TravelAllowance::lockForUpdate()
                    ->where('travel_request_id', $r->id)
                    ->findOrFail($allowanceId);

                $a->status = 'approved';

                if ($this->hasColumn('travel_allowances', 'reviewed_by')) $a->reviewed_by = Auth::id();
                if ($this->hasColumn('travel_allowances', 'reviewed_at')) $a->reviewed_at = now();
                if ($this->hasColumn('travel_allowances', 'coordination_comment')) $a->coordination_comment = $request->comment;

                $a->save();

                $this->recalcRequestTotals($r->id);
            });

            return back()->with('success', 'Viático aprobado.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo aprobar el viático: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, int $id)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $areaKey, $data) {
                $r = TravelRequest::lockForUpdate()->findOrFail($id);
                $this->ensureRequestMatchesArea($r, $areaKey);

                if ((string)$r->status !== 'approved_by_treasury') {
                    throw new \RuntimeException("La solicitud ya no está pendiente (status={$r->status}).");
                }

                // ✅ Recalcula (costos + viáticos activos)
                $this->recalcRequestTotals($r->id);

                // (opcional) Si quieres forzar decisión:
                // $pending = \Modules\GDF\Entities\TravelAllowance::where('travel_request_id',$r->id)
                //    ->whereIn('status',['draft','liquidated'])->count();
                // if($pending>0) throw new \RuntimeException("Hay {$pending} viáticos sin decisión.");

                $r->status = 'approved';

                if ($this->hasColumn('travel_requests', 'approved_at')) $r->approved_at = Carbon::now();
                if ($this->hasColumn('travel_requests', 'approved_by')) $r->approved_by = Auth::id();
                if ($this->hasColumn('travel_requests', 'coordination_comment')) $r->coordination_comment = $data['comment'] ?? null;

                if ($this->hasColumn('travel_requests', 'returned_target')) $r->returned_target = null;

                $r->save();

                $this->maybeCreateReview($r->id, 'approved', $data['comment'] ?? null);
            });

            return back()->with('success', 'Aprobado por Coordinación.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }
    }

    public function rejectAllowance(Request $request, int $id, int $allowanceId)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $allowanceId, $areaKey, $request) {
                $r = TravelRequest::lockForUpdate()->findOrFail($id);
                $this->ensureRequestMatchesArea($r, $areaKey);

                if ((string)$r->status !== 'approved_by_treasury') {
                    throw new \RuntimeException("La solicitud no está en revisión (status={$r->status}).");
                }

                $a = TravelAllowance::lockForUpdate()
                    ->where('travel_request_id', $r->id)
                    ->findOrFail($allowanceId);

                $a->status = 'rejected';

                if ($this->hasColumn('travel_allowances', 'reviewed_by')) $a->reviewed_by = Auth::id();
                if ($this->hasColumn('travel_allowances', 'reviewed_at')) $a->reviewed_at = now();
                if ($this->hasColumn('travel_allowances', 'coordination_comment')) $a->coordination_comment = $request->comment;

                $a->save();

                $this->recalcRequestTotals($r->id);
            });

            return back()->with('success', 'Viático declinado.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo declinar el viático: ' . $e->getMessage());
        }
    }
    public function updateAllowance(Request $request, int $id, int $allowanceId)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'calculated_amount' => ['required', 'numeric', 'min:0'],
            'comment'           => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $allowanceId, $areaKey, $data) {
                $r = TravelRequest::lockForUpdate()->findOrFail($id);
                $this->ensureRequestMatchesArea($r, $areaKey);

                if ((string)$r->status !== 'approved_by_treasury') {
                    throw new \RuntimeException("La solicitud no está en revisión (status={$r->status}).");
                }

                $a = TravelAllowance::lockForUpdate()
                    ->where('travel_request_id', $r->id)
                    ->findOrFail($allowanceId);

                // Guarda original una sola vez si tienes columna
                if ($this->hasColumn('travel_allowances', 'original_amount') && empty($a->original_amount)) {
                    $a->original_amount = $a->calculated_amount;
                }

                $a->calculated_amount = (float) $data['calculated_amount'];
                $a->status = 'approved'; // si lo modificó, queda aprobado (o liquidated si prefieres)

                if ($this->hasColumn('travel_allowances', 'reviewed_by')) $a->reviewed_by = Auth::id();
                if ($this->hasColumn('travel_allowances', 'reviewed_at')) $a->reviewed_at = now();
                if ($this->hasColumn('travel_allowances', 'coordination_comment')) $a->coordination_comment = $data['comment'];

                $a->save();

                $this->recalcRequestTotals($r->id);
            });

            return back()->with('success', 'Viático modificado.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo modificar el viático: ' . $e->getMessage());
        }
    }




    protected function applyAreaFilter($query, string $areaKey): void
    {
        // Caso A: columna area_key
        if ($this->hasColumn('travel_requests', 'area_key')) {
            $query->whereRaw('LOWER(area_key) = ?', [strtolower($areaKey)]);
            return;
        }

        // Caso B: columna area_id + tabla areas
        if ($this->hasColumn('travel_requests', 'area_id') && Schema::hasTable('areas')) {
            $query->whereHas('area', function ($q) use ($areaKey) {
                // ajusta a tu tabla areas: slug/key/name
                if (Schema::hasColumn('areas', 'slug')) {
                    $q->whereRaw('LOWER(slug) = ?', [strtolower($areaKey)]);
                } elseif (Schema::hasColumn('areas', 'key')) {
                    $q->whereRaw('LOWER(`key`) = ?', [strtolower($areaKey)]);
                } else {
                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . $areaKey . '%']);
                }
            });
            return;
        }
    }

    private function ensureRequestMatchesArea(TravelRequest $r, string $areaKey): void
    {
        if (!$this->hasColumn('travel_requests', 'area_id')) return;

        $areaIds = (array) config("gdf.area_groups.$areaKey", []);
        if (empty($areaIds)) abort(403, "Área {$areaKey} no configurada en gdf.area_groups.$areaKey");

        if (!in_array((int)$r->area_id, array_map('intval', $areaIds), true)) {
            abort(403, 'La solicitud no pertenece al área actual.');
        }
    }

    protected function countPendingByModule(string $areaKey, string $module, array $pendingStatuses): int
    {
        $q = TravelRequest::query();

        $this->applyAreaFilter($q, $areaKey);

        if ($this->hasColumn('travel_requests', 'status')) {
            $q->whereIn('status', $pendingStatuses);
        }

        if ($this->hasColumn('travel_requests', 'module')) {
            $q->whereRaw('LOWER(COALESCE(module,"gdf")) = ?', [strtolower($module)]);
        } else {
            // Si no existe columna module, asume todo es GDF
            if (strtolower($module) !== 'gdf') {
                return 0;
            }
        }

        return (int) $q->count();
    }

    private function maybeCreateReview(int $requestId, string $action, ?string $comments): void
    {
        if (!class_exists(\Modules\GDF\Entities\TravelReview::class)) return;

        try {
            \Modules\GDF\Entities\TravelReview::create([
                'travel_request_id' => $requestId,
                'reviewer_id'       => Auth::id(),
                'action'            => $action,
                'comments'          => $comments,
            ]);
        } catch (\Throwable $e) {
            // silent
        }
    }
}
