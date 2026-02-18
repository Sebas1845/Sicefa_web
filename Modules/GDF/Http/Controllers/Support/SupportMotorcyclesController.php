<?php

namespace Modules\GDF\Http\Controllers\Support;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Modules\GDF\Entities\Motorcycle;
use Modules\GDF\Entities\MotorcycleAssignment;
use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelAllowance;
use Modules\GDF\Entities\TravelSegment;

use Carbon\Carbon;

class SupportMotorcyclesController extends Controller
{
    /* ============================================================
     * AUTH / AREA
     * ============================================================ */
    private function authorizeSupport(string $areaKey): void
    {
        $ok = function_exists('checkRol') && (
            ($areaKey === 'academic'  && checkRol('gdf.academic_support')) ||
            ($areaKey === 'campesena' && checkRol('gdf.campesena_support')) ||
            checkRol('gdf.superadmin')
        );
        if (!$ok) abort(403);
    }

    private function areaKeyFromPath(Request $request): string
    {
        $path = $request->path(); // ej: gdf/support/academica/...
        return str_contains($path, 'support/campesena') ? 'campesena' : 'academic';
    }

    private function areaIdsForKey(string $areaKey): array
    {
        $q = DB::table('areas');

        if (Schema::hasColumn('areas', 'key')) {
            $q->where('key', $areaKey);
        } elseif (Schema::hasColumn('areas', 'slug')) {
            $q->where('slug', $areaKey);
        } else {
            if ($areaKey === 'campesena') $q->where('name', 'like', '%CAMP%');
            else $q->where('name', 'like', '%ACA%');
        }

        return $q->pluck('id')->map(fn($v) => (int)$v)->values()->all();
    }

    private function routePrefix(string $areaKey): string
    {
        return $areaKey === 'campesena' ? 'gdf.support.campesena' : 'gdf.support.academic';
    }

    /* ============================================================
     * ESTADOS
     * ============================================================ */
    private function activeStatuses(): array
    {
        return ['approved', 'delivered']; // activas (cuentan cupo)
    }

    private function assignmentBlockingStatuses(): array
    {
        return $this->activeStatuses();
    }

    private function quotaTotalsForAreas(array $areaIds, int $year): array
    {
        $quotaTotal = (int) DB::table('motorcycle_area_quotas')
            ->where('active', 1)
            ->when(!empty($areaIds), fn($q) => $q->whereIn('area_id', $areaIds))
            ->where('year', $year) // ✅ SOLO ese año
            ->sum('quota_total');

        $quotaUsed = (int) MotorcycleAssignment::query()
            ->where('assignment_year', $year) // ✅ SOLO ese año
            ->whereNull('returned_at')
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->when(!empty($areaIds), fn($q) => $q->whereIn('area_id', $areaIds))
            ->count();

        $quotaRemaining = max(0, $quotaTotal - $quotaUsed);
        $quotaExceeded  = ($quotaTotal > 0) && ($quotaUsed > $quotaTotal);
        $overBy         = $quotaExceeded ? ($quotaUsed - $quotaTotal) : 0;

        return compact('quotaTotal', 'quotaUsed', 'quotaRemaining', 'quotaExceeded', 'overBy');
    }




    private function setMotorcycleStatus(?int $motorcycleId, string $status): void
    {
        if (!$motorcycleId) return;
        Motorcycle::where('id', $motorcycleId)->update(['status' => $status]);
    }

    private function getActiveAssignmentOrFail(MotorcycleAssignment $assignment): MotorcycleAssignment
    {
        if ($assignment->returned_at !== null) abort(422, 'La asignación ya fue cerrada (returned_at).');
        return $assignment;
    }

    /* ============================================================
     * CUPOS (motorcycle_area_quotas) - AHORA POR AÑO
     * ============================================================ */


