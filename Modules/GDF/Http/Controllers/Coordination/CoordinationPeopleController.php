<?php

namespace Modules\GDF\Http\Controllers\Coordination;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use Carbon\Carbon;

use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Employee;
use Modules\SICA\Entities\Contractor;
use Modules\SICA\Entities\Role;

use App\Models\User;

use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\BudgetItem;

class CoordinationPeopleController extends Controller
{
   

    public function index(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $allowedAreaIds = $this->allowedAreaIds($areaKey);
        if (empty($allowedAreaIds)) abort(403, 'No hay áreas configuradas para tu rol.');

        $q            = trim((string) $request->get('q', ''));
        $areaId       = (int) $request->get('area_id', 0);
        $budgetItemId = (int) $request->get('budget_item_id', 0);
        $onlyActive   = ((int)$request->get('only_active', 1) === 1);

        $year = (int) $request->get('year', now()->year);

        $areas = Area::query()
            ->whereIn('id', $allowedAreaIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($areaId <= 0 || !in_array($areaId, $allowedAreaIds, true)) {
            $areaId = (int) ($areas->first()->id ?? 0);
        }
        if ($areaId <= 0) abort(403, 'No hay áreas disponibles.');

        // ✅ AHORA: rubros permitidos salen de budgets (por area + year)
        $allowedBudgetItemIds = $this->allowedBudgetItemIdsByArea($areaId, $year);

        $budgetItems = BudgetItem::query()
            ->whereIn('id', $allowedBudgetItemIds ?: [-1])
            ->orderBy('name')
            ->get(['id', 'name']);

        $routePrefix = $this->routePrefixByRole($areaKey);
        $title       = $this->titleByArea($areaKey);

        // DETALLE por rubro
        if ($budgetItemId > 0) {
            if (!in_array($budgetItemId, $allowedBudgetItemIds, true)) abort(403, 'Rubro inválido.');

            $detail = $this->buildAssignmentsQuery($areaId, $allowedBudgetItemIds, $onlyActive, $q, $budgetItemId)
                ->orderByDesc('a.is_primary')
                ->orderByDesc('a.is_active')
                ->orderByDesc('a.start_date')
                ->paginate(20)
                ->appends($request->query());

            return view('gdf::coordination.people_by_rubro_detail', compact(
                'areaKey',
                'routePrefix',
                'title',
                'areas',
                'budgetItems',
                'areaId',
                'budgetItemId',
                'onlyActive',
                'q',
                'year',
                'detail'
            ));
        }

        // INDEX agrupado por rubro
        $grouped = DB::table('person_area_budget_assignments as a')
            ->join('budget_items as bi', 'bi.id', '=', 'a.budget_item_id')
            ->where('a.area_id', $areaId)
            ->when(!empty($allowedBudgetItemIds), fn($qq) => $qq->whereIn('a.budget_item_id', $allowedBudgetItemIds))
            ->when($onlyActive, fn($qq) => $qq->where('a.is_active', true))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->join('people as p', 'p.id', '=', 'a.person_id')
                    ->leftJoin('users as u', 'u.person_id', '=', 'p.id')
                    ->where(function ($sub) use ($q) {
                        $sub->where('p.document_number', 'like', "%{$q}%")
                            ->orWhere('p.first_name', 'like', "%{$q}%")
                            ->orWhere('p.first_last_name', 'like', "%{$q}%")
                            ->orWhere('p.second_last_name', 'like', "%{$q}%")
                            ->orWhere('u.email', 'like', "%{$q}%");
                    });
            })
            ->selectRaw('a.budget_item_id, bi.name as budget_item_name')
            ->selectRaw('COUNT(DISTINCT a.person_id) as total_people')
            ->selectRaw('SUM(CASE WHEN a.is_active = 1 THEN 1 ELSE 0 END) as active_assignments')
            ->groupBy('a.budget_item_id', 'bi.name')
            ->orderBy('bi.name')
            ->paginate(20)
            ->appends($request->query());

