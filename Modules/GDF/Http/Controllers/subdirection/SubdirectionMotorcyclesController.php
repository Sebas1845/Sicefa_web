<?php

namespace Modules\GDF\Http\Controllers\Subdirection;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use Modules\GDF\Entities\Motorcycle;
use Modules\GDF\Entities\MotorcycleAreaTransfer;
use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\BudgetItem;

class SubdirectionMotorcyclesController extends Controller
{
    private function authorizeRole(): void
    {
        $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
        if (!$ok) abort(403);
    }

    // -------------------------
    // LISTADO INVENTARIO
    // -------------------------
    public function index(Request $request)
    {
        $this->authorizeRole();

        $q      = trim((string)$request->get('q', ''));
        $areaId = $request->get('area_id');
        $status = $request->get('status');

        $areas = Area::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        $motorcycles = Motorcycle::query()
            ->select(['id', 'plate', 'brand', 'model', 'current_area_id', 'status', 'current_odometer'])
            ->with(['currentArea:id,name'])
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('plate', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%");
                });
            })
            ->when($areaId, fn($qq) => $qq->where('current_area_id', $areaId))
            ->when($status, fn($qq) => $qq->where('status', $status))
            ->orderBy('plate')
            ->paginate(15)
            ->withQueryString();

        $statsRaw = Motorcycle::query()
            ->selectRaw("
            COUNT(*) as total,
            SUM(status='available') as available,
            SUM(status='assigned') as assigned,
            SUM(status='maintenance') as maintenance,
            SUM(status='retired') as retired
        ")
            ->first();

        $stats = [
            'total'       => (int)($statsRaw->total ?? 0),
            'available'   => (int)($statsRaw->available ?? 0),
            'assigned'    => (int)($statsRaw->assigned ?? 0),
            'maintenance' => (int)($statsRaw->maintenance ?? 0),
            'retired'     => (int)($statsRaw->retired ?? 0),
        ];

        $quotaMeta = [];
        $year = (int) $request->get('year', now()->year);

        return view('gdf::subdirection.motorcycles.index', compact(
            'motorcycles',
            'areas',
            'q',
            'areaId',
            'status',
            'stats',
            'quotaMeta',
            'year'
        ));
    }



    // -------------------------
    // CREAR MOTO
    // -------------------------
    public function create()
    {
        $this->authorizeRole();
        $areas = Area::orderBy('name')->get();
        return view('gdf::subdirection.motorcycles.create', compact('areas'));
    }

    public function store(Request $request)
    {
        $this->authorizeRole();

        $data = $request->validate([
            'plate'            => ['required', 'string', 'max:30', 'unique:motorcycles,plate'],
            'brand'            => ['nullable', 'string', 'max:60'],
            'model'            => ['nullable', 'string', 'max:80'],
            'entry_date'       => ['nullable', 'date'],
            'current_area_id'  => ['nullable', 'integer', 'exists:areas,id'],
            'current_odometer' => ['nullable', 'integer', 'min:0'],
            'status'           => ['required', 'string', 'in:available,assigned,maintenance,retired'],
        ]);

        $m = Motorcycle::create([
            'plate'            => $data['plate'],
            'brand'            => $data['brand'] ?? null,
            'model'            => $data['model'] ?? null,
            'entry_date'       => $data['entry_date'] ?? null,
            'current_area_id'  => $data['current_area_id'] ?? null,
            'current_odometer' => $data['current_odometer'] ?? 0,
            'status'           => $data['status'],
            'created_by'       => Auth::id(),
        ]);

        // Si se asignó área inicial, registra transferencia inicial
        if (!empty($data['current_area_id'])) {
            MotorcycleAreaTransfer::create([
                'motorcycle_id' => $m->id,
                'from_area_id'  => null,
                'to_area_id'    => (int)$data['current_area_id'],
                'assigned_by'   => Auth::id(),
                'assigned_at'   => now(),
                'notes'         => 'Initial assignment on creation',
            ]);
        }

        return redirect()->route('gdf.subdirection.motorcycles.index')
            ->with('success', 'Moto creada correctamente.');
    }

    // -------------------------
    // TRANSFERIR ENTRE ÁREAS
    // -------------------------
    public function transfer(Request $request, Motorcycle $motorcycle)
    {
        $this->authorizeRole();

        $data = $request->validate([
            'to_area_id' => ['required', 'integer', 'exists:areas,id'],
            'notes'      => ['nullable', 'string', 'max:2000'],
        ]);

        // Regla recomendada: no transferir si está asignada a persona
        if ($motorcycle->status === 'assigned') {
            return back()->with('error', 'No se puede transferir una moto asignada. Debe devolverse primero.');
        }

        $from = $motorcycle->current_area_id;

        DB::transaction(function () use ($motorcycle, $data, $from) {
            MotorcycleAreaTransfer::create([
                'motorcycle_id' => $motorcycle->id,
                'from_area_id'  => $from,
                'to_area_id'    => (int)$data['to_area_id'],
                'assigned_by'   => Auth::id(),
                'assigned_at'   => now(),
                'notes'         => $data['notes'] ?? null,
            ]);

            $motorcycle->current_area_id = (int)$data['to_area_id'];
            $motorcycle->save();
        });

        return back()->with('success', 'Moto transferida correctamente.');
    }
    /* ===========================================================
 * SOLICITUDES (Bandeja Subdirección)
 * =========================================================== */
    public function requestsIndex(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $year   = (int) $request->get('year', now()->year);
        $module = $request->get('module'); // gdf|sitrav|null
        $status = $request->get('status'); // submitted|approved|...
        $q      = trim((string) $request->get('q', ''));

        $peopleTable = $this->tableExists('people') ? 'people' : null;

        $nameExpr = $peopleTable
            ? "TRIM(CONCAT_WS(' ',
            NULLIF(p.first_name,''),
            NULLIF(p.first_last_name,''),
            NULLIF(p.second_last_name,'')
          ))"
            : "'—'";

        $query = DB::table('travel_requests as tr')
            ->when($peopleTable, fn($qq) => $qq->leftJoin($peopleTable . ' as p', 'p.id', '=', 'tr.person_id'));

        if ($this->columnExists('travel_requests', 'start_date')) {
            $query->whereYear('tr.start_date', $year);
        }

        if ($module && in_array($module, ['gdf', 'sitrav'], true)) {
            $query->where('tr.module', $module);
        }

        if ($status) {
            $query->where('tr.status', $status);
        }

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($w) use ($q, $like) {
                if (ctype_digit($q)) $w->orWhere('tr.id', (int)$q);

                foreach (['origin', 'destination', 'request_type', 'person_type', 'notes'] as $col) {
                    if ($this->columnExists('travel_requests', $col)) {
                        $w->orWhere("tr.$col", 'like', $like);
                    }
                }
            });
        }

        $requests = $query->selectRaw("
            tr.*,
            COALESCE(NULLIF($nameExpr,''), '—') as person_name
        ")
            ->orderByDesc('tr.updated_at')
            ->paginate(15)
            ->appends($request->query());

        return view('gdf::subdirection.requests.index', compact(
            'requests',
            'year',
            'module',
            'status',
            'q'
        ));
    }

    public function requestsShow(Request $request, int $id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('travel_requests');

        $tr = DB::table('travel_requests')->where('id', $id)->first();
        if (!$tr) abort(404);

        $segments = collect();
        if ($this->tableExists('travel_segments')) {
            $segments = DB::table('travel_segments')
                ->where('travel_request_id', $id)
                ->orderBy('departure_at')
                ->get();
        }

        $costs = collect();
        if ($this->tableExists('travel_costs')) {
            $costs = DB::table('travel_costs')
                ->where('travel_request_id', $id)
                ->orderBy('id')
                ->get();
        }

        $reviews = collect();
        if ($this->tableExists('travel_reviews') && $this->columnExists('travel_reviews', 'travel_request_id')) {
            $reviews = DB::table('travel_reviews')
                ->where('travel_request_id', $id)
                ->orderByDesc('id')
                ->get();
        }

        return view('gdf::subdirection.requests.show', compact('tr', 'segments', 'costs', 'reviews'));
    }

    public function requestsApprove(Request $request, int $id)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $row = DB::table('travel_requests')->where('id', $id)->lockForUpdate()->first();
                if (!$row) abort(404);

                if (($row->status ?? '') !== 'submitted') {
                    throw new \RuntimeException("La solicitud no está pendiente (status={$row->status}).");
                }

                DB::table('travel_requests')->where('id', $id)->update([
                    'status' => 'approved',
                    'approved_at' => $this->columnExists('travel_requests', 'approved_at') ? now() : DB::raw('approved_at'),
                    'updated_at' => now(),
                ]);

                $this->subdirectionReviewLog($id, 'approved', $data['comment'] ?? null);
            });

            return back()->with('success', 'Solicitud aprobada por Subdirección.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo aprobar: ' . $e->getMessage());
        }
    }

    public function requestsReject(Request $request, int $id)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $row = DB::table('travel_requests')->where('id', $id)->lockForUpdate()->first();
                if (!$row) abort(404);

                if (($row->status ?? '') !== 'submitted') {
                    throw new \RuntimeException("La solicitud no está pendiente (status={$row->status}).");
                }

                DB::table('travel_requests')->where('id', $id)->update([
                    'status' => 'rejected',
                    'updated_at' => now(),
                ]);

                $this->subdirectionReviewLog($id, 'rejected', $data['comment']);
            });

            return back()->with('success', 'Solicitud rechazada por Subdirección.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo rechazar: ' . $e->getMessage());
        }
    }

    public function requestsReturn(Request $request, int $id)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'target'  => ['required', 'in:support,applicant'],
            'comment' => ['required', 'string', 'max:2000'],
        ]);

        try {
            DB::transaction(function () use ($id, $data) {
                $row = DB::table('travel_requests')->where('id', $id)->lockForUpdate()->first();
                if (!$row) abort(404);

                if (($row->status ?? '') !== 'submitted') {
                    throw new \RuntimeException("La solicitud no está pendiente (status={$row->status}).");
                }

                $upd = [
                    'status' => 'returned',
                    'updated_at' => now(),
                ];

                if ($this->columnExists('travel_requests', 'returned_target')) {
                    $upd['returned_target'] = $data['target'];
                }

                DB::table('travel_requests')->where('id', $id)->update($upd);

                $this->subdirectionReviewLog($id, 'returned', $data['comment']);
            });

            return back()->with('success', 'Solicitud devuelta correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo devolver: ' . $e->getMessage());
        }
    }

    private function subdirectionReviewLog(int $travelRequestId, string $action, ?string $comments): void
    {
        if (!$this->tableExists('travel_reviews')) return;
        if (!$this->columnExists('travel_reviews', 'travel_request_id')) return;

        try {
            DB::table('travel_reviews')->insert([
                'travel_request_id' => $travelRequestId, // ✅ OJO: ESTA es la columna real
                'reviewer_id' => Auth::id(),
                'action' => $action,
                'comments' => $comments,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // no tumbar la transacción
        }
    }

    /* ===========================================================
 * RUBROS (BudgetItem)
 * =========================================================== */
    public function rubrosIndex(Request $request)
    {
        $this->guardSubdirection();

        $q = trim((string)$request->get('q', ''));

        $rubros = BudgetItem::query()
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(15)
            ->appends($request->query());

        return view('gdf::subdirection.rubros.index', compact('rubros', 'q'));
    }

    public function rubrosCreate()
    {
        $this->guardSubdirection();
        return view('gdf::subdirection.rubros.create');
    }

    public function rubrosStore(Request $request)
    {
        $this->guardSubdirection();

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'active' => ['nullable'],
        ]);

        $data['active'] = (bool) $request->input('active', true);

        BudgetItem::create($data);

        return redirect()->route('gdf.subdirection.rubros')->with('success', 'Rubro creado.');
    }

    public function rubrosEdit(int $id)
    {
        $this->guardSubdirection();
        $rubro = BudgetItem::findOrFail($id);
        return view('gdf::subdirection.rubros.edit', compact('rubro'));
    }

    public function rubrosUpdate(Request $request, int $id)
    {
        $this->guardSubdirection();

        $rubro = BudgetItem::findOrFail($id);

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'active' => ['nullable'],
        ]);

        $data['active'] = (bool) $request->input('active', true);

        $rubro->update($data);

        return redirect()->route('gdf.subdirection.rubros')->with('success', 'Rubro actualizado.');
    }

    /* ===========================================================
 * PRESUPUESTOS (budgets table) - implementado con DB para no
 * depender de modelo si todavía no existe.
 * =========================================================== */
    public function budgetsIndex(Request $request)
    {
        $this->guardSubdirection();

        if (!$this->tableExists('budgets')) {
            return view('gdf::subdirection.budgets.index', [
                'budgets' => collect(),
                'year' => (int)$request->get('year', now()->year),
                'q' => trim((string)$request->get('q', '')),
                'warning' => 'Tabla budgets no existe.',
            ]);
        }

        $year = (int) $request->get('year', now()->year);
        $q    = trim((string)$request->get('q', ''));

        $query = DB::table('budgets');

        if ($this->columnExists('budgets', 'year')) $query->where('year', $year);
        if ($this->columnExists('budgets', 'active')) $query->where('active', 1);

        if ($q !== '') {
            $like = "%{$q}%";
            $query->where(function ($w) use ($like) {
                foreach (['name', 'code', 'notes'] as $col) {
                    if (DB::getSchemaBuilder()->hasColumn('budgets', $col)) {
                        $w->orWhere($col, 'like', $like);
                    }
                }
            });
        }

        $budgets = $query->orderByDesc('id')
            ->paginate(15)
            ->appends($request->query());

        return view('gdf::subdirection.budgets.index', compact('budgets', 'year', 'q'));
    }

    public function budgetsCreate()
    {
        $this->guardSubdirection();
        return view('gdf::subdirection.budgets.create');
    }

    public function budgetsStore(Request $request)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');

        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'name' => ['required', 'string', 'max:150'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'current_amount' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable'],
        ]);

        DB::table('budgets')->insert([
            'year' => (int)$data['year'],
            'name' => $data['name'],
            'total_amount' => $data['total_amount'],
            'current_amount' => $data['current_amount'],
            'active' => (int) $request->input('active', 1),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('gdf.subdirection.budgets.index', ['year' => $data['year']])
            ->with('success', 'Presupuesto creado.');
    }

    public function budgetsEdit(int $id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');

        $budget = DB::table('budgets')->where('id', $id)->first();
        if (!$budget) abort(404);

        return view('gdf::subdirection.budgets.edit', compact('budget'));
    }

    public function budgetsUpdate(Request $request, int $id)
    {
        $this->guardSubdirection();
        $this->ensureTableOrFail('budgets');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'current_amount' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable'],
        ]);

        DB::table('budgets')->where('id', $id)->update([
            'name' => $data['name'],
            'total_amount' => $data['total_amount'],
            'current_amount' => $data['current_amount'],
            'active' => (int) $request->input('active', 1),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Presupuesto actualizado.');
    }

    public function budgetsPercentagesEdit(int $id)
    {
        $this->guardSubdirection();
        return view('gdf::subdirection.budgets.percentages', compact('id'));
    }

    public function budgetsPercentagesStore(Request $request, int $id)
    {
        $this->guardSubdirection();
        return back()->with('info', 'Percentages guardado (stub).');
    }

    public function budgetsAreasEdit(int $id)
    {
        $this->guardSubdirection();
        return view('gdf::subdirection.budgets.areas', compact('id'));
    }

    public function budgetsAreasStore(Request $request, int $id)
    {
        $this->guardSubdirection();
        return back()->with('info', 'Áreas guardadas (stub).');
    }
}