    private function ensureQuotaAvailableForAreas(array $areaIds, int $year): void
    {
        $q = $this->quotaTotalsForAreas($areaIds, $year);

        if ((int)$q['quotaTotal'] <= 0) {
            abort(422, "No hay cupo configurado para motos en $year (motorcycle_area_quotas).");
        }
        if ((bool)$q['quotaExceeded']) {
            abort(422, "Cupo excedido en $year. Corrige devoluciones/cierres o ajusta cupo.");
        }
        if ((int)$q['quotaUsed'] >= (int)$q['quotaTotal']) {
            abort(422, "No hay cupo disponible de motos para $year en el área seleccionada.");
        }
    }

    /* ============================================================
     * SOLAPAMIENTO (POR FECHAS) - SOLO PARA ASIGNACIÓN POR SOLICITUD
     * ============================================================ */
    private function ensureMotorcycleNotOverlapping(int $motorcycleId, string $startAt, string $endAt): void
    {
        $start = Carbon::parse($startAt);
        $end   = Carbon::parse($endAt);

        $exists = MotorcycleAssignment::query()
            ->where('motorcycle_id', $motorcycleId)
            ->whereNull('returned_at')
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->whereNotNull('start_at')
            ->whereNotNull('end_at')
            ->where(function ($q) use ($start, $end) {
                $q->where('start_at', '<=', $end)
                    ->where('end_at', '>=', $start);
            })
            ->exists();

        if ($exists) abort(422, 'Esa moto ya está ocupada en ese rango (solapamiento).');
    }

    /* ============================================================
     * HELPERS TR (gasolina + segmentos)
     * ============================================================ */


    protected function lockSegmentsToMotoRange(int $travelRequestId, string $startAt, string $endAt): void
    {
        TravelSegment::query()
            ->where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->where(function ($q) use ($startAt, $endAt) {
                $q->where('departure_at', '<=', $endAt)
                    ->whereRaw('COALESCE(return_at, departure_at) >= ?', [$startAt]);
            })
            ->update([
                'transport_type' => 'moto',
                'trip_type'      => 'round_trip',
                'trips'          => 2,
                'transport_cost' => 0,
                'total_cost'     => DB::raw('0 + COALESCE(per_diem_cost,0) + COALESCE(other_cost,0)'),
                'updated_at'     => now(),
            ]);
    }