        return view('gdf::coordination.people_by_rubro_index', compact(
            'areaKey',
            'routePrefix',
            'title',
            'areas',
            'budgetItems',
            'areaId',
            'budgetItemId',
            'onlyActive',
            'q',
            'year',
            'grouped'
        ));
    }

    public function peopleByRubroIndex(Request $request)
    {
        return $this->index($request);
    }


    public function create(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $allowedAreaIds = $this->allowedAreaIds($areaKey);
        if (empty($allowedAreaIds)) abort(403, 'No hay áreas configuradas para tu rol.');

        $year = (int) $request->get('year', now()->year);

        $areas = Area::query()
            ->whereIn('id', $allowedAreaIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedAreaId = (int) $request->get('area_id', ($areas->first()->id ?? 0));
        if (!$selectedAreaId || !in_array($selectedAreaId, $allowedAreaIds, true)) {
            $selectedAreaId = (int) ($areas->first()->id ?? 0);
        }

        // ✅ rubros permitidos salen de budgets (area + year)
        $allowedBudgetItemIds = $this->allowedBudgetItemIdsByArea($selectedAreaId, $year);

        $budgetItems = BudgetItem::query()
            ->whereIn('id', $allowedBudgetItemIds ?: [-1])
            ->orderBy('name')
            ->get(['id', 'name']);

        $contractorTypes = DB::table('contractor_types')->orderBy('name')->get(['id', 'name']);
        $employeeTypes   = DB::table('employee_types')->orderBy('name')->get(['id', 'name', 'price']);
        $insurers        = DB::table('insurer_entities')->orderBy('name')->get(['id', 'name']);

        $positions = collect();
        try {
            $positions = DB::table('positions')->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            $positions = collect();
        }

        $routePrefix = $this->routePrefixByRole($areaKey);
        $title       = $this->titleByArea($areaKey);

        return view('gdf::coordination.creatpeople', compact(
            'areaKey',
            'areas',
            'budgetItems',
            'selectedAreaId',
            'routePrefix',
            'title',
            'year',
            'contractorTypes',
            'employeeTypes',
            'insurers',
            'positions'
        ));
    }

    /* =========================================================
     |  AJAX: rubros por area (desde budgets)
     * ========================================================= */

    public function budgetItemsByArea(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $allowedAreaIds = $this->allowedAreaIds($areaKey);

        $data = $request->validate([
            'area_id' => ['required', 'integer'],
            'year'    => ['nullable', 'integer'],
        ]);

        $areaId = (int) $data['area_id'];
        $year   = (int) ($data['year'] ?? now()->year);

        if (!in_array($areaId, $allowedAreaIds, true)) {
            return response()->json(['ok' => false, 'items' => []], 403);
        }

        $ids = $this->allowedBudgetItemIdsByArea($areaId, $year);

        $items = BudgetItem::query()
            ->whereIn('id', $ids ?: [-1])
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['ok' => true, 'items' => $items]);
    }

    /* =========================================================
     |  SEARCH por cédula (AJAX)
     * ========================================================= */

    public function search(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:30'],
            'area_id'         => ['nullable', 'integer'],
        ]);

        $doc = trim($data['document_number']);
        $areaId = (int)($data['area_id'] ?? 0);

        $person = Person::where('document_number', $doc)->first();

        if (!$person) {
            return response()->json([
                'found' => false,
                'person' => null,
                'user' => ['exists' => false],
                'employee' => ['exists' => false],
                'contractor' => [
                    'active'  => ['exists' => false, 'contract' => null],
                    'recent'  => [],
                ],
                'alerts' => [],
                'assignments' => [],
                'active_area_assignment' => null,
            ]);
        }

        $user = User::where('person_id', $person->id)->first();
        $employee = Employee::where('person_id', $person->id)->first();

        $contracts = Contractor::where('person_id', $person->id)
            ->orderByDesc('contract_start_date')
            ->limit(10)
            ->get()
            ->map(function ($c) {
                $end = $c->contract_end_date;
                $isActiveByDates = !$end || now()->toDateString() <= $end;
                $isActive = (($c->state ?? 'Activo') === 'Activo') && $isActiveByDates;

                return [
                    'id'                => $c->id,
                    'contract_number'   => $c->contract_number,
                    'contract_year'     => $c->contract_year,
                    'start_date'        => $c->contract_start_date,
                    'end_date'          => $c->contract_end_date,
                    'state'             => $c->state,
                    'is_active'         => $isActive,
                    'total_contract_value' => $c->total_contract_value,
                ];
            })
            ->values();

        $activeContract = $contracts->firstWhere('is_active', true);

        $assignments = DB::table('person_area_budget_assignments as a')
            ->leftJoin('areas as ar', 'ar.id', '=', 'a.area_id')
            ->leftJoin('budget_items as bi', 'bi.id', '=', 'a.budget_item_id')
            ->select([
                'a.id',
                'a.person_id',
                'a.contractor_id',
                'a.area_id',
                'ar.name as area_name',
                'a.budget_item_id',
                'bi.name as budget_item_name',
                'a.start_date',
                'a.end_date',
                'a.is_active',
                'a.is_primary',
            ])
            ->where('a.person_id', $person->id)
            ->orderByDesc('a.is_active')
            ->orderByDesc('a.start_date')
            ->limit(15)
            ->get();

        $activeAreaAssign = $assignments->firstWhere('is_active', 1);

        $active_area_assignment = $activeAreaAssign ? [
            'area_id' => (int)($activeAreaAssign->area_id ?? 0),
            'area_name' => (string)($activeAreaAssign->area_name ?? ''),
        ] : null;

        // alerts (opcional, por si quieres mostrarlas luego)
        $alerts = [];

        if ($employee) {
            $alerts[] = ['type' => 'info', 'text' => 'La persona ya existe como PLANTA (employees).'];
        } elseif ($activeContract) {
            $alerts[] = ['type' => 'info', 'text' => 'La persona tiene CONTRATO ACTIVO.'];
            if ($areaId > 0 && $active_area_assignment && (int)$active_area_assignment['area_id'] !== $areaId) {
                $alerts[] = ['type' => 'warning', 'text' => 'Tiene asignación activa en otra área.'];
            }
        } else {
            $alerts[] = ['type' => 'warning', 'text' => 'No se detecta planta ni contrato activo. Debes registrar vínculo.'];
        }

        return response()->json([
            'found' => true,
            'person' => [
                'id'               => $person->id,
                'document_number'  => $person->document_number,
                'first_name'       => $person->first_name,
                'first_last_name'  => $person->first_last_name,
                'second_last_name' => $person->second_last_name,
                'misena_email'     => $person->misena_email,
                'personal_email'   => $person->personal_email,
            ],
            'user' => $user ? [
                'exists'   => true,
                'id'       => $user->id,
                'email'    => $user->email,
                'nickname' => $user->nickname,
            ] : ['exists' => false],
            'employee' => $employee ? ['exists' => true] : ['exists' => false],

            // ✅ compatible con tu JS
            'contractor' => [
                'active' => [
                    'exists' => (bool)$activeContract,
                    'contract' => $activeContract ? [
                        'id' => $activeContract['id'] ?? null,
                        'contract_number' => $activeContract['contract_number'] ?? null,
                        'start_date' => $activeContract['start_date'] ?? null,
                        'end_date' => $activeContract['end_date'] ?? null,
                    ] : null,
                ],
                'recent' => $contracts->all(),
            ],

            'alerts' => $alerts,
            'assignments' => $assignments,
            'active_area_assignment' => $active_area_assignment,
        ]);
    }

    /* =========================================================
     |  STORE (tu store actual, solo ajustado el allowedBudgetItemIdsByArea con year)
     * ========================================================= */

    public function store(Request $request)
    {
        $areaKey = $this->areaKeyFromPath($request);
        $this->authorizeByArea($areaKey);

        $supervisorUserId   = Auth::id();
        $supervisorPersonId = Auth::user()->person_id ?? null;

        if (!$supervisorUserId || !$supervisorPersonId) {
            return back()->withInput()->with('error', 'Tu usuario no está asociado a People. No se puede asignar supervisor.');
        }

        $allowedAreaIds = $this->allowedAreaIds($areaKey);
        if (empty($allowedAreaIds)) abort(403, 'No hay áreas configuradas para tu rol.');

        $data = $request->validate([
            // People
            'document_number'  => ['required', 'string', 'max:30'],
            'first_name'       => ['required', 'string', 'max:120'],
            'first_last_name'  => ['required', 'string', 'max:120'],
            'second_last_name' => ['nullable', 'string', 'max:120'],
            'personal_email'   => ['nullable', 'email', 'max:255'],
            'misena_email'     => ['nullable', 'email', 'max:255'],

            // ✅ Vigencia (para filtrar budgets)
            'year' => ['nullable', 'integer'],

            // Asignación
            'area_id'           => ['required', 'integer'],
            'budget_item_ids'   => ['required', 'array', 'min:1'],
            'budget_item_ids.*' => ['integer'],

            // Vínculo
            'link_type' => ['required', Rule::in(['employee', 'contractor'])],

            // Modo contrato
            'contract_mode' => ['nullable', Rule::in(['days', 'hours'])],

            // Contractor (básico)
            'contract_number'      => ['nullable', 'string', 'max:80'],
            'contract_year'        => ['nullable', 'integer'],
            'contract_start_date'  => ['nullable', 'date'],
            'contract_end_date'    => ['nullable', 'date'],
            'total_contract_value' => ['nullable', 'numeric', 'min:0'],
            'contractor_type_id'   => ['nullable', 'integer'],
            'contract_object'      => ['nullable', 'string'],
            'contract_obligations' => ['nullable', 'string'],
            'state_contractor'     => ['nullable', 'in:Activo,Inactivo'],
            'amount_hours'         => ['nullable', 'integer', 'min:0'],
            'assigment_value'      => ['nullable', 'numeric', 'min:0'],

            // Contractor: NOT NULL (según tu tabla)
            'employee_type_id'       => ['nullable', 'integer'],
            'SIIF_code'              => ['nullable', 'string', 'max:255'],
            'insurer_entity_id'      => ['nullable', 'integer'],
            'policy_number'          => ['nullable', 'string', 'max:255'],
            'policy_issue_date'      => ['nullable', 'date'],
            'policy_approval_date'   => ['nullable', 'date'],
            'policy_effective_date'  => ['nullable', 'date'],
            'policy_expiration_date' => ['nullable', 'date'],
            'risk_type'              => ['nullable', Rule::in(['I', 'II', 'III', 'IV', 'V'])],

            // Employee (planta)
            'employee_contract_number'     => ['nullable', 'integer'],
            'contract_date'                => ['nullable', 'date'],
            'professional_card_number'     => ['nullable', 'string', 'max:255'],
            'professional_card_issue_date' => ['nullable', 'date'],
            'employee_type_id_planta'      => ['nullable', 'integer'],
            'position_id'                  => ['nullable', 'integer'],
            'risk_type_employee'           => ['nullable', Rule::in(['I', 'II', 'III', 'IV', 'V'])],
            'state_employee'               => ['nullable', 'in:Activo,Inactivo'],

            // Usuario
            'create_user' => ['nullable', 'boolean'],
            'user_email'  => ['nullable', 'email', 'max:255'],
            'nickname'    => ['nullable', 'string', 'max:255'],
            'role_slug'   => ['nullable', 'string', 'max:120'],

            // Vigencia asignación
            'assignment_start_date' => ['nullable', 'date'],
            'assignment_end_date'   => ['nullable', 'date'],
            'is_primary'            => ['nullable', 'boolean'],

            // Correo
            'send_email' => ['nullable', 'boolean'],
        ]);

        $year = (int)($data['year'] ?? now()->year);

        $areaId = (int) $data['area_id'];
        if (!in_array($areaId, $allowedAreaIds, true)) {
            return back()->withInput()->with('error', 'Área inválida para tu rol.');
        }

        // ✅ AHORA: validación de rubros contra budgets (area + year)
        $allowedBudgetItemIds = $this->allowedBudgetItemIdsByArea($areaId, $year);

        $budgetItemIds = array_values(array_unique(array_map('intval', $data['budget_item_ids'] ?? [])));
        foreach ($budgetItemIds as $bid) {
            if (!in_array($bid, $allowedBudgetItemIds, true)) {
                return back()->withInput()->with('error', 'Uno de los rubros seleccionados NO tiene presupuesto en Budgets para esta área/vigencia.');
            }
        }

        $sendEmail    = (bool)($data['send_email'] ?? true);
        $contractMode = $data['contract_mode'] ?? 'days';
        $amountHours  = (int)($data['amount_hours'] ?? 0);
        $assigmentVal = (int)($data['assigment_value'] ?? 0);

        try {

            $result = DB::transaction(function () use (
                $data,
                $areaId,
                $budgetItemIds,
                $supervisorUserId,
                $supervisorPersonId,
                $contractMode,
                $amountHours,
                $assigmentVal,
                $sendEmail
            ) {
                // 1) Persona
                $person = Person::updateOrCreate(
                    ['document_number' => $data['document_number']],
                    [
                        'first_name'       => $data['first_name'],
                        'first_last_name'  => $data['first_last_name'],
                        'second_last_name' => $data['second_last_name'] ?? null,
                        'personal_email'   => $data['personal_email'] ?? null,
                        'misena_email'     => $data['misena_email'] ?? null,
                    ]
                );

                $existingUser = User::where('person_id', $person->id)->first();
                $existingEmployee = Employee::where('person_id', $person->id)->first();

                $activeContractor = Contractor::query()
                    ->where('person_id', $person->id)
                    ->where(function ($q) {
                        $q->whereNull('contract_end_date')
                            ->orWhereDate('contract_end_date', '>=', now()->toDateString());
                    })
                    ->orderByDesc('contract_start_date')
                    ->first();

                $contractorId = $activeContractor ? (int)$activeContractor->id : null;

                // 2) Employee / Contractor
                if ($data['link_type'] === 'employee') {

                    if (!$existingEmployee) {
                        $missing = [];
                        foreach ([
                            'employee_contract_number',
                            'contract_date',
                            'employee_type_id_planta',
                            'position_id',
                            'risk_type_employee',
                        ] as $k) {
                            if (empty($data[$k])) $missing[] = $k;
                        }
                        if ($missing) {
                            throw new \RuntimeException('Para Planta faltan campos obligatorios: ' . implode(', ', $missing));
                        }

                        Employee::updateOrCreate(
                            ['person_id' => $person->id],
                            [
                                'contract_number'              => (int)$data['employee_contract_number'],
                                'contract_date'                => $data['contract_date'],
                                'professional_card_number'     => $data['professional_card_number'] ?? null,
                                'professional_card_issue_date' => $data['professional_card_issue_date'] ?? null,
                                'employee_type_id'             => (int)$data['employee_type_id_planta'],
                                'position_id'                  => (int)$data['position_id'],
                                'risk_type'                    => $data['risk_type_employee'],
                                'state'                        => $data['state_employee'] ?? 'Activo',
                            ]
                        );
                    }

                    $contractorId = null;

                } else {

                    if (!$activeContractor) {

                        $hasAnyContractData =
                            !empty($data['contract_number']) ||
                            !empty($data['contract_start_date']) ||
                            !empty($data['contract_end_date']) ||
                            !empty($data['total_contract_value']) ||
                            ($contractMode === 'hours' && $amountHours > 0);

                        if (!$hasAnyContractData) {
                            throw new \RuntimeException('No existe contrato activo. Debes registrar datos del contrato para crear uno nuevo.');
                        }

                        if (empty($data['contractor_type_id'])) {
                            throw new \RuntimeException('contractor_type_id es obligatorio para crear contrato.');
                        }

                        $contractStart = $data['contract_start_date'] ?? null;
                        $contractEnd   = $data['contract_end_date'] ?? null;

                        if ($contractMode === 'hours') {
                            if (!$contractStart) throw new \RuntimeException('Para contrato por horas, contract_start_date es obligatorio.');
                            if ($amountHours <= 0) throw new \RuntimeException('Para contrato por horas, amount_hours debe ser > 0.');
                            $calc = $this->addWorkingDaysFromHours($contractStart, $amountHours, 7);
                            $contractEnd = $calc['end_date'];
                        } else {
                            if (!$contractStart) throw new \RuntimeException('contract_start_date es obligatorio.');
                            if ($contractStart && $contractEnd && Carbon::parse($contractEnd)->lt(Carbon::parse($contractStart))) {
                                throw new \RuntimeException('contract_end_date no puede ser menor que contract_start_date.');
                            }
                        }

                        $contractYear = (int)($data['contract_year'] ?? 0);
                        if (!$contractYear && $contractStart) $contractYear = Carbon::parse($contractStart)->year;
                        if (!$contractYear) $contractYear = (int)now()->year;

                        $missing = [];
                        foreach ([
                            'employee_type_id',
                            'SIIF_code',
                            'insurer_entity_id',
                            'policy_number',
                            'policy_issue_date',
                            'policy_approval_date',
                            'policy_effective_date',
                            'policy_expiration_date',
                            'risk_type',
                        ] as $k) {
                            if (empty($data[$k])) $missing[] = $k;
                        }
                        if ($missing) {
                            throw new \RuntimeException('Faltan campos obligatorios del contrato: ' . implode(', ', $missing));
                        }

                        if ($sendEmail && !$existingUser) {
                            $candidateEmail = $data['user_email']
                                ?? ($data['personal_email'] ?? ($data['misena_email'] ?? null));
                            if (!$candidateEmail) {
                                throw new \RuntimeException('Marcaste "Enviar correo", pero no hay correo. Registra personal_email/misena_email (o user_email).');
                            }
                        }

                        $c = Contractor::create([
                            'person_id'              => $person->id,
                            'supervisor_id'          => $supervisorPersonId,

                            'contract_number'        => $data['contract_number'],
                            'contract_year'          => $contractYear,
                            'contract_start_date'    => $contractStart,
                            'contract_end_date'      => $contractEnd,
                            'total_contract_value'   => (int)($data['total_contract_value'] ?? 0),

                            'contractor_type_id'     => (int)$data['contractor_type_id'],
                            'contract_object'        => $data['contract_object'] ?? '',
                            'contract_obligations'   => $data['contract_obligations'] ?? '',

                            'amount_hours'           => $amountHours,
                            'assigment_value'        => $assigmentVal,

                            'employee_type_id'       => (int)$data['employee_type_id'],
                            'SIIF_code'              => $data['SIIF_code'],
                            'insurer_entity_id'      => (int)$data['insurer_entity_id'],
                            'policy_number'          => $data['policy_number'],
                            'policy_issue_date'      => $data['policy_issue_date'],
                            'policy_approval_date'   => $data['policy_approval_date'],
                            'policy_effective_date'  => $data['policy_effective_date'],
                            'policy_expiration_date' => $data['policy_expiration_date'],
                            'risk_type'              => $data['risk_type'],

                            'state'                  => $data['state_contractor'] ?? 'Activo',
                        ]);

                        $contractorId = (int)$c->id;
                        $activeContractor = $c;
                    }
                }

                // 3) Usuario + roles
                $createdUser = false;

                if (!$existingUser) {
                    $createUser = (bool)($data['create_user'] ?? true);
                    if ($createUser) {
                        $emailLogin = $data['user_email'] ?? ($data['personal_email'] ?? ($data['misena_email'] ?? null));
                        if (!$emailLogin) throw new \RuntimeException('No hay email disponible para crear usuario.');

                        $emailLogin = strtolower(trim($emailLogin));
                        $nickname = $data['nickname'] ?? $this->defaultNickname((string)$person->first_name, (string)$person->document_number);

                        $user = User::where('email', $emailLogin)->first();

                        if (!$user) {
                            $user = User::create([
                                'person_id' => $person->id,
                                'nickname'  => $nickname,
                                'email'     => $emailLogin,
                                'password'  => Hash::make(Str::random(40)),
                            ]);
                            $createdUser = true;

                            if (Schema::hasColumn('users', 'force_password_change')) {
                                $user->force_password_change = 1;
                                $user->save();
                            }
                        } else {
                            if (empty($user->person_id)) {
                                $user->person_id = $person->id;
                                $user->save();
                            }
                        }

                        $this->assignRoles($user, $data);
                        $existingUser = $user;
                    }
                } else {
                    $this->assignRoles($existingUser, $data);
                    if (Schema::hasColumn('users', 'force_password_change')) {
                        $existingUser->force_password_change = 1;
                        $existingUser->save();
                    }
                }

                // 4) Asignaciones area+rubro
                $start = $data['assignment_start_date']
                    ?? ($data['contract_start_date'] ?? ($activeContractor->contract_start_date ?? null));

                $end   = $data['assignment_end_date']
                    ?? ($data['contract_end_date'] ?? ($activeContractor->contract_end_date ?? null));

                $isActive = true;
                if ($end && now()->toDateString() > $end) $isActive = false;

                $makePrimary = (bool)($data['is_primary'] ?? false);
                if ($makePrimary) {
                    DB::table('person_area_budget_assignments')
                        ->where('person_id', $person->id)
                        ->where('area_id', $data['area_id'])
                        ->where('is_primary', true)
                        ->update(['is_primary' => false, 'updated_at' => now()]);
                }

                foreach ($budgetItemIds as $budgetItemId) {

                    $activeSame = DB::table('person_area_budget_assignments')
                        ->where('person_id', $person->id)
                        ->where('area_id', $data['area_id'])
                        ->where('budget_item_id', $budgetItemId)
                        ->where('is_active', true)
                        ->first();

                    if ($activeSame) {
                        DB::table('person_area_budget_assignments')
                            ->where('id', $activeSame->id)
                            ->update([
                                'is_active'  => false,
                                'end_date'   => $end ?? now()->toDateString(),
                                'updated_at' => now(),
                            ]);
                    }

                    DB::table('person_area_budget_assignments')->insert([
                        'person_id'      => $person->id,
                        'contractor_id'  => $contractorId,
                        'area_id'        => $data['area_id'],
                        'budget_item_id' => $budgetItemId,
                        'supervisor_id'  => $supervisorUserId,
                        'start_date'     => $start,
                        'end_date'       => $end,
                        'is_active'      => $isActive,
                        'is_primary'     => $makePrimary,
                        'created_at'     => now(),
                        'updated_at'     => now(),
                    ]);
                }

                $areaName = (string) Area::where('id', $data['area_id'])->value('name');
                $rubros   = BudgetItem::whereIn('id', $budgetItemIds)->orderBy('name')->pluck('name')->all();

                return [
                    'person'       => $person,
                    'user'         => $existingUser,
                    'area_name'    => $areaName,
                    'rubros'       => $rubros,
                    'created_user' => $createdUser,
                ];
            });

            if ($sendEmail) {

                if (empty($result['user'])) {
                    $route = ($areaKey === 'academic')
                        ? 'gdf.academic.people.index'
                        : 'gdf.campesena.people.index';

                    return redirect()->route($route)
                        ->with('warning', 'Se guardó, pero no se envió correo porque no se creó/tenía usuario. (Activa "Crear usuario" para enviar link).');
                }

                $mailTo = $result['user']->email
                    ?? $result['person']->personal_email
                    ?? $result['person']->misena_email
                    ?? null;

                if (!$mailTo) {
                    return back()->withInput()->with('error', 'Se guardó, pero no hay correo para enviar link de contraseña.');
                }

                $plainToken = $this->createLoginTokenForUser($result['user']);

                $ok = $this->sendMagicLinkEmailWithRetry(
                    $result['person'],
                    $result['user'],
                    (string)$result['area_name'],
                    (array)$result['rubros'],
                    $plainToken,
                    3
                );

                if (!$ok) {
                    return back()->withInput()->with('error', 'Se guardó el registro, pero el correo falló (reintentos agotados).');
                }
            }

            $route = ($areaKey === 'academic')
                ? 'gdf.academic.people.index'
                : 'gdf.campesena.people.index';

            $msg = 'Registro guardado correctamente (rubros filtrados por budgets).';
            if ($sendEmail) $msg .= ' Correo enviado con enlace para crear contraseña.';

            return redirect()->route($route)->with('success', $msg);

        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /* =========================================================
     |  Roles (GDF + SIGAC)
     * ========================================================= */

    private function assignRoles(User $user, array $data): void
    {
        $slugs = [];

        if (!empty($data['role_slug'])) {
            $slugs[] = (string)$data['role_slug'];
        }

        $slugs[] = 'sigac.instructor';

        $slugs = array_values(array_unique(array_filter($slugs)));

        $roleIds = Role::whereIn('slug', $slugs)->pluck('id')->all();
        if (!empty($roleIds)) {
            $user->roles()->syncWithoutDetaching($roleIds);
        }
    }

    private function titleByArea(string $areaKey): string
    {
        return $areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica';
    }

    private function routePrefixByRole(string $areaKey): string
    {
        $isSupport = $this->isSupportRoleForArea($areaKey);

        if ($isSupport) {
            return $areaKey === 'campesena' ? 'gdf.support.campesena' : 'gdf.support.academic';
        }

        return $areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic';
    }

    private function isSupportRoleForArea(string $areaKey): bool
    {
        if (!function_exists('checkRol')) return false;

        if ($areaKey === 'campesena') {
            return checkRol('gdf.campesena_support') || checkRol('gdf.superadmin');
        }
        return checkRol('gdf.academic_support') || checkRol('gdf.superadmin');
    }

    private function authorizeAcademicOrSupport(): void
    {
        if (!function_exists('checkRol')) abort(403);

        if (
            !checkRol('gdf.academic_coordinator') &&
            !checkRol('gdf.academic_support') &&
            !checkRol('gdf.superadmin')
        ) {
            abort(403);
        }
    }

    private function authorizeCampesenaOrSupport(): void
    {
        if (!function_exists('checkRol')) abort(403);

        if (
            !checkRol('gdf.campesena_coordinator') &&
            !checkRol('gdf.campesena_support') &&
            !checkRol('gdf.superadmin')
        ) {
            abort(403);
        }
    }

    private function authorizeByArea(string $areaKey): void
    {
        if ($areaKey === 'campesena') $this->authorizeCampesenaOrSupport();
        else $this->authorizeAcademicOrSupport();
    }

    private function areaKeyFromPath(Request $request): string
    {
        $path = $request->path();
        return str_contains($path, 'campesena') ? 'campesena' : 'academic';
    }

    private function allowedAreaIds(string $areaKey): array
    {
        $ids = (array) config("gdf.area_groups.$areaKey", []);
        return array_values(array_filter(array_map('intval', $ids)));
    }

    /**
     * ✅ Fuente oficial de "rubros disponibles": budgets (por area + year).
     */
    private function allowedBudgetItemIdsByArea(int $areaId, int $year): array
    {
        if (!Schema::hasTable('budgets')) return [];

        return DB::table('budgets')
            ->where('area_id', $areaId)
            ->where('year', $year)
            ->where('active', 1)
            ->pluck('budget_item_id')
            ->map(fn($x) => (int)$x)
            ->unique()
            ->values()
            ->all();
    }

    private function defaultNickname(string $firstName, string $doc): string
    {
        $base = Str::upper(Str::substr(preg_replace('/\s+/', '', $firstName), 0, 3));
        return $base . $doc;
    }

    private function buildAssignmentsQuery(int $areaId, array $allowedBudgetItemIds, bool $onlyActive, string $q, int $budgetItemId = 0)
    {
        $query = DB::table('person_area_budget_assignments as a')
            ->join('people as p', 'p.id', '=', 'a.person_id')
            ->leftJoin('users as u', 'u.person_id', '=', 'p.id')
            ->leftJoin('areas as ar', 'ar.id', '=', 'a.area_id')
            ->leftJoin('budget_items as bi', 'bi.id', '=', 'a.budget_item_id')
            ->select([
                'a.id as assignment_id',
                'a.person_id',
                'p.document_number',
                'p.first_name',
                'p.first_last_name',
                'p.second_last_name',
                'u.email as user_email',
                'a.contractor_id',
                'a.area_id',
                'ar.name as area_name',
                'a.budget_item_id',
                'bi.name as budget_item_name',
                'a.start_date',
                'a.end_date',
                'a.is_active',
                'a.is_primary',
            ])
            ->where('a.area_id', $areaId);

        if (!empty($allowedBudgetItemIds)) $query->whereIn('a.budget_item_id', $allowedBudgetItemIds);
        if ($onlyActive) $query->where('a.is_active', true);
        if ($budgetItemId > 0) $query->where('a.budget_item_id', $budgetItemId);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('p.document_number', 'like', "%{$q}%")
                    ->orWhere('p.first_name', 'like', "%{$q}%")
                    ->orWhere('p.first_last_name', 'like', "%{$q}%")
                    ->orWhere('p.second_last_name', 'like', "%{$q}%")
                    ->orWhere('u.email', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    private function addWorkingDaysFromHours(string $startDate, int $hours, int $hoursPerDay = 7): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        if ($hours <= 0) return ['working_days' => 0, 'end_date' => $start->toDateString()];

        $workingDaysNeeded = (int) ceil($hours / max(1, $hoursPerDay));
        $date = $start->copy();
        $added = 0;

        while ($added < $workingDaysNeeded) {
            if ($date->isWeekday()) $added++;
            if ($added < $workingDaysNeeded) $date->addDay();
        }

        return ['working_days' => $workingDaysNeeded, 'end_date' => $date->toDateString()];
    }

    private function createLoginTokenForUser(User $user): string
    {
        $plain = Str::random(64);

        $payload = [
            'user_id'    => $user->id,
            'used_at'    => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('login_tokens', 'expires_at')) {
            $payload['expires_at'] = now()->addHours(48);
        }

        if (Schema::hasColumn('login_tokens', 'token_hash')) {
            $payload['token_hash'] = hash('sha256', $plain);
        } else {
            $payload['token'] = $plain;
        }

        DB::table('login_tokens')->insert($payload);

        return $plain;
    }

    private function sendMagicLinkEmailWithRetry(
        Person $person,
        ?User $user,
        string $areaName,
        array $rubros,
        string $plainToken,
        int $retries = 3
    ): bool {
        $to = $user->email
            ?? $person->personal_email
            ?? $person->misena_email
            ?? null;

        if (!$to) return false;

        $fullName = trim(($person->first_name ?? '') . ' ' . ($person->first_last_name ?? '') . ' ' . ($person->second_last_name ?? ''));
        $rubrosTxt = !empty($rubros) ? implode(', ', $rubros) : '—';

        $url = route('gdf.security.magic', ['token' => $plainToken]);

        $subject = "Acceso y creación de contraseña - {$areaName}";
        $lines = [
            "Hola {$fullName},",
            "",
            "Se registró tu asignación en el sistema.",
            "Área: {$areaName}",
            "Rubros: {$rubrosTxt}",
            "",
            "Para ingresar, define tu contraseña aquí:",
            $url,
            "",
            "Este enlace expira y solo se puede usar una vez.",
            "Si no solicitaste esto, ignora este correo.",
        ];

        $body = implode("\n", $lines);

        for ($i = 1; $i <= max(1, $retries); $i++) {
            try {
                Mail::raw($body, function ($msg) use ($to, $subject) {
                    $msg->to($to)->subject($subject);
                });
                return true;
            } catch (\Throwable $e) {
                usleep(200000);
            }
        }

        return false;
    }
}
