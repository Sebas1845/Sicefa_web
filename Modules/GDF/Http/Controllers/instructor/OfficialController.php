<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelAllowance;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Throwable;
use Modules\GDF\Entities\TravelSegment;
use Modules\GDF\Entities\TravelCost;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OfficialController extends BaseOfficialController
{
    public function dashboard(Request $request)
    {
        // ✅ CONTEXTO
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string) ($ctx['area'] ?? 'academic');
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!($gate['can_enter'] ?? true)) {
            return redirect()->route('gdf.index')
                ->with('warning', $gate['message'] ?? 'Acceso restringido');
        }

        [$col, $val] = $this->ownerFilter();
        $areaIds = $this->areaIdsByKey($areaKey);

        // 🔐 Base segura
        $base = \Modules\GDF\Entities\TravelRequest::query()
            ->where($col, $val)
            ->whereIn('area_id', $areaIds)
            ->whereIn('module', ['gdf', 'sitrav']);

        // ✅ Stats
        $stats = [
            'draft'             => (clone $base)->where('status', 'draft')->count(),
            'submitted'         => (clone $base)->where('status', 'submitted')->count(),
            'returned'          => (clone $base)->where('status', 'returned')->count(),
            'rejected'          => (clone $base)->where('status', 'rejected')->count(),
            'approved'          => (clone $base)->where('status', 'approved')->count(),
            'pending_treasury'  => (clone $base)->where('status', 'pending_treasury')->count(),
            'approved_treasury' => (clone $base)->where('status', 'approved_by_treasury')->count(),
            'executed'          => (clone $base)->where('status', 'executed')->count(),
            'confirmed'         => (clone $base)->where('status', 'confirmed')->count(),
            'cancelled'         => (clone $base)->where('status', 'cancelled')->count(),
        ];

        $stats['in_process'] =
            ($stats['submitted'] ?? 0) +
            ($stats['pending_treasury'] ?? 0) +
            ($stats['approved_treasury'] ?? 0);

        // ✅ Helper: url pública si existe en disk public
        $publicUrlIfExists = function (string $diskPath): ?string {
            try {
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($diskPath)) {
                    return \Illuminate\Support\Facades\Storage::disk('public')->url($diskPath);
                }

                $fullPath = storage_path("app/public/{$diskPath}");
                if (file_exists($fullPath)) {
                    return asset("storage/{$diskPath}");
                }
            } catch (\Throwable $e) {
                \Log::warning("publicUrlIfExists error", [
                    'path' => $diskPath,
                    'error' => $e->getMessage(),
                ]);
            }

            return null;
        };

        // ✅ Recientes + Documento
        $recent = (clone $base)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($tr) use ($publicUrlIfExists) {

                $tr->status_label = \Modules\GDF\Status\TravelStatus::label($tr->status ?? null);
                $tr->status_badge = \Modules\GDF\Status\TravelStatus::badge($tr->status ?? null);

                // Documento genérico
                $tr->doc_url   = null;
                $tr->doc_label = null;
                $tr->doc_badge = null;

                // ✅ PRIORIDAD TOTAL: si existe autorización GDF para este ID, se muestra SIEMPRE
                $authDiskPath = "gdf/requests/{$tr->id}/autorizacion_{$tr->id}.pdf";
                $authUrl = $publicUrlIfExists($authDiskPath);

                if ($authUrl) {
                    $tr->doc_url   = $authUrl;
                    $tr->doc_label = 'AUTORIZACIÓN';
                    $tr->doc_badge = 'success';
                    return $tr;
                }

                // ✅ Solo si NO hay autorización, SITRAV cae a docs SIGAC
                if ((string)($tr->module ?? '') === 'sitrav') {
                    $prId = (int) ($tr->source_request_id ?? 0);

                    if ($prId > 0) {
                        $row = \Illuminate\Support\Facades\DB::table('program_request_documents')
                            ->where('program_request_id', $prId)
                            ->orderByDesc('id')
                            ->first();

                        if ($row) {
                            $path = (string) ($row->path ?? $row->file_path ?? $row->url ?? '');
                            $path = str_replace('\\', '/', $path);
                            $path = preg_replace('#^.*storage/app/public/#', '', $path);

                            if ($path !== '') {
                                $url = $publicUrlIfExists($path);
                                if ($url) {
                                    $tr->doc_url   = $url;
                                    $tr->doc_label = 'DOCS SIGAC';
                                    $tr->doc_badge = 'info';
                                }
                            }
                        }
                    }
                }

                return $tr;
            });

        // SITRAV habilitado
        $sitravEnabled = \Illuminate\Support\Facades\Route::has('gdf.instructor.sitrav.programs.index');

        // ✅ MOTO: traer datos REALES desde motorcycles (plate/model/current_odometer)
        $personId = (int) (auth()->user()->person_id ?? 0);

        $activeMoto = null;
        $hasMoto = false;

        if ($personId > 0) {
            $q = \Illuminate\Support\Facades\DB::table('motorcycle_assignments as ma')
                ->leftJoin('motorcycles as m', 'm.id', '=', 'ma.motorcycle_id')
                ->where('ma.person_id', $personId)
                ->whereNull('ma.returned_at')
                ->where(function ($w) {
                    $w->whereNull('ma.status')
                        ->orWhereIn('ma.status', ['approved', 'delivered', 'active', 'assigned', 'entregado']);
                });


            if (!empty($areaIds)) {
                $q->whereIn('ma.area_id', array_map('intval', $areaIds));
            }

            $activeMoto = $q->orderByDesc('ma.id')->select([
                'ma.*',
                'm.plate as plate',
                'm.brand as brand',
                'm.model as model',
                'm.current_odometer as odometer',
                'm.status as motorcycle_status',
            ])->first();

            $hasMoto = (bool) $activeMoto;
        }

        return view('gdf::official.dashboard', compact(
            'ctx',
            'areaKey',
            'areaIds',
            'stats',
            'recent',
            'gate',
            'sitravEnabled',
            'hasMoto',
            'activeMoto'
        ));
    }


    public function index(Request $request)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string) $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }

        [$col, $val] = $this->ownerFilter();
        $areaIds = $this->areaIdsByKey($areaKey);

        // filtros
        $q   = trim((string) $request->get('q', ''));
        $tab = (string) $request->get('tab', 'all'); // all|gdf|sitrav

        // query base (seguridad)
        $query = TravelRequest::query()
            ->where($col, $val)
            ->whereIn('area_id', $areaIds);

        // filtro por módulo
        if ($tab === 'gdf') {
            $query->where('module', 'gdf');
        } elseif ($tab === 'sitrav') {
            $query->where('module', 'sitrav');
        } else {
            $query->whereIn('module', ['gdf', 'sitrav']);
        }

        // búsqueda
        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('origin', 'like', "%{$q}%")
                    ->orWhere('destination', 'like', "%{$q}%");

                if (ctype_digit($q)) {
                    $sub->orWhere('id', (int) $q);
                }
            });
        }

        // listado
        $requests = $query
            ->orderByDesc('created_at')
            ->paginate(15)
            ->appends([
                'q' => $q,
                'tab' => $tab,
            ]);

        return view('gdf::official.requests.index', compact(
            'ctx',
            'areaKey',
            'areaIds',
            'requests',
            'q',
            'gate',
            'tab'
        ));
    }

    public function create(Request $request)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string) $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }
        if (!$gate['can_create']) {
            return redirect()->route('gdf.instructor.dashboard')->with('warning', $gate['message']);
        }

        $areaIds = $this->areaIdsByKey($areaKey);
        if (empty($areaIds)) {
            return redirect()->route('gdf.instructor.dashboard')
                ->with('error', 'No hay áreas configuradas para este contexto.');
        }

        $defaultAreaId = (int) ($areaIds[0] ?? 0);
        if ($defaultAreaId <= 0) {
            return redirect()->route('gdf.instructor.dashboard')
                ->with('error', 'Área por defecto inválida.');
        }

        $personId = (int) (auth()->user()->person_id ?? 0);
        if ($personId <= 0) {
            return redirect()->route('gdf.instructor.dashboard')
                ->with('error', 'Tu usuario no tiene person_id asociado.');
        }

        $this->ensureNoMultipleOpenMotoAssignments($personId, $areaIds);

        $budgetItems = $this->allowedBudgetItemsForPersonInAreaIds($personId, $areaIds);

        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);

        $motoLock = [
            'has_moto' => (bool) $activeMoto,
            'message'  => $activeMoto
                ? 'Tienes una moto asignada. En Huila se puede forzar moto; fuera de Huila no aplica.'
                : null,
            'assignment' => $activeMoto, // por si la vista quiere mostrar placa/id
        ];

        $budgetRule = [
            'required' => true,
            'message'  => null,
        ];

        $countryId = (int) config('gdf.country_id', 25);

        $departments = DB::table('departments')
            ->select('id', 'name')
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->get();

        $HUILA_ID = 421;

        $defaultDepartmentId = (int) (
            optional($departments->firstWhere('id', $HUILA_ID))->id
            ?? optional($departments->first())->id
            ?? 0
        );

        $defaultOrigin = [
            'label' => 'Centro de Formación (por defecto)',
            'lat'   => 2.615508,
            'lng'   => -75.359011,
        ];

        $origins = [
            ['key' => 'center_default', 'label' => 'Centro de Formación', 'lat' => 2.615508, 'lng' => -75.359011],
            ['key' => 'terminal_neiva', 'label' => 'Terminal Neiva', 'lat' => 2.91681, 'lng' => -75.28191],
        ];

        $hasMoto = (bool) ($motoLock['has_moto'] ?? false);

        $isEmployee   = $this->isEmployeePlant($personId);
        $isContractor = $this->isContractorActive($personId);
        $isPlant      = $isEmployee && !$isContractor;



        $vanAllowed = false;

        return view('gdf::official.requests.create', compact(
            'ctx',
            'areaKey',
            'areaIds',
            'defaultAreaId',
            'gate',
            'budgetItems',
            'motoLock',
            'budgetRule',
            'countryId',
            'departments',
            'defaultDepartmentId',
            'origins',
            'defaultOrigin',
            'hasMoto',
            'isPlant',
            'HUILA_ID',
            'vanAllowed'
        ));
    }
    protected function isEmployeePlant(int $personId): bool
    {
        return DB::table('employees')
            ->where('person_id', $personId)
            ->whereNull('deleted_at')
            ->where('state', 'Activo')
            ->exists();
    }

    protected function isContractorActive(int $personId): bool
    {
        $today = now()->toDateString();

        return DB::table('contractors')
            ->where('person_id', $personId)
            ->whereNull('deleted_at')
            ->where('state', 'Activo')
            ->whereDate('contract_start_date', '<=', $today)
            ->whereDate('contract_end_date', '>=', $today)
            ->exists();
    }


    public function store(Request $request)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string) $ctx['area'];
        $gate = $this->creationGateForCtxArea($areaKey);

        if (!$gate['can_enter']) {
            return redirect()->route('gdf.index')->with('warning', $gate['message']);
        }
        if (!$gate['can_create']) {
            return back()->with('warning', $gate['message'])->withInput();
        }

        $areaIds  = $this->areaIdsByKey($areaKey);
        $personId = (int) (auth()->user()->person_id ?? 0);

        if (empty($areaIds) || $personId <= 0) {
            return back()->with('error', 'Contexto inválido (área/persona).')->withInput();
        }

        // Moto activa
        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
        $hasMoto    = (bool) $activeMoto;

        $HUILA_ID = 421;

        // ========= VALIDACIÓN =========
        $data = $request->validate([
            'area_id'         => ['required', 'integer', 'in:' . implode(',', $areaIds)],
            'budget_item_id'  => ['required', 'integer'],
            'origin'          => ['required', 'string', 'max:150'],

            'department_id'   => ['required', 'integer'],
            'place_type'      => ['required', 'in:municipio,vereda'],
            'municipality_id' => ['required', 'integer'],
            'village_id'      => ['nullable', 'integer', 'required_if:place_type,vereda'],

            'origin_lat'      => ['nullable', 'numeric'],
            'origin_lng'      => ['nullable', 'numeric'],
            'destination_lat' => ['nullable', 'numeric'],
            'destination_lng' => ['nullable', 'numeric'],

            'start_date'      => ['required', 'date'],
            'end_date'        => ['required', 'date', 'after_or_equal:start_date'],

            'objeto'          => ['required', 'string', 'max:5000'],
            'notes'           => ['nullable', 'string', 'max:5000'],

            'transport_mode'  => ['required', 'in:bus,van,motorcycle,air'],
            'trip_type'       => ['required', 'in:oneway,return,roundtrip'],
            'transport_value' => ['nullable', 'numeric', 'min:0'],

            'estimated_value' => ['nullable', 'numeric', 'min:0'],

            // viáticos (payload)
            'allowances' => ['nullable', 'array'],

            // planta
            'allowances.lodging.include'      => ['nullable', 'boolean'],
            'allowances.lodging.unit_amount'  => ['nullable', 'numeric', 'min:0'],
            'allowances.meals.include'        => ['nullable', 'boolean'],
            'allowances.meals.unit_amount'    => ['nullable', 'numeric', 'min:0'],
            'allowances.per_diem.include'     => ['nullable', 'boolean'],
            'allowances.per_diem.unit_amount' => ['nullable', 'numeric', 'min:0'],

            // moto (fuel)
            'allowances.fuel.include'         => ['nullable', 'boolean'],
            'allowances.fuel.unit_amount'     => ['nullable', 'numeric', 'min:0'],

            // documentos
            'documents'   => ['nullable', 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // ========= Reglas duras de negocio =========
        $deptId  = (int) ($data['department_id'] ?? 0);
        $inHuila = ($deptId === $HUILA_ID);

        if (!$inHuila && ($data['place_type'] ?? '') === 'vereda') {
            return back()->with('error', 'Fuera de Huila el destino debe ser Municipio (Vereda no aplica).')->withInput();
        }

        if (($data['transport_mode'] ?? '') === 'van') {
            return back()->with('error', 'Camioneta solo la asigna Apoyo.')->withInput();
        }

        if (($data['transport_mode'] ?? '') === 'motorcycle' && !$hasMoto) {
            return back()->with('error', 'No tienes una moto asignada.')->withInput();
        }
        if (!$inHuila && ($data['transport_mode'] ?? '') === 'motorcycle') {
            return back()->with('error', 'Fuera de Huila no se permite transporte en moto.')->withInput();
        }

        if ($hasMoto && $inHuila) {
            $data['transport_mode'] = 'motorcycle';
        }

        $isMoto = (($data['transport_mode'] ?? '') === 'motorcycle');

        $isEmployee   = $this->isEmployeePlant($personId);
        $isContractor = $this->isContractorActive($personId);
        $isPlant      = $isEmployee && !$isContractor;

        // ========= Enforce regla viáticos =========
        $allow = $data['allowances'] ?? [];

        if (!$isPlant) {
            foreach (['lodging', 'meals', 'per_diem'] as $k) {
                if (!empty($allow[$k]['include'])) {
                    return back()->with('error', 'Contratista: no puede registrar Hospedaje/Alimentación/Otros.')->withInput();
                }
            }
            if (!empty($allow['fuel']['include']) && !$isMoto) {
                return back()->with('error', 'Contratista: gasolina solo aplica si el transporte quedó en Moto.')->withInput();
            }
        }

        // ========= Nombres destino =========
        $munName = DB::table('municipalities')->where('id', (int)$data['municipality_id'])->value('name');

        $vilName = null;
        if (($data['place_type'] ?? 'municipio') === 'vereda') {
            $vilName = DB::table('villages')->where('id', (int)$data['village_id'])->value('name');
        }

        $prettyDestination = (($data['place_type'] ?? 'municipio') === 'vereda')
            ? ($vilName ?: ('Vereda #' . (int)$data['village_id']))
            : ($munName ?: ('Municipio #' . (int)$data['municipality_id']));


        $rate = null;
        $rateSource = null;

        // Bloquear "air" en vereda
        if (($data['place_type'] ?? '') === 'vereda' && ($data['transport_mode'] ?? '') === 'air') {
            return back()->with('error', 'Para Vereda solo aplica transporte terrestre (no aéreo).')->withInput();
        }

        if (!$isMoto) {
            if (($data['place_type'] ?? 'municipio') === 'vereda') {
                $rateSource = 'village_rates';
                if ($this->tableExists('village_rates')) {
                    $rate = DB::table('village_rates')
                        ->where('village_id', (int)$data['village_id'])
                        ->where('active', 1)
                        ->orderByDesc('id')
                        ->first();
                }
            } else {
                $rateSource = 'municipality_rates';
                if ($this->tableExists('municipality_rates')) {
                    $rate = DB::table('municipality_rates')
                        ->where('municipality_id', (int)$data['municipality_id'])
                        ->where('active', 1)
                        ->orderByDesc('id')
                        ->first();
                }
            }
        }

        // ========= Cálculo transporte =========
        $baseFromRate = 0.0;

        // Nota: aquí solo necesitamos bus y air según tu regla (van ya está bloqueado arriba).
        if (!$isMoto && $rate) {
            $baseFromRate = match ($data['transport_mode']) {
                'bus' => (float)($rate->bus_amount ?? 0),
                'air' => (float)($rate->air_amount ?? 0), // municipio
                default => 0.0,
            };
        }

        $typedBase = (float) ($data['transport_value'] ?? 0);
        $tripType  = (string) ($data['trip_type'] ?? 'oneway');
        $mult      = ($tripType === 'roundtrip') ? 2 : 1;

        if (!$isMoto && $typedBase > 0) {
            $now = now();

            if (($data['place_type'] ?? 'municipio') === 'vereda') {
                // Vereda: solo terrestre => guardamos en bus_amount
                if ($this->tableExists('village_rates')) {

                    // desactivar anterior
                    DB::table('village_rates')
                        ->where('village_id', (int)$data['village_id'])
                        ->where('active', 1)
                        ->update(['active' => 0, 'updated_at' => $now]);

                    $row = DB::table('villages as v')
                        ->leftJoin('municipalities as m', 'm.id', '=', 'v.municipality_id')
                        ->select('v.id', 'v.name as village_name', 'v.municipality_id', 'm.name as municipality_name')
                        ->where('v.id', (int)$data['village_id'])
                        ->first();

                    if (!$row) {
                        return back()->with('error', 'Vereda no encontrada para guardar tarifa.')->withInput();
                    }

                    $newId = DB::table('village_rates')->insertGetId([
                        'municipality_id'   => (int) $row->municipality_id,
                        'village_id'        => (int) $row->id,
                        'village_name'      => (string) $row->village_name,
                        'municipality_name' => (string) ($row->municipality_name ?? ''),

                        'bus_amount'        => $typedBase,  // ✅ terrestre
                        // NO guardamos air en vereda
                        // NO guardamos moto
                        // NO guardamos van

                        'active'     => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $rate = (object)[
                        'id' => $newId,
                        'bus_amount' => $typedBase,
                        'air_amount' => 0,
                    ];
                    $rateSource = 'village_rates';
                }
            } else {
                // Municipio: puede ser terrestre (bus) o aéreo (air)
                if ($this->tableExists('municipality_rates')) {

                    // desactivar anterior
                    DB::table('municipality_rates')
                        ->where('municipality_id', (int)$data['municipality_id'])
                        ->where('active', 1)
                        ->update(['active' => 0, 'updated_at' => $now]);

                    $munNameForRate = DB::table('municipalities')->where('id', (int)$data['municipality_id'])->value('name');
                    if (!$munNameForRate) {
                        return back()->with('error', 'Municipio no encontrado para guardar tarifa.')->withInput();
                    }

                    $busAmount = 0.0;
                    $airAmount = 0.0;

                    if (($data['transport_mode'] ?? '') === 'bus') {
                        $busAmount = $typedBase;
                    } elseif (($data['transport_mode'] ?? '') === 'air') {
                        $airAmount = $typedBase;
                    } else {
                        // aquí no debería entrar (van/moto ya bloqueados)
                    }

                    $newId = DB::table('municipality_rates')->insertGetId([
                        'municipality_id'   => (int) $data['municipality_id'],
                        'municipality_name' => (string) $munNameForRate,

                        'bus_amount'        => $busAmount, // ✅ terrestre
                        'air_amount'        => $airAmount, // ✅ aéreo
                        // NO guardamos moto
                        // NO guardamos van

                        'active'     => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $rate = (object)[
                        'id' => $newId,
                        'bus_amount' => $busAmount,
                        'air_amount' => $airAmount,
                    ];
                    $rateSource = 'municipality_rates';
                }
            }
        }

        // Recalcular baseFromRate si acabamos de crear rate y no había base
        if (!$isMoto && $rate && $typedBase <= 0) {
            $baseFromRate = match ($data['transport_mode']) {
                'bus' => (float)($rate->bus_amount ?? 0),
                'air' => (float)($rate->air_amount ?? 0),
                default => 0.0,
            };
        }

        if ($isMoto) {
            $baseTransport  = 0.0;
            $totalTransport = 0.0;
        } else {
            $baseTransport = ($typedBase > 0) ? $typedBase : $baseFromRate;

            if ($baseTransport <= 0) {
                return back()->with('error', 'No hay tarifa base y tampoco digitaste un valor válido de transporte.')->withInput();
            }

            $totalTransport = max(0, round($baseTransport * $mult, 2));
        }

        // ========= Fechas =========
        $s = Carbon::parse($data['start_date']);
        $e = Carbon::parse($data['end_date']);
        $days   = $s->diffInDays($e) + 1;
        $nights = max(1, $days - 1);

        // ========= Viáticos =========
        $totalPerDiem = 0.00;
        $totalOther   = 0.00;

        if ($isPlant) {
            if (!empty($allow['lodging']['include'])) {
                $unit = (float)($allow['lodging']['unit_amount'] ?? 0);
                if ($unit <= 0) return back()->with('error', 'Valor unitario de hospedaje requerido.')->withInput();
                $totalPerDiem += round($unit * $nights, 2);
            }
            if (!empty($allow['meals']['include'])) {
                $unit = (float)($allow['meals']['unit_amount'] ?? 0);
                if ($unit <= 0) return back()->with('error', 'Valor unitario de alimentación requerido.')->withInput();
                $totalPerDiem += round($unit * max(1, $days * 3), 2);
            }
            if (!empty($allow['per_diem']['include'])) {
                $unit = (float)($allow['per_diem']['unit_amount'] ?? 0);
                if ($unit <= 0) return back()->with('error', 'Valor unitario de otros requerido.')->withInput();
                $totalPerDiem += round($unit * max(1, $days), 2);
            }
        }

        if ($isMoto && !empty($allow['fuel']['include'])) {
            $unit = (float)($allow['fuel']['unit_amount'] ?? 0);
            if ($unit <= 0) return back()->with('error', 'Valor unitario de gasolina requerido.')->withInput();
            $totalPerDiem += round($unit * max(1, $days), 2);
        }

        $grandTotal = round($totalTransport + $totalPerDiem + $totalOther, 2);

        // ========= Guardado =========
        [$col, $val] = $this->ownerFilter();

        DB::beginTransaction();
        try {
            // 1) TravelRequest
            $req = new TravelRequest();
            $req->module = 'gdf';
            $req->source = 'manual';
            $req->{$col} = $val;

            $req->area_id        = (int) $data['area_id'];
            $req->budget_item_id = (int) $data['budget_item_id'];
            $req->person_id      = $personId;
            $req->person_type    = $isPlant ? 'staff' : 'contractor';

            $req->origin      = (string) $data['origin'];
            $req->destination = $prettyDestination;

            $req->start_date = $data['start_date'];
            $req->end_date   = $data['end_date'];

            $notes = trim((string)($data['notes'] ?? ''));
            $obj   = trim((string)($data['objeto'] ?? ''));
            $req->notes = $notes ? ("OBJETO:\n{$obj}\n\nNOTAS:\n{$notes}") : ("OBJETO:\n{$obj}");

            $req->total_transport = $totalTransport;
            $req->total_per_diem  = $totalPerDiem;
            $req->total_other     = $totalOther;
            $req->total_amount    = $grandTotal;

            if (Schema::hasColumn($req->getTable(), 'estimated_value')) {
                $req->estimated_value = $isMoto ? (float)$totalPerDiem : (float)($data['estimated_value'] ?? $totalTransport);
            }

            $req->status     = 'submitted';
            $req->created_by = auth()->id();
            $req->save();

            // 2) Segment
            $seg = new TravelSegment();
            $seg->travel_request_id = (int) $req->id;

            $seg->departure_at = $s->copy()->startOfDay();
            $seg->return_at    = $e->copy()->endOfDay();

            $seg->origin_place      = (string) $data['origin'];
            $seg->destination_place = $prettyDestination;

            $seg->origin_display_name      = (string) $data['origin'];
            $seg->destination_display_name = $prettyDestination;

            $seg->origin_lat = $data['origin_lat'] ?? null;
            $seg->origin_lng = $data['origin_lng'] ?? null;

            $seg->destination_lat = $data['destination_lat'] ?? null;
            $seg->destination_lng = $data['destination_lng'] ?? null;

            $seg->destination_type = (($data['place_type'] ?? 'municipio') === 'vereda') ? 'village' : 'municipality';
            $seg->department_id    = (int) $data['department_id'];
            $seg->municipality_id  = (int) $data['municipality_id'];
            $seg->village_id       = (($data['place_type'] ?? 'municipio') === 'vereda') ? (int) $data['village_id'] : null;

            // ✅ guardar rate_id si existe (moto no guarda rate)
            if (!$isMoto && $rate) {
                if (($data['place_type'] ?? 'municipio') === 'vereda') {
                    if (Schema::hasColumn($seg->getTable(), 'village_rate_id')) {
                        $seg->village_rate_id = (int) $rate->id;
                    }
                } else {
                    if (Schema::hasColumn($seg->getTable(), 'municipality_rate_id')) {
                        $seg->municipality_rate_id = (int) $rate->id;
                    }
                }
            }

            // ✅ (opcional) guardar notes en segment si la columna existe
            if (Schema::hasColumn($seg->getTable(), 'notes')) {
                $seg->notes = json_encode([
                    'source' => 'manual',
                    'place_type' => $data['place_type'],
                    'transport_mode' => $data['transport_mode'],
                    'typed_transport_value' => $typedBase,
                    'rate_source' => $rateSource,
                    'rate_id' => $rate->id ?? null,
                ], JSON_UNESCAPED_UNICODE);
            }

            $seg->transport_type = match ($data['transport_mode']) {
                'air'        => 'aereo',
                'motorcycle' => 'moto',
                default      => 'terrestre',
            };

            $seg->trip_type = ($mult === 2) ? 'round_trip' : 'one_way';
            $seg->trips     = ($mult === 2) ? 2 : 1;

            $seg->transport_cost = $totalTransport;
            $seg->per_diem_cost  = $totalPerDiem;
            $seg->other_cost     = $totalOther;
            $seg->total_cost     = $grandTotal;
            $seg->save();

            // 3) Cost
            if (!$isMoto && $totalTransport > 0) {
                $meta = [
                    'transport' => $data['transport_mode'],
                    'trip_type' => $data['trip_type'],
                    'base'      => $baseTransport,
                    'mult'      => $mult,
                    'source'    => $typedBase > 0 ? 'manual' : ($rateSource ?: null),
                    'rate_id'   => $rate->id ?? null,
                ];
                $desc = json_encode($meta, JSON_UNESCAPED_UNICODE);
                if (is_string($desc) && strlen($desc) > 240) $desc = substr($desc, 0, 240);

                TravelCost::create([
                    'travel_request_id' => (int) $req->id,
                    'cost_type'   => 'transport',
                    'description' => $desc,
                    'amount'      => $totalTransport,
                    'applies_to'  => ($mult === 2) ? 'both' : 'one_way',
                ]);
            }

            // 4) Allowances
            if ($this->tableExists('travel_allowances')) {
                $creatorId = (int) auth()->id();

                if ($isPlant) {
                    if (!empty($allow['lodging']['include'])) {
                        $unit = (float)($allow['lodging']['unit_amount'] ?? 0);
                        TravelAllowance::create([
                            'travel_request_id' => (int) $req->id,
                            'allowance_type'    => 'lodging',
                            'unit_amount'       => $unit,
                            'units'             => (int) $nights,
                            'calculated_amount' => round($unit * $nights, 2),
                            'description'       => 'Hospedaje',
                            'status'            => 'draft',
                            'created_by'        => $creatorId,
                            'budget_item_id'    => (int) $data['budget_item_id'],
                            'applies_to'        => $isPlant ? 'staff' : 'contractor',
                        ]);
                    }

                    if (!empty($allow['meals']['include'])) {
                        $units = max(1, $days * 3);
                        $unit  = (float)($allow['meals']['unit_amount'] ?? 0);
                        TravelAllowance::create([
                            'travel_request_id' => (int) $req->id,
                            'allowance_type'    => 'meals',
                            'unit_amount'       => $unit,
                            'units'             => (int) $units,
                            'calculated_amount' => round($unit * $units, 2),
                            'description'       => 'Alimentación',
                            'status'            => 'draft',
                            'created_by'        => $creatorId,
                            'budget_item_id'    => (int) $data['budget_item_id'],
                            'applies_to'        => $isPlant ? 'staff' : 'contractor',
                        ]);
                    }

                    if (!empty($allow['per_diem']['include'])) {
                        $units = max(1, $days);
                        $unit  = (float)($allow['per_diem']['unit_amount'] ?? 0);
                        TravelAllowance::create([
                            'travel_request_id' => (int) $req->id,
                            'allowance_type'    => 'per_diem',
                            'unit_amount'       => $unit,
                            'units'             => (int) $units,
                            'calculated_amount' => round($unit * $units, 2),
                            'description'       => 'Otros',
                            'status'            => 'draft',
                            'created_by'        => $creatorId,
                            'budget_item_id'    => (int) $data['budget_item_id'],
                            'applies_to'        => $isPlant ? 'staff' : 'contractor',
                        ]);
                    }
                }

                if ($isMoto && !empty($allow['fuel']['include'])) {
                    $units = max(1, $days);
                    $unit  = (float)($allow['fuel']['unit_amount'] ?? 0);

                    TravelAllowance::create([
                        'travel_request_id' => (int) $req->id,
                        'allowance_type'    => 'fuel',
                        'unit_amount'       => $unit,
                        'units'             => (int) $units,
                        'calculated_amount' => round($unit * $units, 2),
                        'description'       => 'Gasolina (moto)',
                        'status'            => 'draft',
                        'created_by'        => $creatorId,
                        'budget_item_id'    => (int) $data['budget_item_id'],
                        'applies_to'        => $isPlant ? 'staff' : 'contractor',
                    ]);
                }
            }

            // 5) Documentos
            if ($request->hasFile('documents') && $this->tableExists('travel_request_documents')) {
                foreach ($request->file('documents', []) as $file) {
                    if (!$file || !$file->isValid()) continue;

                    $disk = 'public';
                    $dir  = "gdf/requests/{$req->id}/documents";
                    $storedName = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

                    $path = $file->storeAs($dir, $storedName, $disk);

                    DB::table('travel_request_documents')->insert([
                        'travel_request_id' => (int) $req->id,
                        'document_type'     => 'proof',
                        'title'             => null,
                        'original_name'     => $file->getClientOriginalName(),
                        'stored_name'       => $storedName,
                        'path'              => $path,
                        'disk'              => $disk,
                        'mime_type'         => $file->getClientMimeType(),
                        'size_bytes'        => (int) $file->getSize(),
                        'status'            => 'submitted',
                        'notes'             => null,
                        'uploaded_by'       => (int) (auth()->user()->person_id ?? null),
                        'is_required'       => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);
                }
            }

            DB::commit();

            return redirect()
                ->route('gdf.instructor.requests.show', $req->id)
                ->with('success', 'Solicitud enviada.');
        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            Log::error('GDF store QueryException', [
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'msg' => $e->getMessage(),
            ]);
            return back()->withInput()->with('error', app()->environment('local') ? ('DB: ' . $e->getMessage()) : 'No se pudo guardar la solicitud.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('GDF store Throwable', [
                'msg' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withInput()->with('error', app()->environment('local') ? ('ERR: ' . $e->getMessage()) : 'No se pudo guardar la solicitud.');
        }
    }


    public function show(\Modules\GDF\Entities\TravelRequest $gdfRequest)
    {
        if ($r = $this->requireOfficialContext()) return $r;

        $ctx     = $this->ctx();
        $areaKey = (string) ($ctx['area'] ?? 'academic');
        $areaIds = $this->areaIdsByKey($areaKey);

        [$col, $val] = $this->ownerFilter();

        if (
            (string) $gdfRequest->module !== 'gdf' ||
            (string) $gdfRequest->{$col} !== (string) $val ||
            !in_array((int) $gdfRequest->area_id, $areaIds, true)
        ) {
            abort(403);
        }

        $personId   = (int) (auth()->user()->person_id ?? 0);
        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
        $hasMoto    = (bool) $activeMoto;

        $gate = $this->creationGateForCtxArea($areaKey);

        $gdfRequest->load([
            'person',
            'area',
            'budgetItem',
            'segments'   => fn($q) => $q->orderBy('id'),
            'costs'      => fn($q) => $q->orderBy('id'),
            'allowances' => fn($q) => $q->orderBy('id'),
            'documents'  => fn($q) => $q->orderBy('id'),
        ]);

        $allowancesByType = $gdfRequest->allowances
            ? $gdfRequest->allowances->groupBy('allowance_type')
            : collect();

        $selectedAllowanceTypes = $allowancesByType->keys()->values()->all();

        $transportCost = $gdfRequest->costs?->firstWhere('cost_type', 'transport');
        $transportMeta = [];
        if ($transportCost && is_string($transportCost->description)) {
            $tmp = json_decode($transportCost->description, true);
            if (is_array($tmp)) $transportMeta = $tmp;
        }


        $authUrl = null;

        $authVisibleStatuses = ['confirmed']; // agrega otros si quieres: ['confirmed','executed']

        $isAuthVisible = in_array((string) ($gdfRequest->status ?? ''), $authVisibleStatuses, true);

        if ($isAuthVisible) {
            $authDiskPath = "gdf/requests/{$gdfRequest->id}/autorizacion_{$gdfRequest->id}.pdf";

            try {
                if (Storage::disk('public')->exists($authDiskPath)) {
                    // ✅ asset() usa el host real con el que entras (127.0.0.1:8000, sicefa_web.test, etc.)
                    $authUrl = asset("storage/{$authDiskPath}");
                }
            } catch (\Throwable $e) {
                Log::warning("Error buscando autorización", [
                    'request_id' => $gdfRequest->id,
                    'path'       => $authDiskPath,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return view('gdf::official.requests.show', [
            'ctx'                   => $ctx,
            'areaKey'               => $areaKey,
            'areaIds'               => $areaIds,
            'gdfRequest'            => $gdfRequest,
            'gate'                  => $gate,
            'hasMoto'               => $hasMoto,
            'activeMoto'            => $activeMoto,
            'allowancesByType'      => $allowancesByType,
            'selectedAllowanceTypes' => $selectedAllowanceTypes,
            'transportMeta'         => $transportMeta,

            // ✅ Autorización
            'authUrl'               => $authUrl,
            'isAuthVisible'         => $isAuthVisible,
            'authVisibleStatuses'   => $authVisibleStatuses,
        ]);
    }
    protected function activeMotorcycleAssignmentForPerson(int $personId, array $areaIds = []): ?object
    {
        // ✅ Estados que consideramos "activos"
        $activeStatuses = ['pending', 'delivered', 'approved'];

        $q = DB::table('motorcycle_assignments as ma')
            ->where('ma.person_id', $personId)
            ->whereNull('ma.returned_at')
            ->whereIn('ma.status', $activeStatuses)
            ->where(function ($w) {
                $w->whereNull('ma.end_at')
                    ->orWhere('ma.end_at', '>=', now());
            });

        if (!empty($areaIds)) {
            $q->whereIn('ma.area_id', array_map('intval', $areaIds));
        }

        return $q->orderByDesc('ma.id')->first();
    }



    public function cancel(TravelRequest $gdfRequest)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string) $ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);

        [$col, $val] = $this->ownerFilter();

        if (
            (string) $gdfRequest->module !== 'gdf' ||
            (string) $gdfRequest->{$col} !== (string) $val ||
            !in_array((int) $gdfRequest->area_id, $areaIds, true)
        ) {
            abort(403);
        }

        if (!in_array((string) $gdfRequest->status, ['draft', 'submitted'], true)) {
            return back()->with('error', 'No se puede cancelar en este estado.');
        }

        $gdfRequest->status = 'cancelled';
        $gdfRequest->save();

        return redirect()->route('gdf.instructor.requests.index')->with('success', 'Solicitud cancelada.');
    }

    private function recalcRequestTotals(int $travelRequestId): void
    {
        // Transporte: suma segmentos NO cancelados
        $transport = (float) TravelSegment::where('travel_request_id', $travelRequestId)
            ->where('is_cancelled', 0)
            ->sum('transport_cost');

        // Allowances: aquí vive TODO el viático
        $allowTotal = (float) TravelAllowance::where('travel_request_id', $travelRequestId)
            ->whereIn('status', ['draft', 'liquidated', 'approved'])
            ->sum('calculated_amount');

        $finalOther = 0.0;

        TravelRequest::where('id', $travelRequestId)->update([
            'total_transport' => $transport,
            'total_per_diem'  => $allowTotal,
            'total_other'     => $finalOther,
            'total_amount'    => round($transport + $allowTotal + $finalOther, 2),
        ]);
    }

    protected function ensureNoMultipleOpenMotoAssignments(int $personId, array $areaIds = []): void
    {
        $activeStatuses = ['pending', 'delivered', 'approved'];

        $q = DB::table('motorcycle_assignments as ma')
            ->where('ma.person_id', $personId)
            ->whereNull('ma.returned_at')
            ->whereIn('ma.status', $activeStatuses)
            ->where(function ($w) {
                $w->whereNull('ma.end_at')
                    ->orWhere('ma.end_at', '>=', now());
            });

        if (!empty($areaIds)) {
            $q->whereIn('ma.area_id', array_map('intval', $areaIds));
        }

        $count = (int) $q->count();

        if ($count > 1) {
            Log::warning('Persona con múltiples motos activas', [
                'person_id' => $personId,
                'count'     => $count,
            ]);

            // Si quieres BLOQUEAR:
            abort(422, 'Tienes múltiples asignaciones de moto activas. Contacta a Apoyo.');
        }
    }


    protected function hasActiveMotoForPerson(int $personId, array $areaIds = []): bool
    {
        return (bool) $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
    }

    protected function debugMotoState(int $personId): void
    {
        $rows = DB::table('motorcycle_assignments')
            ->where('person_id', $personId)
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'assignment_year', 'motorcycle_id', 'area_id', 'status', 'delivered_at', 'returned_at', 'start_at', 'end_at']);

        Log::info('Moto debug state', [
            'person_id' => $personId,
            'rows' => $rows,
        ]);
    }




    protected function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function ensureTableOrFail(string $table): void
    {
        if (!$this->tableExists($table)) {
            abort(500, "No existe tabla {$table}");
        }
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
            'air_amount'        => ['nullable', 'numeric', 'min:0'], // solo aplica a municipio, pero no molesta
        ]);

        $now = now();

        $bus  = (float)($data['bus_amount'] ?? 0);
        $van  = (float)($data['van_amount'] ?? 0);
        $moto = (float)($data['motorcycle_amount'] ?? 0);
        $air  = (float)($data['air_amount'] ?? 0);

        DB::beginTransaction();
        try {

            if ($data['type'] === 'municipality') {
                $municipalityId = (int) $data['id'];

                $munName = DB::table('municipalities')->where('id', $municipalityId)->value('name');
                if (!$munName) return back()->with('error', 'Municipio no encontrado.');

                // 1) desactivar anterior
                DB::table('municipality_rates')
                    ->where('municipality_id', $municipalityId)
                    ->where('active', 1)
                    ->update(['active' => 0, 'updated_at' => $now]);

                // 2) insertar nuevo activo
                DB::table('municipality_rates')->insert([
                    'municipality_id'   => $municipalityId,
                    'municipality_name' => $munName,

                    'bus_amount'        => $bus,
                    'van_amount'        => $van,
                    'motorcycle_amount' => $moto,
                    'air_amount'        => $air,

                    'active'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $villageId = (int) $data['id'];

                $row = DB::table('villages as v')
                    ->leftJoin('municipalities as m', 'm.id', '=', 'v.municipality_id')
                    ->select('v.id', 'v.name as village_name', 'v.municipality_id', 'm.name as municipality_name')
                    ->where('v.id', $villageId)
                    ->first();

                if (!$row) return back()->with('error', 'Vereda no encontrada.');

                // 1) desactivar anterior
                DB::table('village_rates')
                    ->where('village_id', $villageId)
                    ->where('active', 1)
                    ->update(['active' => 0, 'updated_at' => $now]);

                // 2) insertar nuevo activo
                DB::table('village_rates')->insert([
                    'municipality_id'   => (int) $row->municipality_id,
                    'village_id'        => $villageId,
                    'village_name'      => (string) $row->village_name,
                    'municipality_name' => (string) ($row->municipality_name ?? ''),

                    'bus_amount'        => $bus,
                    'van_amount'        => $van,
                    'motorcycle_amount' => $moto,

                    'active'     => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            DB::commit();
            return back()->with('success', 'Tarifa guardada.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', app()->environment('local') ? $e->getMessage() : 'No se pudo guardar la tarifa.');
        }
    }

    public function edit(TravelRequest $gdfRequest)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string)$ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);
        [$col, $val] = $this->ownerFilter();

        if ($gdfRequest->module !== 'gdf' || (string)$gdfRequest->{$col} !== (string)$val || !in_array((int)$gdfRequest->area_id, $areaIds, true)) {
            abort(403);
        }

        $personId = (int)(auth()->user()->person_id ?? 0);
        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
        $hasMoto = (bool)$activeMoto;

        $documents = DB::table('travel_request_documents')
            ->where('travel_request_id', $gdfRequest->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->get();

        $gdfRequest->load(['segments', 'costs', 'allowances']);

        return view('gdf::official.requests.edit', compact(
            'ctx',
            'areaKey',
            'areaIds',
            'gdfRequest',
            'hasMoto',
            'activeMoto',
            'documents'
        ));
    }
    public function autosave(Request $request, TravelRequest $gdfRequest)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string)$ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);
        [$col, $val] = $this->ownerFilter();

        if ($gdfRequest->module !== 'gdf' || (string)$gdfRequest->{$col} !== (string)$val || !in_array((int)$gdfRequest->area_id, $areaIds, true)) {
            abort(403);
        }

        if (!in_array((string)$gdfRequest->status, ['draft', 'returned'], true)) {
            return response()->json(['ok' => false, 'message' => 'No se puede autoguardar en este estado.'], 422);
        }

        $data = $request->validate([
            'area_id'         => ['sometimes', 'integer', 'in:' . implode(',', $areaIds)],
            'budget_item_id'  => ['sometimes', 'integer'],
            'origin'          => ['sometimes', 'string', 'max:150'],

            'department_id'   => ['sometimes', 'integer'],
            'place_type'      => ['sometimes', 'in:municipio,vereda'],
            'municipality_id' => ['sometimes', 'integer'],
            'village_id'      => ['nullable', 'integer'],

            'start_date'      => ['sometimes', 'date'],
            'end_date'        => ['sometimes', 'date'],

            'transport_mode'  => ['sometimes', 'in:bus,van,motorcycle,air'],
            'trip_type'       => ['sometimes', 'in:oneway,return,roundtrip'],
            'transport_value' => ['nullable', 'numeric', 'min:0'],

            'notes'           => ['nullable', 'string', 'max:5000'],
        ]);

        $personId = (int)(auth()->user()->person_id ?? 0);
        $activeMoto = $this->activeMotorcycleAssignmentForPerson($personId, $areaIds);
        $hasMoto = (bool)$activeMoto;

        $HUILA_ID = 421;
        $deptId  = (int)($data['department_id'] ?? ($gdfRequest->segments->first()->department_id ?? 0));
        $inHuila = ($deptId === $HUILA_ID);

        // lock moto
        if ($hasMoto && $inHuila) {
            $data['transport_mode'] = 'motorcycle';
        } elseif (($data['transport_mode'] ?? '') === 'motorcycle' && (!$hasMoto || !$inHuila)) {
            return response()->json(['ok' => false, 'message' => 'Moto solo si tienes asignación y el destino es Huila.'], 422);
        }

        DB::beginTransaction();
        try {
            // update request
            foreach (['area_id', 'budget_item_id', 'origin', 'start_date', 'end_date', 'notes'] as $f) {
                if (array_key_exists($f, $data)) $gdfRequest->{$f} = $data[$f];
            }
            $gdfRequest->status = 'draft'; // sigue siendo borrador
            $gdfRequest->save();

            // update main segment (asumimos 1)
            $seg = $gdfRequest->segments()->orderBy('id')->first();
            if ($seg) {
                if (array_key_exists('department_id', $data)) $seg->department_id = (int)$data['department_id'];
                if (array_key_exists('municipality_id', $data)) $seg->municipality_id = (int)$data['municipality_id'];
                if (array_key_exists('village_id', $data)) $seg->village_id = $data['village_id'] ? (int)$data['village_id'] : null;

                if (array_key_exists('transport_mode', $data)) {
                    $seg->transport_type = match ($data['transport_mode']) {
                        'air' => 'aereo',
                        'motorcycle' => 'moto',
                        default => 'terrestre',
                    };
                }

                if (array_key_exists('trip_type', $data)) {
                    $tripType = (string)$data['trip_type'];
                    $isRoundTrip = in_array($tripType, ['roundtrip', 'return'], true);
                    $seg->trip_type = $isRoundTrip ? 'round_trip' : 'one_way';
                    $seg->trips = $isRoundTrip ? 2 : 1;
                }

                $seg->save();
            }

            // recalcular totales (si tu transporte vive en segmentos/costs/allowances)
            $this->recalcRequestTotals((int)$gdfRequest->id);

            DB::commit();

            return response()->json(['ok' => true, 'message' => 'Autoguardado', 'id' => $gdfRequest->id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Error autoguardando'], 500);
        }
    }
    public function submit(TravelRequest $gdfRequest)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string)$ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);
        [$col, $val] = $this->ownerFilter();

        if ($gdfRequest->module !== 'gdf' || (string)$gdfRequest->{$col} !== (string)$val || !in_array((int)$gdfRequest->area_id, $areaIds, true)) {
            abort(403);
        }

        if (!in_array((string)$gdfRequest->status, ['draft', 'returned'], true)) {
            return back()->with('error', 'No puedes enviar en este estado.');
        }

        // Documentos requeridos (si marcaste is_required)
        $missing = DB::table('travel_request_documents')
            ->where('travel_request_id', $gdfRequest->id)
            ->where('is_required', 1)
            ->whereNull('deleted_at')
            ->whereNotIn('status', ['submitted', 'approved'])
            ->count();

        if ($missing > 0) {
            return back()->with('error', 'Faltan documentos obligatorios para enviar la solicitud.');
        }

        $gdfRequest->status = 'submitted';
        if (\Illuminate\Support\Facades\Schema::hasColumn($gdfRequest->getTable(), 'submitted_at')) {
            $gdfRequest->submitted_at = now();
        }
        $gdfRequest->save();

        return redirect()->route('gdf.instructor.requests.show', $gdfRequest->id)
            ->with('success', 'Solicitud enviada.');
    }
    public function uploadDocument(Request $request, int $travelRequestId)
    {
        if ($r = $this->requireOfficialContext()) return $r;

        $ctx = $this->ctx();
        $areaIds = $this->areaIdsByKey((string)$ctx['area']);
        [$col, $val] = $this->ownerFilter();

        $travel = TravelRequest::query()
            ->where('module', 'gdf')
            ->where($col, $val)
            ->whereIn('area_id', $areaIds)
            ->findOrFail($travelRequestId);

        if (!in_array($travel->status, ['draft', 'submitted', 'returned'], true)) {
            return back()->with('error', 'No puedes subir documentos en este estado.');
        }

        $data = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'file'          => ['required', 'file', 'max:10240'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $file = $data['file'];
            $path = $file->store("gdf/requests/{$travel->id}/documents", 'public');

            DB::table('travel_request_documents')->insert([
                'travel_request_id' => $travel->id,
                'document_type'     => $data['document_type'],
                'original_name'     => $file->getClientOriginalName(),
                'stored_name'       => basename($path),
                'path'              => $path,
                'disk'              => 'public',
                'mime_type'         => $file->getClientMimeType(),
                'size_bytes'        => $file->getSize(),
                'status'            => 'submitted',
                'notes'             => $data['notes'] ?? null,
                'uploaded_by'       => auth()->user()->person_id,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            return back()->with('success', 'Documento subido correctamente.');
        } catch (\Throwable $e) {
            return back()->with('error', app()->environment('local') ? $e->getMessage() : 'No se pudo subir el documento.');
        }
    }
    public function deleteDocument(int $documentId)
    {
        if ($r = $this->requireOfficialContext()) return $r;

        $doc = DB::table('travel_request_documents')->where('id', $documentId)->first();
        if (!$doc) abort(404);

        $travel = TravelRequest::findOrFail($doc->travel_request_id);

        if (!in_array($travel->status, ['draft', 'submitted', 'returned'], true)) {
            return back()->with('error', 'No se puede eliminar el documento en este estado.');
        }

        DB::table('travel_request_documents')
            ->where('id', $documentId)
            ->update(['deleted_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Documento eliminado.');
    }
    public function createDraft(Request $request)
    {
        if ($r = $this->requireOfficialContext()) return $r;
        $ctx = $this->ctx();

        $areaKey = (string)$ctx['area'];
        $areaIds = $this->areaIdsByKey($areaKey);
        [$col, $val] = $this->ownerFilter();

        $personId = (int)(auth()->user()->person_id ?? 0);
        if (!$personId || empty($areaIds)) abort(403);

        $data = $request->validate([
            'area_id'        => ['required', 'integer', 'in:' . implode(',', $areaIds)],
            'budget_item_id' => ['nullable', 'integer'],
            'origin'         => ['nullable', 'string', 'max:150'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date'],
        ]);

        DB::beginTransaction();
        try {
            $isEmployee   = $this->isEmployeePlant($personId);
            $isContractor = $this->isContractorActive($personId);
            $isPlant      = $isEmployee && !$isContractor;



            $req = new TravelRequest();
            $req->module = 'gdf';
            $req->source = 'manual';
            $req->{$col} = $val;

            $req->area_id        = (int)$data['area_id'];
            $req->budget_item_id = $data['budget_item_id'] ? (int)$data['budget_item_id'] : null;

            $req->person_id   = $personId;
            $req->person_type = $isPlant ? 'staff' : 'contractor';

            $req->origin      = (string)($data['origin'] ?? 'center_default');
            $req->destination = 'Pendiente';

            $req->start_date = $data['start_date'] ?? now()->toDateString();
            $req->end_date   = $data['end_date'] ?? now()->toDateString();

            $req->notes = '';
            $req->total_transport = 0;
            $req->total_per_diem  = 0;
            $req->total_other     = 0;
            $req->total_amount    = 0;

            $req->status     = 'draft';
            $req->created_by = auth()->id();
            $req->save();

            // Segment base
            $seg = new TravelSegment();
            $seg->travel_request_id = $req->id;
            $seg->departure_at = now()->startOfDay();
            $seg->return_at    = now()->endOfDay();
            $seg->origin_place = $req->origin;
            $seg->destination_place = $req->destination;
            $seg->origin_display_name = $req->origin;
            $seg->destination_display_name = $req->destination;
            $seg->destination_type = 'municipality';
            $seg->department_id = 421; // default Huila si quieres
            $seg->municipality_id = null;
            $seg->transport_type = 'terrestre';
            $seg->trip_type = 'one_way';
            $seg->trips = 1;
            $seg->transport_cost = 0;
            $seg->per_diem_cost  = 0;
            $seg->other_cost     = 0;
            $seg->total_cost     = 0;
            $seg->save();

            DB::commit();
            return response()->json(['ok' => true, 'id' => $req->id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['ok' => false, 'message' => app()->environment('local') ? $e->getMessage() : 'Error creando borrador'], 500);
        }
    }
    public function documentPreview(int $documentId)
    {
        if ($r = $this->requireOfficialContext()) return $r;

        $doc = \DB::table('travel_request_documents')->where('id', $documentId)->first();
        if (!$doc) abort(404);

        $tr = \Modules\GDF\Entities\TravelRequest::findOrFail((int) $doc->travel_request_id);

        $ctx     = $this->ctx();
        $areaKey = (string) ($ctx['area'] ?? 'academic');
        $areaIds = $this->areaIdsByKey($areaKey);
        [$col, $val] = $this->ownerFilter();

        if (
            (string) $tr->module !== 'gdf' ||
            (string) $tr->{$col} !== (string) $val ||
            !in_array((int) $tr->area_id, $areaIds, true)
        ) abort(403);

        $path = ltrim((string) ($doc->path ?? ''), '/');
        if ($path === '') abort(404, 'Documento sin ruta');

        $disk = (string) ($doc->disk ?? 'public');

        // Normalizar prefijos comunes
        $path = preg_replace('#^(public/|storage/)#', '', $path);

        if (\Storage::disk($disk)->exists($path)) {
            $abs = \Storage::disk($disk)->path($path);

            return response()->file($abs, [
                'Content-Disposition' => 'inline; filename="' . ($doc->original_name ?? ('documento_' . $doc->id)) . '"',
            ]);
        }

        abort(404, "No existe el archivo: {$path}");
    }
}