    /* ============================================================
     * GET: COLA / DASHBOARD MOTOS (FILTRA POR AÑO)
     * ============================================================ */
    public function queue(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $year   = (int) $request->get('year', now()->year);
        $areaId = (int) $request->get('area_id', 0);
        $type   = (string) $request->get('type', 'all');

        $areaIds = $this->areaIdsForKey($areaKey);
        $areaIdsFiltered = ($areaId > 0 && in_array($areaId, $areaIds, true)) ? [$areaId] : $areaIds;

        $items = MotorcycleAssignment::query()
            ->with(['person', 'area', 'motorcycle'])
            ->where('assignment_year', $year) // ✅
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->whereNull('returned_at')
            ->when(!empty($areaIdsFiltered), fn($q) => $q->whereIn('area_id', $areaIdsFiltered))
            ->when($type === 'direct', fn($q) => $q->whereNull('travel_requestable_id'))
            ->when($type === 'request', fn($q) => $q->whereNotNull('travel_requestable_id'))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $quota = $this->quotaTotalsForAreas($areaIdsFiltered, $year);

        $areas = DB::table('areas')
            ->select('id', 'name')
            ->when(!empty($areaIds), fn($q) => $q->whereIn('id', $areaIds))
            ->orderBy('name')
            ->get();

        $motosAvailable = Motorcycle::query()
            ->where('status', 'available')
            ->when(!empty($areaIdsFiltered), fn($q) => $q->whereIn('current_area_id', $areaIdsFiltered))
            ->orderBy('plate')
            ->get();

        $motosInventory = Motorcycle::query()
            ->when(!empty($areaIdsFiltered), fn($q) => $q->whereIn('current_area_id', $areaIdsFiltered))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
        $people = DB::table('person_area_budget_assignments as pa')
            ->join('people as p', 'p.id', '=', 'pa.person_id')
            ->where('pa.is_active', 1)
            ->when(!empty($areaIdsFiltered), fn($q) => $q->whereIn('pa.area_id', $areaIdsFiltered))
            ->select([
                'p.id',
                'p.first_name', // ✅ para poder ordenar
                DB::raw("
            CONCAT(
                p.first_name, ' ',
                p.first_last_name,
                IF(p.second_last_name IS NULL OR p.second_last_name = '', '', CONCAT(' ', p.second_last_name)),
                ' - ', p.document_number
            ) as name
        "),
            ])
            ->distinct()
            ->orderBy('p.first_name')
            ->limit(5000) // o 2000, lo que te aguante
            ->get()
            ->map(fn($r) => (object)[
                'id'   => $r->id,
                'name' => $r->name,
            ]);



        $titleId = 'Cola de Asignaciones de Motocicletas · ' . strtoupper($areaKey);

        return view('gdf::support.motorcycles.queue', array_merge(compact(
            'items',
            'areaKey',
            'areaId',
            'type',
            'year',
            'areas',
            'motosAvailable',
            'motosInventory',
            'people',

            'titleId'
        ), $quota));
    }

    /* ============================================================
     * GET: DEVOLUCIONES (FILTRA POR AÑO)
     * ============================================================ */
    public function returnIndex(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $year   = (int) $request->get('year', now()->year);
        $areaId = (int) $request->get('area_id', 0);
        $q      = trim((string) $request->get('q', ''));

        $areaIds = $this->areaIdsForKey($areaKey);
        $areaIdsFiltered = ($areaId > 0 && in_array($areaId, $areaIds, true)) ? [$areaId] : $areaIds;

        $items = MotorcycleAssignment::query()
            ->with([
                'person',
                'area',
                'motorcycle',
                // ✅ traer el polimórfico (si es TravelRequest, luego en la vista lees status)
                'travel_requestable' => function ($morph) {
                    // no hace falta nada aquí, pero queda explícito
                },
            ])
            ->where('assignment_year', $year)
            ->where('status', 'delivered')
            ->whereNull('returned_at')
            ->when(!empty($areaIdsFiltered), fn($qq) => $qq->whereIn('area_id', $areaIdsFiltered))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->whereHas('motorcycle', fn($m) => $m->where('plate', 'like', "%{$q}%"));
            })
            ->orderByDesc('delivered_at')
            ->paginate(15)
            ->withQueryString();

        // ✅ set de estados que bloquean “quitar” (por seguridad también en backend)
        $lockStatuses = ['pending_treasury', 'approved_by_treasury', 'confirmed', 'executed'];

        return view('gdf::support.motorcycles.return', compact(
            'items',
            'areaKey',
            'year',
            'areaId',
            'q',
            'lockStatuses'
        ));
    }
    private function ensureNotLockedByTreasury(MotorcycleAssignment $a): void
    {
        if (!empty($a->travel_requestable_id) && $a->travel_requestable_type === TravelRequest::class) {
            $tr = TravelRequest::find($a->travel_requestable_id);

            $lock = ['pending_treasury', 'approved_by_treasury', 'confirmed', 'executed'];
            if ($tr && in_array($tr->status, $lock, true)) {
                abort(422, "No se puede quitar: la solicitud está en estado {$tr->status} (Tesorería/proceso).");
            }
        }
    }



    /* ============================================================
     * POST: CREAR MOTO (INVENTARIO) - NO CONSUME CUPO
     * ============================================================ */
    public function storeMoto(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'plate'            => ['required', 'string', 'max:30', 'unique:motorcycles,plate'],
            'brand'            => ['nullable', 'string', 'max:60'],
            'model'            => ['nullable', 'string', 'max:80'],
            'entry_date'       => ['nullable', 'date'],
            'current_area_id'  => ['required', 'integer'],
            'current_odometer' => ['nullable', 'integer', 'min:0'],
            'status'           => ['required', 'string', 'in:available,maintenance,inactive,assigned,delivered'],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ]);

        $allowedAreaIds = $this->areaIdsForKey($areaKey);
        if (!in_array((int)$data['current_area_id'], $allowedAreaIds, true)) {
            abort(422, 'El área seleccionada no pertenece a tu módulo (academic/campesena).');
        }

        $year = (int) $request->input('year', now()->year);
        $areaId = (int) $data['current_area_id'];

        // ✅ 1) Cupo configurado para esa área y año
        $quotaTotal = (int) DB::table('motorcycle_area_quotas')
            ->where('active', 1)
            ->where('area_id', $areaId)
            ->where('year', $year)
            ->sum('quota_total');

        if ($quotaTotal <= 0) {
            return back()->with('error', "No hay cupo configurado para el área seleccionada en la vigencia {$year}.");
        }

        // ✅ 2) Contar inventario actual del área (todas las motos existentes en esa área)
        // OJO: esto NO depende de assignments, es inventario puro.
        $inventoryCount = (int) Motorcycle::query()
            ->where('current_area_id', $areaId)
            ->whereNotIn('status', ['inactive']) // si quieres que inactivas no cuenten, déjalo así
            ->count();

        if ($inventoryCount >= $quotaTotal) {
            return back()->with('error', "No hay más cupo de inventario para esta área ({$year}). Cupo: {$quotaTotal}, Inventario: {$inventoryCount}.");
        }

        $m = new Motorcycle();
        $m->plate            = $data['plate'];
        $m->brand            = $data['brand'] ?? null;
        $m->model            = $data['model'] ?? null;
        $m->entry_date       = $data['entry_date'] ?? null;
        $m->current_area_id  = $areaId;
        $m->current_odometer = (int) ($data['current_odometer'] ?? 0);
        $m->status           = $data['status'] ?? 'available';
        $m->created_by       = Auth::id();

        if (Schema::hasColumn('motorcycles', 'notes')) {
            $m->notes = $data['notes'] ?? null;
        }

        $m->save();

        return redirect()->route($this->routePrefix($areaKey) . '.motorcycles.queue', [
            'year' => $year,
            'area_id' => $areaId,
        ])->with('success', 'Moto creada correctamente y asignada al área de inventario.');
    }


    /* ============================================================
     * PATCH: ESTADO INVENTARIO
     * ============================================================ */
    public function updateMotoStatus(Request $request, Motorcycle $motorcycle)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $data = $request->validate([
            'status' => ['required', 'in:available,maintenance,inactive'],
        ]);

        $current = strtolower((string)($motorcycle->status ?? ''));

        if (in_array($current, ['assigned', 'delivered'], true)) {
            return back()->with('error', 'No puedes cambiar el estado: la moto está asignada/entregada.');
        }

        $motorcycle->status = $data['status'];
        $motorcycle->save();

        return back()->with('success', 'Estado de la moto actualizado.');
    }

    /* ============================================================
     * POST: ASIGNACIÓN DIRECTA (CONSUME CUPO DEL AÑO)
     * ============================================================ */
    public function storeDirect(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $year = (int) now()->year; // ✅ vigencia

        $data = $request->validate([
            'person_id'      => ['required', 'integer'],
            'motorcycle_id'  => ['required', 'integer'],
            'area_id'        => ['required', 'integer'],
            'budget_item_id' => ['nullable', 'integer'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);

        // ✅ Persona debe estar asociada al área (tabla real)
        $belongs = DB::table('person_area_budget_assignments')
            ->where('is_active', 1)
            ->where('person_id', (int)$data['person_id'])
            ->where('area_id', (int)$data['area_id'])
            ->exists();

        if (!$belongs) {
            abort(422, 'La persona NO está asociada al área seleccionada (asignación activa).');
        }

        // ✅ La moto debe pertenecer al inventario del área (si tu regla es por área)
        $okMotoArea = Motorcycle::query()
            ->where('id', (int)$data['motorcycle_id'])
            ->where('status', 'available')
            ->where('current_area_id', (int)$data['area_id'])
            ->exists();

        if (!$okMotoArea) {
            abort(422, 'La moto no está disponible o no pertenece al inventario del área seleccionada.');
        }


        $this->ensureQuotaAvailableForAreas([(int)$data['area_id']], $year);
        $this->ensureMotorcycleNotActiveInYear((int)$data['motorcycle_id'], $year);
        $this->ensurePersonNoDirectActiveInYear((int)$data['person_id'], $year);

        DB::transaction(function () use ($data, $year) {
            $a = new MotorcycleAssignment();
            $a->motorcycle_id   = (int)$data['motorcycle_id'];
            $a->person_id       = (int)$data['person_id'];
            $a->area_id         = (int)$data['area_id'];
            $a->budget_item_id  = $data['budget_item_id'] ?? null;

            $a->assignment_year = $year; // ✅
            $a->start_at        = null;
            $a->end_at          = null;

            $a->travel_requestable_type = null;
            $a->travel_requestable_id   = null;

            $a->status           = 'approved';
            $a->requested_by     = Auth::id();
            $a->approved_by      = Auth::id();
            $a->managed_by       = Auth::id();
            $a->observations_out = $data['notes'] ?? null;
            $a->save();

            $this->setMotorcycleStatus($a->motorcycle_id, 'assigned');
        });

        return back()->with('success', "Asignación directa creada para vigencia $year (estado approved).");
    }
    private function ensureMotorcycleNotActiveInYear(int $motorcycleId, int $year): void
    {
        $exists = MotorcycleAssignment::query()
            ->where('assignment_year', $year)
            ->where('motorcycle_id', $motorcycleId)
            ->whereNull('returned_at')
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->exists();

        if ($exists) abort(422, "Esa moto ya está activa en la vigencia {$year}.");
    }

    private function ensurePersonNoDirectActiveInYear(int $personId, int $year): void
    {
        $exists = MotorcycleAssignment::query()
            ->where('assignment_year', $year)
            ->where('person_id', $personId)
            ->whereNull('travel_requestable_id') // directa
            ->whereNull('returned_at')
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->exists();

        if ($exists) abort(422, "Esta persona ya tiene una moto directa activa en la vigencia {$year}.");
    }


    /* ============================================================
     * GET: RECIBO
     * ============================================================ */
    public function assignmentReceipt(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $assignment->load(['person', 'area', 'motorcycle']);

        return view('gdf::support.motorcycles.receipt', compact('assignment', 'areaKey'));
    }

    public function storeForRequest(Request $request, TravelRequest $travelRequest)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $tr = TravelRequest::query()->with(['person', 'area'])->findOrFail($travelRequest->id);

        $personId = (int)($tr->person_id ?? 0);
        if ($personId <= 0) {
            return back()->withErrors(['person_id' => 'La solicitud no tiene person_id. No se puede asignar moto.']);
        }

        $data = $request->validate([
            'motorcycle_id'  => ['required', 'integer'],
            'start_at'       => ['required', 'date'],
            'end_at'         => ['required', 'date', 'after_or_equal:start_at'],
            'budget_item_id' => ['nullable', 'integer'],
            'notes'          => ['nullable', 'string', 'max:2000'],
        ]);

        // ✅ Vigencia = año del start_at
        $year = (int) \Carbon\Carbon::parse($data['start_at'])->format('Y');

        $this->ensureQuotaAvailableForAreas([(int)$tr->area_id], $year);

        // ✅ Evita dobles asignaciones en el mismo año
        $this->ensureMotorcycleNotActiveInYear((int)$data['motorcycle_id'], $year);

        // ✅ Y además solape real por fechas
        $this->ensureMotorcycleNotOverlapping((int)$data['motorcycle_id'], $data['start_at'], $data['end_at']);

        $existsForReq = \Modules\GDF\Entities\MotorcycleAssignment::query()
            ->where('travel_requestable_type', \Modules\GDF\Entities\TravelRequest::class)
            ->where('travel_requestable_id', $tr->id)
            ->where('assignment_year', $year)
            ->whereNull('returned_at')
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->exists();

        if ($existsForReq) {
            return back()->with('error', 'Esta solicitud ya tiene una asignación activa en esa vigencia.');
        }

        \DB::transaction(function () use ($tr, $data, $personId, $year) {
            $a = new \Modules\GDF\Entities\MotorcycleAssignment();
            $a->motorcycle_id  = (int)$data['motorcycle_id'];
            $a->person_id      = $personId;
            $a->area_id        = (int)($tr->area_id ?? 0);
            $a->budget_item_id = $data['budget_item_id'] ?? null;

            $a->assignment_year = $year;
            $a->start_at        = $data['start_at'];
            $a->end_at          = $data['end_at'];

            $a->travel_requestable_type = \Modules\GDF\Entities\TravelRequest::class;
            $a->travel_requestable_id   = $tr->id;

            $a->status           = 'approved';
            $a->requested_by     = \Auth::id();
            $a->approved_by      = \Auth::id();
            $a->managed_by       = \Auth::id();
            $a->observations_out = $data['notes'] ?? null;
            $a->save();

            $this->setMotorcycleStatus($a->motorcycle_id, 'assigned');

            // ✅ Bloquea transporte en segmentos dentro del rango
            $this->lockSegmentsToMotoRange($tr->id, $data['start_at'], $data['end_at']);

            if (($tr->status ?? '') === 'submitted') {
                $tr->status = 'approved';
                $tr->save();
            }

            $this->recalcRequestTotals($tr->id);
        });

        return back()->with('success', "Moto asignada a la solicitud para vigencia $year. Segmentos en rango ⇒ transporte $0.");
    }


    /* ============================================================
     * POST: LIBERAR MOTO DE SOLICITUD
     * ============================================================ */
    public function releaseForRequest(Request $request, TravelRequest $travelRequest)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $tr = TravelRequest::findOrFail($travelRequest->id);

        // Puedes enviar year explícito, si no, toma el actual
        $year = (int) $request->get('year', now()->year);

        $assignment = MotorcycleAssignment::query()
            ->where('travel_requestable_type', TravelRequest::class)
            ->where('travel_requestable_id', $tr->id)
            ->where('assignment_year', $year) // ✅
            ->whereNull('returned_at')
            ->whereIn('status', $this->assignmentBlockingStatuses())
            ->latest('id')
            ->first();

        if (!$assignment) {
            return back()->with('error', "Esta solicitud no tiene asignación activa para liberar en $year.");
        }

        $data = $request->validate([
            'odometer_in'     => ['nullable', 'integer', 'min:0'],
            'observations_in' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($assignment, $data) {
            $assignment->returned_at = now();
            $assignment->status = 'returned';
            $assignment->odometer_in = $data['odometer_in'] ?? $assignment->odometer_in;
            $assignment->observations_in = $data['observations_in'] ?? $assignment->observations_in;
            $assignment->managed_by = Auth::id();
            $assignment->save();

            Motorcycle::where('id', (int)$assignment->motorcycle_id)->update([
                'status' => 'available',
                'current_odometer' => (int)($data['odometer_in']
                    ?? (Motorcycle::where('id', $assignment->motorcycle_id)->value('current_odometer') ?? 0)),
            ]);
        });

        return back()->with('success', "Moto liberada para la solicitud (vigencia {$assignment->assignment_year}).");
    }

    /* ============================================================
     * POST: ENTREGAR
     * ============================================================ */
    public function deliver(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $assignment = $this->getActiveAssignmentOrFail($assignment);

        $data = $request->validate([
            'odometer_out'     => ['required', 'integer', 'min:0'],
            'observations_out' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($assignment->status !== 'approved') {
            return back()->with('error', 'Solo se puede entregar una asignación en estado approved.');
        }

        DB::transaction(function () use ($assignment, $data) {
            $assignment->delivered_at = now();
            $assignment->odometer_out = (int)$data['odometer_out'];
            $assignment->observations_out = $data['observations_out'] ?? $assignment->observations_out;
            $assignment->status = 'delivered';
            $assignment->managed_by = Auth::id();
            $assignment->save();

            Motorcycle::where('id', (int)$assignment->motorcycle_id)->update([
                'status'           => 'delivered',
                'current_odometer' => (int)$data['odometer_out'],
            ]);
        });

        return back()->with('success', 'Moto entregada correctamente.');
    }

    /* ============================================================
     * POST: DEVOLVER
     * ============================================================ */
    public function returnAssignment(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $assignment = $this->getActiveAssignmentOrFail($assignment);

        $data = $request->validate([
            'odometer_in'     => ['required', 'integer', 'min:0'],
            'observations_in' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($assignment->status !== 'delivered') {
            return back()->with('error', 'Solo se puede devolver una asignación en estado delivered.');
        }

        if (!is_null($assignment->odometer_out) && (int)$data['odometer_in'] < (int)$assignment->odometer_out) {
            return back()->with('error', 'El odómetro de entrada no puede ser menor al de salida.');
        }

        DB::transaction(function () use ($assignment, $data) {
            $assignment->returned_at = now();
            $assignment->odometer_in = (int)$data['odometer_in'];
            $assignment->observations_in = $data['observations_in'] ?? null;
            $assignment->status = 'returned';
            $assignment->managed_by = Auth::id();
            $assignment->save();

            Motorcycle::where('id', (int)$assignment->motorcycle_id)->update([
                'status'           => 'available',
                'current_odometer' => (int)$data['odometer_in'],
            ]);
        });

        return back()->with('success', 'Moto devuelta correctamente.');
    }

    /* ============================================================
     * POST: CANCELAR
     * ============================================================ */
    public function cancel(Request $request, MotorcycleAssignment $assignment)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeSupport($areaKey);

        $assignment = $this->getActiveAssignmentOrFail($assignment);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($assignment->status !== 'approved') {
            return back()->with('error', 'Solo se puede cancelar una asignación en estado approved.');
        }

        DB::transaction(function () use ($assignment, $data) {
            $assignment->status = 'cancelled';
            $assignment->managed_by = Auth::id();
            $assignment->observations_out = trim(($assignment->observations_out ?? '') . "\nCANCEL: " . ($data['reason'] ?? ''));
            $assignment->save();

            $this->setMotorcycleStatus((int)$assignment->motorcycle_id, 'available');
        });

        return back()->with('success', 'Asignación cancelada.');
    }
    public function assignMotoForRequest(TravelRequest $travelRequest, Request $request)
    {
        // Gate mínimo
        if (!Auth::user() || !Auth::user()->person) abort(403);

        // Para redirecciones según área
        $areaKey = str_contains($request->path(), 'support/campesena') ? 'campesena' : 'academic';
        $routePrefix = $areaKey === 'campesena' ? 'gdf.support.campesena' : 'gdf.support.academic';

        $tr = TravelRequest::query()->findOrFail($travelRequest->id);

        $data = $request->validate([
            'motorcycle_id' => ['required', 'integer', 'exists:motorcycles,id'],
            'start_at'      => ['required', 'date'],
            'end_at'        => ['required', 'date', 'after:start_at'],
        ]);

        $start = Carbon::parse($data['start_at']);
        $end   = Carbon::parse($data['end_at']);

        $startAt = $start->format('Y-m-d H:i:s');
        $endAt   = $end->format('Y-m-d H:i:s');

        // ✅ CLAVE por tu tabla: NOT NULL sin default
        $assignmentYear = (int) $start->format('Y');

        $blockingStatuses = ['approved', 'delivered']; // ajusta si tu negocio lo pide

        // 1) Validar solape moto
        $overlapMoto = DB::table('motorcycle_assignments')
            ->whereNull('returned_at')
            ->whereIn('status', $blockingStatuses)
            ->where('motorcycle_id', (int)$data['motorcycle_id'])
            ->whereNotNull('start_at')->whereNotNull('end_at')
            ->where(function ($q) use ($startAt, $endAt) {
                $q->whereBetween('start_at', [$startAt, $endAt])
                    ->orWhereBetween('end_at', [$startAt, $endAt])
                    ->orWhere(function ($qq) use ($startAt, $endAt) {
                        $qq->where('start_at', '<=', $startAt)
                            ->where('end_at', '>=', $endAt);
                    });
            })
            ->exists();

        if ($overlapMoto) {
            return back()->withErrors([
                'motorcycle_id' => 'La moto seleccionada ya está asignada en ese rango.'
            ]);
        }

        DB::transaction(function () use ($tr, $data, $startAt, $endAt, $assignmentYear) {

            // 2) Crear asignación temporal (OJO: assignment_year obligatorio)
            DB::table('motorcycle_assignments')->insert([
                'assignment_year'         => $assignmentYear,              // ✅ CLAVE
                'motorcycle_id'           => (int)$data['motorcycle_id'],
                'person_id'               => (int)($tr->person_id ?? 0),
                'area_id'                 => (int)($tr->area_id ?? 0),
                'budget_item_id'          => (int)($tr->budget_item_id ?? 0) ?: null,
                'travel_requestable_type' => TravelRequest::class,
                'travel_requestable_id'   => (int)$tr->id,
                'start_at'                => $startAt,
                'end_at'                  => $endAt,
                'status'                  => 'approved',
                'requested_by'            => Auth::id(),
                'approved_by'             => Auth::id(),
                'managed_by'              => Auth::id(),
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            // 3) Bloquear segmentos dentro del rango: transporte 0 + modo moto
            $this->lockSegmentsToMotoRange((int)$tr->id, $startAt, $endAt);

            // 4) Cambiar estado moto
            DB::table('motorcycles')->where('id', (int)$data['motorcycle_id'])->update([
                'status'     => 'assigned',
                'updated_at' => now(),
            ]);
        });

        // 5) Recalcular totales del TR
        $this->recalcRequestTotals((int)$tr->id);

        return redirect()
            ->route($routePrefix . '.requests.show', ['travelRequest' => $tr->id])
            ->with('success', 'Moto asignada: segmentos en rango → MOTO y transporte $0.');
    }
    protected function recalcRequestTotals(int $travelRequestId): void
    {
        $seg = TravelSegment::query()
            ->where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->get(['transport_cost', 'per_diem_cost', 'other_cost']);

        $transportSeg = (float)$seg->sum('transport_cost');
        $perDiemSeg   = (float)$seg->sum('per_diem_cost');
        $otherSeg     = (float)$seg->sum('other_cost');

        $allowTotal = (float) TravelAllowance::query()
            ->where('travel_request_id', $travelRequestId)
            ->whereIn('status', ['draft', 'liquidated', 'approved'])
            ->sum('calculated_amount');

        $finalPerDiem = $perDiemSeg + $allowTotal;

        TravelRequest::query()
            ->where('id', $travelRequestId)
            ->update([
                'total_transport' => $transportSeg,
                'total_per_diem'  => $finalPerDiem,
                'total_other'     => $otherSeg,
                'total_amount'    => ($transportSeg + $finalPerDiem + $otherSeg),
                'updated_at'      => now(),
            ]);
    }
}
