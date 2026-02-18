<?php

namespace Modules\GDF\Http\Controllers\Instructor;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Modules\GDF\Http\Controllers\Instructor\BaseOfficialController;
use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelSegment;
use Modules\GDF\Entities\TravelCost;
use Modules\GDF\Entities\TravelLog;
use Modules\GDF\Entities\TravelAllowance;
use Modules\SIGAC\Entities\ProgramRequest;
use Illuminate\Support\Facades\Storage;


class sitraInstructorController extends BaseOfficialController
{

    public function programs(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        $personId = (int)$user->person->id;
        if ($personId <= 0) abort(403);

        $ctx = session('gdf_context', []);
        $areaKey = (string)($ctx['area'] ?? $request->get('area', 'academic')); // academic|campesena
        $areaIds = $this->areaIdsByKey($areaKey);

        $allowedStates = ['Confirmado', 'Preconfirmado'];
        $state = (string)$request->get('state', 'Confirmado');
        if (!in_array($state, $allowedStates, true)) $state = 'Confirmado';

        $programRequests = ProgramRequest::query()
            ->where('person_id', $personId)
            ->where('state', $state)
            ->when(!empty($areaIds), fn($q) => $q->whereIn('area_id', $areaIds))
            ->whereHas('programRequestDates', function ($q) {
                $q->whereNull('deleted_at');
            })
            ->with(['programRequestDates' => function ($q) {
                $q->whereNull('deleted_at')->orderBy('date')->orderBy('start_time');
            }])
            ->orderByDesc('id')
            ->paginate(15)
            ->appends(['state' => $state, 'area' => $areaKey]);

        return view('gdf::official.SITRAV.index', compact(
            'programRequests',
            'state',
            'allowedStates',
            'areaKey'
        ));
    }
    public function createFromProgram(Request $request, int $programRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int) $user->person->id;

        $ctxArea = data_get(session('gdf_context'), 'area');
        $ctxAreaLabel = $ctxArea === 'campesena' ? 'Campesena' : ($ctxArea === 'academic' ? 'Académica' : null);

        $program = ProgramRequest::query()
            ->with([
                'programRequestDates' => function ($q) {
                    $q->whereNull('deleted_at')->orderBy('date')->orderBy('start_time');
                },
                'area',
                'budgetItem',
            ])
            ->findOrFail($programRequestId);

        if ((int) $program->person_id !== $personId) abort(403);

        if (!in_array((string) $program->state, ['Confirmado', 'Preconfirmado'], true)) {
            return back()->with('error', 'El programa aún no está en estado válido para SITRAV.');
        }

        $existing = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('source', 'sigac')
            ->where('source_request_id', (int) $program->id)
            ->where('person_id', $personId)
            ->first();

        if ($existing) {
            return redirect()->route('gdf.instructor.sitrav.requests.edit', $existing->id);
        }

        $dates = $program->programRequestDates ?? collect();

        // ===== UI: nombres =====
        $areaName = null;
        if ($program->relationLoaded('area') && $program->area) {
            $areaName = $program->area->name
                ?? $program->area->nombre
                ?? $program->area->name_area
                ?? $program->area->description
                ?? null;
        }
        $areaName = $areaName ?: $ctxAreaLabel;

        $budgetName = null;
        if ($program->relationLoaded('budgetItem') && $program->budgetItem) {
            $budgetName = $program->budgetItem->name
                ?? $program->budgetItem->nombre
                ?? $program->budgetItem->name_budget_item
                ?? $program->budgetItem->description
                ?? null;
        }

        $preview = [
            'origin'           => 'CEFA Campoalegre',
            'destination'      => $this->buildDestinationFromProgram($program),
            'start_date'       => $dates->min('date') ?: now()->toDateString(),
            'end_date'         => $dates->max('date') ?: now()->toDateString(),

            'area_id'          => (int) ($program->area_id ?? 0),
            'budget_item_id'   => (int) ($program->budget_item_id ?? 0),

            'area_name'        => $areaName,
            'budget_item_name' => $budgetName,
        ];

        $isVillage = $this->detectPlaceType($program) === 'vereda';

        [$personType] = $this->resolvePersonType($personId);
        $isStaff = ($personType === 'staff');

        $hasMoto = $this->hasMotorcycle($personId, (int) ($program->area_id ?? 0));

        $forceMotorcycle = false;

        if ($forceMotorcycle) {
            $transportOptions = ['motorcycle' => 'Moto (obligatoria)'];
        } else {
            $transportOptions = $hasMoto
                ? ['motorcycle' => 'Moto', 'bus' => 'Transporte público']
                : ['bus' => 'Transporte público'];
        }

        $defaultTransport = $forceMotorcycle
            ? 'motorcycle'
            : (array_key_exists('motorcycle', $transportOptions) ? 'motorcycle' : 'bus');

        $ratesByTransport = [
            'bus'        => (float) $this->getTransportRateFromProgram($program, 'bus'),
            'van'        => (float) $this->getTransportRateFromProgram($program, 'van'),
            'motorcycle' => 0.0,
        ];

        $baseRateBus = $ratesByTransport['bus'];

        $defaultTransportRate = $defaultTransport === 'motorcycle'
            ? 0.0
            : (float) $this->getTransportRateFromProgram($program, $defaultTransport);

        $fuelBaseRate = (float) (config('gdf.fuel.default_unit_amount') ?? 25000);

        \Log::info('SITRAV Create - Rates Debug', [
            'program_id' => $program->id,
            'municipality_id' => $program->municipality_id,
            'village_id' => $program->village_id,
            'place_type' => $program->place_type,
            'detected_type' => $this->detectPlaceType($program),
            'rates_by_transport' => $ratesByTransport,
        ]);

        return view('gdf::official.SITRAV.create', compact(
            'program',
            'preview',
            'dates',
            'isVillage',
            'isStaff',
            'personType',
            'hasMoto',
            'forceMotorcycle',
            'transportOptions',
            'defaultTransport',
            'baseRateBus',
            'fuelBaseRate',
            'defaultTransportRate',
            'ratesByTransport'
        ));
    }


    public function storeFromProgram(Request $request, int $programRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int) $user->person->id;

        $program = ProgramRequest::query()
            ->with(['programRequestDates' => function ($q) {
                $q->whereNull('deleted_at')->orderBy('date')->orderBy('start_time');
            }])
            ->findOrFail($programRequestId);

        if ((int) $program->person_id !== $personId) abort(403);

        if (!in_array((string) $program->state, ['Confirmado', 'Preconfirmado'], true)) {
            return back()->with('error', 'El programa aún no está en estado válido para SITRAV.');
        }

        $existing = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('source', 'sigac')
            ->where('source_request_id', (int) $program->id)
            ->where('person_id', $personId)
            ->first();

        if ($existing) {
            return redirect()->route('gdf.instructor.sitrav.requests.edit', $existing->id);
        }

        $dates = $program->programRequestDates ?? collect();
        if ($dates->count() <= 0) return back()->with('error', 'El programa no tiene fechas.');

        $allDateIds = $dates->pluck('id')->map(fn($v) => (int) $v)->values()->all();
        $decisions  = (array) $request->input('decisions', []);

        foreach ($allDateIds as $dateId) {
            $mode = $decisions[$dateId]['mode'] ?? null;

            if (!in_array($mode, ['selected', 'excluded', 'reschedule'], true)) {
                return back()->withInput()->with('error', "Falta decisión para la fecha #{$dateId}.");
            }

            if (in_array($mode, ['excluded', 'reschedule'], true)) {
                $reason = trim((string) ($decisions[$dateId]['reason'] ?? ''));
                if ($reason === '') {
                    return back()->withInput()->with('error', "Debes indicar el motivo en la fecha #{$dateId}.");
                }
            }

            if ($mode === 'reschedule') {
                $ps = trim((string) ($decisions[$dateId]['proposed_start'] ?? ''));
                $pe = trim((string) ($decisions[$dateId]['proposed_end'] ?? ''));
                if ($ps === '' || $pe === '') {
                    return back()->withInput()->with('error', "Si reprogramas, debes proponer inicio/fin para #{$dateId}.");
                }
            }
        }

        $activeCount = 0;
        foreach ($allDateIds as $dateId) {
            $m = (string) ($decisions[$dateId]['mode'] ?? '');
            if ($m === 'selected' || $m === 'reschedule') $activeCount++;
        }
        if ($activeCount <= 0) {
            return back()->withInput()->with('error', 'Debes seleccionar o reprogramar al menos una fecha.');
        }

        $hasMoto = $this->hasMotorcycle($personId, (int) ($program->area_id ?? 0));

        $rules = [
            'cost.transport'    => ['required', Rule::in(['bus', 'motorcycle'])],
            'cost.direction'    => ['required', Rule::in(['one_way', 'two_way', 'round_trip'])],
            'cost.same_for_all' => ['nullable'],
            'cost.unit_amount'  => ['nullable', 'numeric', 'min:0'],

            'costs_by_date'               => ['array'],
            'costs_by_date.*.transport'   => ['nullable', Rule::in(['bus', 'motorcycle'])],
            'costs_by_date.*.direction'   => ['nullable', Rule::in(['one_way', 'two_way', 'round_trip'])],
            'costs_by_date.*.unit_amount' => ['nullable', 'numeric', 'min:0'],
        ];

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) return back()->withErrors($v)->withInput();

        $sameForAll = (string) $request->input('cost.same_for_all', '0') === '1';

        $transport = (string) $request->input('cost.transport');
        if (!$hasMoto && $transport === 'motorcycle') $transport = 'bus';

        $direction = $this->normalizeDirection((string) $request->input('cost.direction'));

        $suggestedUnit = ($transport === 'motorcycle')
            ? 0.0
            : (float) $this->getTransportRateFromProgram($program, $transport);

        $userUnit = $this->moneyToFloat($request->input('cost.unit_amount', 0));

        $unit = ($transport === 'motorcycle')
            ? 0.0
            : (($userUnit > 0) ? $userUnit : max(0.0, $suggestedUnit));

        $usesMoto = false;
        $byDate   = (array) $request->input('costs_by_date', []);

        if ($sameForAll) {
            $usesMoto = ($transport === 'motorcycle');
        } else {
            foreach ($allDateIds as $dateId) {
                $mode = (string) ($decisions[$dateId]['mode'] ?? '');
                if (!in_array($mode, ['selected', 'reschedule'], true)) continue;

                $tRow = (string) ($byDate[$dateId]['transport'] ?? $transport);
                if (!$hasMoto && $tRow === 'motorcycle') $tRow = 'bus';

                if ($tRow === 'motorcycle') {
                    $usesMoto = true;
                    break;
                }
            }
        }

        $fuel = (array) $request->input('fuel', []);
        $motoActiveTrips = 0;

        if ($usesMoto) {
            if ($sameForAll) {
                $motoActiveTrips = ($transport === 'motorcycle') ? $activeCount : 0;
            } else {
                foreach ($allDateIds as $dateId) {
                    $mode = (string) ($decisions[$dateId]['mode'] ?? '');
                    if (!in_array($mode, ['selected', 'reschedule'], true)) continue;

                    $tRow = (string) ($byDate[$dateId]['transport'] ?? $transport);
                    if (!$hasMoto && $tRow === 'motorcycle') $tRow = 'bus';
                    if ($tRow === 'motorcycle') $motoActiveTrips++;
                }
            }

            $fuelUnit  = $this->moneyToFloat($fuel['unit_amount'] ?? 0);
            $fuelUnits = (int) ($fuel['units'] ?? 1);
            if ($fuelUnits < 1) $fuelUnits = 1;

            if ($fuelUnit <= 0) {
                return back()->withInput()->with('error', 'Si usas moto, debes registrar el viático de gasolina (valor unitario > 0).');
            }
            if ($fuelUnits < $motoActiveTrips) {
                return back()->withInput()->with('error', "Gasolina: las unidades no pueden ser menores a los trayectos en moto ({$motoActiveTrips}).");
            }
        }

        [$resolvedPersonType] = $this->resolvePersonType($personId);
        $allowEnabled = (string) $request->input('allowances_enabled', '0') === '1';
        $allowRows    = $allowEnabled ? (array) $request->input('allowances', []) : [];
        if ($resolvedPersonType !== 'staff') $allowRows = [];

        if ($resolvedPersonType === 'staff' && $allowEnabled) {
            foreach ($allowRows as &$row) {
                $n = (int) ($row['units'] ?? 1);
                if ($n < 1) $row['units'] = 1;
            }
            unset($row);
        }

        try {
            $travel = DB::transaction(function () use (
                $program,
                $personId,
                $user,
                $dates,
                $allDateIds,
                $decisions,
                $sameForAll,
                $transport,
                $direction,
                $unit,
                $hasMoto,
                $allowRows,
                $allowEnabled,
                $usesMoto,
                $fuel,
                $byDate
            ) {
                [$personType, $employeeId, $contractorId] = $this->resolvePersonType($personId);

                $areaId       = (int) ($program->area_id ?? 0);
                $budgetItemId = (int) ($program->budget_item_id ?? 0);
                if ($areaId <= 0 || $budgetItemId <= 0) {
                    throw new \RuntimeException('SIGAC no trae area_id/budget_item_id en program_requests.');
                }

                $t = new TravelRequest();
                $t->module             = 'sitrav';
                $t->source             = 'sigac';
                $t->source_request_id  = (int) $program->id;

                $t->area_id            = $areaId;
                $t->budget_item_id     = $budgetItemId;
                $t->budget_id          = null;

                $t->person_id          = $personId;
                $t->person_type        = $personType;
                $t->employee_id        = $employeeId;
                $t->contractor_id      = $contractorId;

                $t->request_type       = 'travel';
                $t->origin             = 'CEFA Campoalegre';
                $t->destination        = $this->buildDestinationFromProgram($program);

                $t->status             = 'draft';
                $t->created_by         = (int) $user->id;

                $t->total_transport    = 0;
                $t->total_per_diem     = 0;
                $t->total_other        = 0;
                $t->total_amount       = 0;

                $t->start_date         = ($dates->min('date') ?: now()->toDateString());
                $t->end_date           = ($dates->max('date') ?: now()->toDateString());
                $t->save();

                $this->log($t->id, (int) $user->id, 'created_from_sigac', [
                    'program_request_id' => (int) $program->id,
                    'place_type_db'      => (string) ($program->place_type ?? 'municipio'),
                    'municipality_id'    => (int) ($program->municipality_id ?? 0),
                    'village_id'         => (int) ($program->village_id ?? 0),
                ]);

                foreach ($dates as $d) {
                    $dateId = (int) $d->id;
                    $mode   = (string) ($decisions[$dateId]['mode'] ?? '');

                    if ($mode !== 'selected' && $mode !== 'reschedule') continue;

                    if ($mode === 'selected') {
                        $departureAt = Carbon::parse($d->date . ' ' . $d->start_time)->format('Y-m-d H:i:s');
                        $returnAt    = Carbon::parse($d->date . ' ' . $d->end_time)->format('Y-m-d H:i:s');
                        $meta        = ['source' => 'sigac', 'program_request_date_id' => $dateId];
                    } else {
                        $ps = (string) $decisions[$dateId]['proposed_start'];
                        $pe = (string) $decisions[$dateId]['proposed_end'];
                        $departureAt = Carbon::parse($ps)->format('Y-m-d H:i:s');
                        $returnAt    = Carbon::parse($pe)->format('Y-m-d H:i:s');
                        $meta = [
                            'source' => 'sigac',
                            'program_request_date_id' => $dateId,
                            'rescheduled_local' => true,
                            'original_date' => (string) $d->date,
                            'original_start' => (string) $d->start_time,
                            'original_end' => (string) $d->end_time,
                        ];
                    }

                    $seg = new TravelSegment();
                    $seg->travel_request_id   = $t->id;
                    $seg->departure_at        = $departureAt;
                    $seg->return_at           = $returnAt;
                    $seg->origin_place        = $t->origin;
                    $seg->destination_place   = $t->destination;

                    $transportForSegment = $sameForAll ? $transport : null;

                    $this->fillSegmentGeoRatesTransport(
                        $seg,
                        $program,
                        (string)$t->destination,
                        $transportForSegment,
                        $direction
                    );

                    $seg->is_cancelled        = 0;
                    $seg->transport_cost      = 0;
                    $seg->per_diem_cost       = 0;
                    $seg->other_cost          = 0;
                    $seg->total_cost          = 0;

                    $seg->notes               = json_encode($meta, JSON_UNESCAPED_UNICODE);
                    $seg->save();
                }

                foreach ($dates as $d) {
                    $dateId = (int) $d->id;
                    $mode   = (string) ($decisions[$dateId]['mode'] ?? '');
                    if ($mode === 'selected') continue;

                    $payload = [
                        'program_request_date_id' => $dateId,
                        'reason' => trim((string) ($decisions[$dateId]['reason'] ?? '')),
                    ];

                    if ($mode === 'reschedule') {
                        $payload['proposed_start'] = (string) $decisions[$dateId]['proposed_start'];
                        $payload['proposed_end']   = (string) $decisions[$dateId]['proposed_end'];
                    }

                    $this->log($t->id, (int) $user->id, $mode === 'excluded' ? 'date_excluded' : 'date_reschedule', $payload);
                }

                $this->syncDateRangeFromSegments($t);

                TravelCost::where('travel_request_id', $t->id)->delete();

                $detectedType = $this->detectPlaceType($program);

                $byDateCfg = [];
                if (!$sameForAll) {
                    foreach ($allDateIds as $dateId) {
                        $mode = (string) ($decisions[$dateId]['mode'] ?? '');
                        if (!in_array($mode, ['selected', 'reschedule'], true)) continue;

                        $tRow = (string) ($byDate[$dateId]['transport'] ?? $transport);
                        if (!$hasMoto && $tRow === 'motorcycle') $tRow = 'bus';

                        $dirRow = $this->normalizeDirection((string) ($byDate[$dateId]['direction'] ?? $direction));

                        $suggestedRow = ($tRow === 'motorcycle')
                            ? 0.0
                            : (float) $this->getTransportRateFromProgram($program, $tRow);

                        $userRow = $this->moneyToFloat($byDate[$dateId]['unit_amount'] ?? 0);

                        $uRow = ($tRow === 'motorcycle')
                            ? 0.0
                            : (($userRow > 0) ? $userRow : max(0.0, $suggestedRow));

                        $byDateCfg[(string) $dateId] = [
                            'transport'   => $tRow,
                            'direction'   => $dirRow,
                            'unit_amount' => $uRow,
                        ];
                    }
                }

                TravelCost::create([
                    'travel_request_id' => $t->id,
                    'cost_type'         => 'transport',
                    'description'       => json_encode([
                        'same_for_all'        => $sameForAll,
                        'transport'           => $transport,
                        'direction'           => $direction,
                        'unit_amount'         => $unit,
                        'by_date'             => $byDateCfg,
                        'place_type_db'       => (string) ($program->place_type ?? 'municipio'),
                        'place_type_detected' => $detectedType,
                        'municipality_id'     => (int) ($program->municipality_id ?? 0),
                        'village_id'          => (int) ($program->village_id ?? 0),
                    ], JSON_UNESCAPED_UNICODE),
                    'amount'            => 0,
                    'applies_to'        => 'both',
                ]);

                $applied = $this->applyTransportCostToSegments($t);

                if ($usesMoto) {
                    $fuelUnit  = $this->moneyToFloat($fuel['unit_amount'] ?? 0);
                    $fuelUnits = (int) ($fuel['units'] ?? 1);
                    if ($fuelUnits < 1) $fuelUnits = 1;

                    if ($fuelUnit <= 0) {
                        throw new \RuntimeException('Si usas moto, debes registrar el viático de gasolina (valor unitario > 0).');
                    }

                    TravelAllowance::where('travel_request_id', $t->id)
                        ->where('allowance_type', 'fuel')
                        ->delete();

                    TravelAllowance::create([
                        'travel_request_id' => $t->id,
                        'allowance_type'    => 'fuel',
                        'status'            => 'draft',
                        'unit_amount'       => $fuelUnit,
                        'units'             => $fuelUnits,
                        'calculated_amount' => $fuelUnit * $fuelUnits,
                        'approved_amount'   => null,
                        'description'       => $fuel['description'] ?? 'Gasolina - Moto',
                        'applies_to'        => ((string)$t->person_type === 'contractor') ? 'contractor' : 'staff',
                        'budget_item_id'    => $t->budget_item_id,
                        'created_by'        => (int) $user->id,
                    ]);
                }

                if ((string) $t->person_type === 'staff') {
                    if ($allowEnabled) {
                        TravelAllowance::where('travel_request_id', $t->id)
                            ->whereIn('allowance_type', ['lodging', 'meals', 'per_diem', 'other'])
                            ->delete();

                        foreach ($allowRows as $row) {
                            $type = (string) ($row['allowance_type'] ?? '');
                            if (!in_array($type, ['lodging', 'meals', 'per_diem', 'other'], true)) continue;

                            $u = (float) ($row['unit_amount'] ?? 0);
                            $n = (int) ($row['units'] ?? 1);
                            if ($n < 1) $n = 1;
                            if ($u < 0) $u = 0;

                            if ($u <= 0 && trim((string) ($row['description'] ?? '')) === '') continue;

                            TravelAllowance::create([
                                'travel_request_id' => $t->id,
                                'allowance_type'    => $type,
                                'status'            => 'draft',
                                'unit_amount'       => $u,
                                'units'             => $n,
                                'calculated_amount' => $u * $n,
                                'approved_amount'   => null,
                                'description'       => $row['description'] ?? null,
                                'applies_to'        => 'staff',
                                'budget_item_id'    => $t->budget_item_id,
                                'created_by'        => (int) $user->id,
                            ]);
                        }
                    }
                }

                $this->recalcTotals($t);

                $this->log($t->id, (int) $user->id, 'create_all_saved', [
                    'segments'          => TravelSegment::where('travel_request_id', $t->id)->count(),
                    'costs'             => TravelCost::where('travel_request_id', $t->id)->count(),
                    'allowances'        => TravelAllowance::where('travel_request_id', $t->id)->count(),
                    'transport_applied' => $applied,
                    'uses_moto'         => $usesMoto,
                    'allow_enabled'     => $allowEnabled,
                ]);

                return $t;
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('gdf.instructor.sitrav.requests.edit', $travel->id)
            ->with('success', 'Solicitud SITRAV creada. Transporte aplicado por segmento y total sincronizado.');
    }

    public function storeDates(Request $request, int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int)$user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->with(['segments'])
            ->findOrFail($travelRequestId);

        if ($resp = $this->assertEditableByInstructor($travel)) return $resp;

        $program = ProgramRequest::query()
            ->with(['programRequestDates' => function ($q) {
                $q->whereNull('deleted_at')->orderBy('date')->orderBy('start_time');
            }])
            ->findOrFail((int)$travel->source_request_id);

        $dates = $program->programRequestDates ?? collect();
        if ($dates->count() <= 0) {
            return back()->with('error', 'El programa no tiene fechas en SIGAC.');
        }

        $allDateIds = $dates->pluck('id')->map(fn($v) => (int)$v)->values()->all();
        $decisions = (array)$request->input('decisions', []);

        foreach ($allDateIds as $dateId) {
            $mode = $decisions[$dateId]['mode'] ?? null;
            if (!in_array($mode, ['selected', 'excluded', 'reschedule'], true)) {
                return back()->withInput()->with('error', "Falta decisión para la fecha #{$dateId}.");
            }
            if (in_array($mode, ['excluded', 'reschedule'], true)) {
                $reason = trim((string)($decisions[$dateId]['reason'] ?? ''));
                if ($reason === '') {
                    return back()->withInput()->with('error', "Debes indicar el motivo en la fecha #{$dateId}.");
                }
            }
            if ($mode === 'reschedule') {
                $ps = trim((string)($decisions[$dateId]['proposed_start'] ?? ''));
                $pe = trim((string)($decisions[$dateId]['proposed_end'] ?? ''));
                if ($ps === '' || $pe === '') {
                    return back()->withInput()->with('error', "Si reprogramas, debes proponer inicio/fin para #{$dateId}.");
                }
            }
        }

        $activeCount = 0;
        foreach ($allDateIds as $dateId) {
            $m = (string)($decisions[$dateId]['mode'] ?? '');
            if ($m === 'selected' || $m === 'reschedule') $activeCount++;
        }
        if ($activeCount <= 0) {
            return back()->withInput()->with('error', 'Debes seleccionar o reprogramar al menos una fecha.');
        }

        // ✅ Trae config actual del transporte (source of truth para same_for_all + direction)
        $tc = TravelCost::where('travel_request_id', $travel->id)
            ->where('cost_type', 'transport')
            ->first();

        $desc = $tc ? $this->tryJson($tc->description) : [];
        $sameForAll = (bool)($desc['same_for_all'] ?? true);
        $direction  = $this->normalizeDirection((string)($desc['direction'] ?? 'one_way'));
        $globalTransport = (string)($desc['transport'] ?? 'bus');

        $hasMoto = $this->hasMotorcycle($personId, (int)($travel->area_id ?? 0));

        // ✅ Si no es same_for_all, aquí sí guardamos lo que viene de la tabla:
        $incomingByDate = (array)$request->input('costs_by_date', []);
        $byDateCfg = (array)($desc['by_date'] ?? []);

        if (!$sameForAll && $tc) {
            foreach ($allDateIds as $dateId) {
                $mode = (string)($decisions[$dateId]['mode'] ?? '');
                if (!in_array($mode, ['selected', 'reschedule'], true)) continue;

                $row = (array)($incomingByDate[$dateId] ?? []);

                $tRow = (string)($row['transport'] ?? $globalTransport);
                if (!$hasMoto && $tRow === 'motorcycle') $tRow = 'bus';

                $dirRow = $this->normalizeDirection((string)($row['direction'] ?? $direction));

                $userUnit = $this->moneyToFloat($row['unit_amount'] ?? 0);
                if ($tRow === 'motorcycle') $userUnit = 0.0;

                // Si el usuario deja 0 y no es moto, mantenemos lo ya guardado o sugerido
                $prev = (array)($byDateCfg[(string)$dateId] ?? []);
                $fallback = (float)($prev['unit_amount'] ?? 0);
                if ($userUnit <= 0 && $tRow !== 'motorcycle') {
                    $suggested = (float)$this->getTransportRateFromProgram($program, $tRow);
                    $userUnit = $fallback > 0 ? $fallback : max(0.0, $suggested);
                }

                $byDateCfg[(string)$dateId] = [
                    'transport'   => $tRow,
                    'direction'   => $dirRow,
                    'unit_amount' => (float)$userUnit,
                ];
            }

            $desc['by_date'] = $byDateCfg;
            $tc->description = json_encode($desc, JSON_UNESCAPED_UNICODE);
            $tc->save();
        }

        try {
            DB::transaction(function () use ($travel, $program, $dates, $decisions, $user, $tc, $sameForAll, $direction, $globalTransport, $byDateCfg) {

                // 1) borrar segmentos SIGAC previos
                $sigacSegIds = [];
                foreach (($travel->segments ?? []) as $seg) {
                    $meta = $this->tryJson($seg->notes);
                    if (($meta['source'] ?? null) === 'sigac' && !empty($meta['program_request_date_id'])) {
                        $sigacSegIds[] = (int)$seg->id;
                    }
                }
                if (!empty($sigacSegIds)) {
                    TravelSegment::whereIn('id', $sigacSegIds)->delete();
                }

                // 2) crear segmentos
                foreach ($dates as $d) {
                    $dateId = (int)$d->id;
                    $mode   = (string)($decisions[$dateId]['mode'] ?? '');

                    if ($mode !== 'selected' && $mode !== 'reschedule') continue;

                    if ($mode === 'selected') {
                        $departureAt = Carbon::parse($d->date . ' ' . $d->start_time)->format('Y-m-d H:i:s');
                        $returnAt    = Carbon::parse($d->date . ' ' . $d->end_time)->format('Y-m-d H:i:s');
                        $meta = ['source' => 'sigac', 'program_request_date_id' => $dateId];
                    } else {
                        $ps = (string)$decisions[$dateId]['proposed_start'];
                        $pe = (string)$decisions[$dateId]['proposed_end'];
                        $departureAt = Carbon::parse($ps)->format('Y-m-d H:i:s');
                        $returnAt    = Carbon::parse($pe)->format('Y-m-d H:i:s');
                        $meta = [
                            'source' => 'sigac',
                            'program_request_date_id' => $dateId,
                            'rescheduled_local' => true,
                            'original_date' => (string)$d->date,
                            'original_start' => (string)$d->start_time,
                            'original_end' => (string)$d->end_time,
                        ];
                    }

                    $transportForSegment = null;

                    if ($tc) {
                        if ($sameForAll) {
                            $transportForSegment = $globalTransport;
                        } else {
                            $cfg = (array)($byDateCfg[(string)$dateId] ?? []);
                            $transportForSegment = (string)($cfg['transport'] ?? null);
                        }
                    }

                    $seg = new TravelSegment();
                    $seg->travel_request_id = $travel->id;
                    $seg->departure_at = $departureAt;
                    $seg->return_at = $returnAt;

                    $seg->origin_place = $travel->origin;
                    $seg->destination_place = $travel->destination;

                    $this->fillSegmentGeoRatesTransport(
                        $seg,
                        $program,
                        (string)$travel->destination,
                        $transportForSegment,
                        $direction
                    );

                    $seg->is_cancelled = 0;

                    $seg->transport_cost = 0;
                    $seg->per_diem_cost  = 0;
                    $seg->other_cost     = 0;
                    $seg->total_cost     = 0;

                    $seg->notes = json_encode($meta, JSON_UNESCAPED_UNICODE);
                    $seg->save();
                }

                // 3) logs
                foreach ($dates as $d) {
                    $dateId = (int)$d->id;
                    $mode   = (string)($decisions[$dateId]['mode'] ?? '');
                    if ($mode === 'selected') continue;

                    $payload = [
                        'program_request_date_id' => $dateId,
                        'reason' => trim((string)($decisions[$dateId]['reason'] ?? '')),
                    ];
                    if ($mode === 'reschedule') {
                        $payload['proposed_start'] = (string)$decisions[$dateId]['proposed_start'];
                        $payload['proposed_end']   = (string)$decisions[$dateId]['proposed_end'];
                    }
                    $this->log($travel->id, (int)$user->id, $mode === 'excluded' ? 'date_excluded' : 'date_reschedule', $payload);
                }

                // 4) rango real
                $this->syncDateRangeFromSegments($travel);

                // ✅ 5) Reaplicar transporte (esto ya toma by_date del TravelCost actualizado)
                $applied = $this->applyTransportCostToSegments($travel);

                // 6) totales
                $this->recalcTotals($travel);

                $this->log($travel->id, (int)$user->id, 'dates_saved', [
                    'segments_now' => TravelSegment::where('travel_request_id', $travel->id)->count(),
                    'transport_applied' => $applied,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'No se pudieron guardar fechas: ' . $e->getMessage());
        }

        return redirect()
            ->route('gdf.instructor.sitrav.requests.edit', $travel->id)
            ->with('success', 'Fechas guardadas. Transporte aplicado por segmento y total sincronizado.');
    }


    public function edit(int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int) $user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->with(['segments', 'costs', 'allowances'])
            ->findOrFail($travelRequestId);

        // ✅ traer relaciones para nombre de área/rubro (si existen)
        $program = ProgramRequest::query()
            ->with([
                'programRequestDates' => function ($q) {
                    $q->whereNull('deleted_at')->orderBy('date')->orderBy('start_time');
                },
                'area',
                'budgetItem',
            ])
            ->findOrFail((int) $travel->source_request_id);

        // ✅ Nombres (si relación existe)
        $areaName = null;
        if ($program->relationLoaded('area') && $program->area) {
            $areaName = $program->area->name ?? $program->area->nombre ?? $program->area->name_area ?? null;
        }
        $budgetItemName = null;
        if ($program->relationLoaded('budgetItem') && $program->budgetItem) {
            $budgetItemName = $program->budgetItem->name ?? $program->budgetItem->nombre ?? $program->budgetItem->name_budget_item ?? null;
        }

        $hasMoto = $this->hasMotorcycle($personId, (int) $travel->area_id);
        $transportOptions = $this->buildTransportOptions($program, $hasMoto);

        $ratesByTransport = [
            'bus'        => (float) $this->getTransportRateFromProgram($program, 'bus'),
            'motorcycle' => 0.0,
        ];

        // lee config actual guardada (transport.description) robusto
        $tc = ($travel->costs ?? collect())->firstWhere('cost_type', 'transport');
        $transportMeta = [];
        if ($tc) {
            if (is_array($tc->description)) {
                $transportMeta = $tc->description;
            } elseif (is_string($tc->description) && trim($tc->description) !== '') {
                $tmp = json_decode($tc->description, true);
                if (is_array($tmp)) $transportMeta = $tmp;
            }
        }

        $savedTransport = (string) ($transportMeta['transport'] ?? '');

        // ✅ direction/trip_type desde segmentos (BD real)
        $firstSegment = $travel->segments->where('is_cancelled', 0)->first();
        $savedDirection = 'one_way';
        if ($firstSegment) {
            $t = (string) ($firstSegment->trip_type ?? 'one_way');
            $savedDirection = in_array($t, ['one_way', 'two_way', 'round_trip'], true) ? $t : 'one_way';
        } else {
            // fallback meta
            $t = (string) ($transportMeta['direction'] ?? 'one_way');
            // normalizar legacy
            if ($t === 'return') $t = 'two_way';
            $savedDirection = in_array($t, ['one_way', 'two_way', 'round_trip'], true) ? $t : 'one_way';
        }

        $savedUnit = (float) ($transportMeta['unit_amount'] ?? 0);

        $defaultTransport = ($savedTransport !== '' && array_key_exists($savedTransport, $transportOptions))
            ? $savedTransport
            : (array_key_exists('motorcycle', $transportOptions) ? 'motorcycle' : array_key_first($transportOptions));

        $defaultDirection = $savedDirection ?: 'one_way';
        $baseRate = (float) ($ratesByTransport[$defaultTransport] ?? 0);
        $defaultUnit = $savedUnit > 0 ? $savedUnit : $baseRate;

        $selectedMap = $this->selectedMapFromSegments($travel);

        $decisionLogs = TravelLog::query()
            ->where('travel_request_id', $travel->id)
            ->whereIn('action', ['date_excluded', 'date_reschedule'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $lastLogByDateId = [];
        foreach ($decisionLogs as $log) {
            $payload = $this->tryJson($log->description);
            $dateId = (int) ($payload['program_request_date_id'] ?? 0);
            if ($dateId > 0 && !isset($lastLogByDateId[$dateId])) {
                $lastLogByDateId[$dateId] = ['action' => $log->action, 'payload' => $payload];
            }
        }

        $isStaff   = ((string) $travel->person_type === 'staff');
        $isVillage = $this->detectPlaceType($program) === 'vereda';
        $canEdit   = in_array((string) $travel->status, ['draft', 'returned'], true);

        $segmentByDateId = collect($travel->segments ?? [])
            ->where('is_cancelled', 0)
            ->keyBy(function ($s) {
                $meta = $this->tryJson($s->notes);
                return (int) ($meta['program_request_date_id'] ?? 0);
            });

        // ✅ Totales BD
        $transportCostRow = ($travel->costs ?? collect())->firstWhere('cost_type', 'transport');
        $transportTotalBD = (float) ($transportCostRow->amount ?? 0);

        $fuelAllowance = ($travel->allowances ?? collect())->firstWhere('allowance_type', 'fuel');
        $fuelTotalBD = $fuelAllowance ? ((float) $fuelAllowance->unit_amount * (int) $fuelAllowance->units) : 0;

        // ✅ alerta mezcla de trip_type (opcional)
        $tripTypes = collect($travel->segments ?? [])
            ->where('is_cancelled', 0)
            ->pluck('trip_type')
            ->filter()
            ->unique()
            ->values();

        $tripMixed = $tripTypes->count() > 1;
        $tripTypesList = $tripTypes->all();

        return view('gdf::official.SITRAV.edit', compact(
            'travel',
            'program',
            'selectedMap',
            'decisionLogs',
            'lastLogByDateId',
            'hasMoto',
            'transportOptions',
            'defaultTransport',
            'baseRate',
            'defaultDirection',
            'defaultUnit',
            'ratesByTransport',
            'transportMeta',
            'isStaff',
            'isVillage',
            'canEdit',
            'segmentByDateId',
            'transportTotalBD',
            'fuelTotalBD',
            'areaName',
            'budgetItemName',
            'tripMixed',
            'tripTypesList'
        ));
    }




    public function autosaveCosts(Request $request, int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) {
            return response()->json(['success' => false, 'message' => 'No autenticado'], 403);
        }

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) {
                return response()->json(['success' => false, 'message' => 'No autorizado'], 403);
            }
        }

        $personId = (int)$user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->find($travelRequestId);

        if (!$travel) {
            return response()->json(['success' => false, 'message' => 'Solicitud no encontrada'], 404);
        }

        if ($resp = $this->assertEditableByInstructor($travel, true)) return $resp;

        try {
            DB::transaction(function () use ($travel, $request, $user) {

                // Guardamos SOLO el borrador de config de transporte (1 item)
                $draft = (array)$request->input('costs', []);
                $notes = $this->tryJson($travel->notes);

                $notes['costs_draft'] = $draft;
                $notes['costs_draft_updated_at'] = now()->toDateTimeString();

                $travel->notes = json_encode($notes, JSON_UNESCAPED_UNICODE);
                $travel->save();

                $this->log($travel->id, (int)$user->id, 'costs_autosaved', [
                    'count' => count($draft),
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Costos guardados automáticamente',
                'timestamp' => now()->format('H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function deleteFuel(Request $request, int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int)$user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->with(['allowances', 'segments', 'costs'])
            ->findOrFail($travelRequestId);

        if ($resp = $this->assertEditableByInstructor($travel)) return $resp;

        DB::transaction(function () use ($travel, $user) {
            TravelAllowance::where('travel_request_id', $travel->id)
                ->where('allowance_type', 'fuel')
                ->delete();

            // Recalcular totales (tu total_per_diem suma allowances)
            $this->recalcTotals($travel);

            $this->log($travel->id, (int)$user->id, 'fuel_deleted', [
                'allowance_type' => 'fuel'
            ]);
        });

        return redirect()
            ->route('gdf.instructor.sitrav.requests.edit', $travel->id)
            ->with('success', 'Viático de gasolina eliminado.');
    }


    public function storeCosts(Request $request, int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int)$user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->findOrFail($travelRequestId);

        if ($resp = $this->assertEditableByInstructor($travel)) return $resp;

        $program = ProgramRequest::findOrFail((int)$travel->source_request_id);

        $hasMoto = $this->hasMotorcycle($personId, (int)$travel->area_id);

        $rules = [
            'costs' => ['required', 'array', 'size:1'],
            'costs.0.cost_type'   => ['required', Rule::in(['transport'])],
            'costs.0.transport'   => ['required', Rule::in(['bus', 'motorcycle'])],
            'costs.0.direction'   => ['required', Rule::in(['one_way', 'two_way', 'round_trip'])],
            'costs.0.unit_amount' => ['required', 'numeric', 'min:0'],
        ];

        $v = Validator::make($request->all(), $rules);
        if ($v->fails()) return back()->withErrors($v)->withInput();

        DB::transaction(function () use ($travel, $request, $program, $hasMoto, $user) {

            $it = (array)$request->input('costs.0', []);

            $transport = (string)($it['transport'] ?? 'bus');
            if (!$hasMoto && $transport === 'motorcycle') $transport = 'bus';

            $direction = $this->normalizeDirection((string)($it['direction'] ?? 'one_way'));

            $unit = (float)($it['unit_amount'] ?? 0);
            if ($unit < 0) $unit = 0;

            if ($transport === 'motorcycle') $unit = 0.0;

            $detectedType = $this->detectPlaceType($program);

            $payload = [
                'same_for_all'        => true,
                'transport'           => $transport,
                'direction'           => $direction,
                'unit_amount'         => $unit,
                'by_date'             => [],
                'place_type_db'       => (string)($program->place_type ?? 'municipio'),
                'place_type_detected' => $detectedType,
                'municipality_id'     => (int)($program->municipality_id ?? 0),
                'village_id'          => (int)($program->village_id ?? 0),
            ];

            TravelCost::updateOrCreate(
                ['travel_request_id' => $travel->id, 'cost_type' => 'transport'],
                [
                    'description' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    'amount'      => 0,
                    'applies_to'  => 'both',
                ]
            );

            $this->upsertRateFromProgramAndCost($program, $transport, $unit);

            $applied = $this->applyTransportCostToSegments($travel);

            $usesMoto = ($transport === 'motorcycle');
            $appliesTo = ((string)$travel->person_type === 'staff') ? 'staff' : 'contractor';

            if ($usesMoto) {
                $fuelRules = [
                    'fuel.unit_amount' => ['required', 'numeric', 'min:0.01'],
                    'fuel.units'       => ['required', 'integer', 'min:1'],
                    'fuel.description' => ['nullable', 'string', 'max:255'],
                ];
                $fuelV = Validator::make($request->all(), $fuelRules);
                if ($fuelV->fails()) {
                    throw new \Illuminate\Validation\ValidationException($fuelV);
                }

                $fu = (float)$request->input('fuel.unit_amount');
                $fn = (int)$request->input('fuel.units');
                $fd = trim((string)$request->input('fuel.description', 'Gasolina - Moto'));
                if ($fd === '') $fd = 'Gasolina - Moto';

                TravelAllowance::updateOrCreate(
                    ['travel_request_id' => $travel->id, 'allowance_type' => 'fuel'],
                    [
                        'status'            => 'draft',
                        'unit_amount'       => $fu,
                        'units'             => $fn,
                        'calculated_amount' => $fu * $fn,
                        'approved_amount'   => null,
                        'description'       => $fd,
                        'applies_to'        => $appliesTo,
                        'budget_item_id'    => $travel->budget_item_id,
                        'created_by'        => (int)$user->id,
                    ]
                );
            } else {
                TravelAllowance::where('travel_request_id', $travel->id)
                    ->where('allowance_type', 'fuel')
                    ->delete();
            }

            $notes = $this->tryJson($travel->notes);
            unset($notes['costs_draft'], $notes['costs_draft_updated_at']);
            $travel->notes = json_encode($notes, JSON_UNESCAPED_UNICODE);
            $travel->save();

            $this->recalcTotals($travel);

            $this->log($travel->id, (int)$user->id, 'costs_saved', [
                'transport'         => $transport,
                'direction'         => $direction,
                'unit_amount'       => $unit,
                'transport_applied' => $applied,
                'rates_upserted'    => true,
                'place_type'        => $detectedType,
                'fuel_upserted'     => $usesMoto,
            ]);
        });

        return redirect()
            ->route('gdf.instructor.sitrav.requests.edit', $travel->id)
            ->with('success', 'Transporte guardado. (Si es moto, gasolina actualizada).');
    }



    private function applyTransportCostToSegments(TravelRequest $travel): array
    {
        $tc = TravelCost::where('travel_request_id', $travel->id)
            ->where('cost_type', 'transport')
            ->first();

        if (!$tc) {
            TravelSegment::where('travel_request_id', $travel->id)
                ->where('is_cancelled', 0)
                ->update([
                    'transport_cost' => 0,
                    'total_cost'     => DB::raw('IFNULL(per_diem_cost,0)+IFNULL(other_cost,0)'),
                ]);

            return ['applied' => false, 'reason' => 'no_transport_cost_row'];
        }

        $desc = $this->tryJson($tc->description);
        $sameForAll = (bool)($desc['same_for_all'] ?? true);

        $sum = 0.0;

        // ============================================================
        // MODO GLOBAL (same_for_all)
        // ============================================================
        if ($sameForAll) {
            $transport = (string)($desc['transport'] ?? 'bus');
            $unit = (float)($desc['unit_amount'] ?? 0);
            if ($unit < 0) $unit = 0;
            if ($transport === 'motorcycle') $unit = 0;

            // ✅ dirección unificada a: one_way|two_way|round_trip
            $direction = $this->normalizeDirection((string)($desc['direction'] ?? 'one_way'));
            $meta = $this->directionToTripMeta($direction);

            $perSegment = $unit * (int)$meta['mult'];
            $segTransport = $this->mapTransportToSegmentEnum($transport);

            $update = [
                'transport_cost' => (float)$perSegment,
                'trip_type'      => (string)$meta['trip_type'], // one_way|two_way|round_trip
                'trips'          => (int)$meta['trips'],        // 1|2
                'total_cost'     => DB::raw('IFNULL(per_diem_cost,0)+IFNULL(other_cost,0)+' . (float)$perSegment),
            ];
            if ($segTransport) $update['transport_type'] = $segTransport;

            TravelSegment::where('travel_request_id', $travel->id)
                ->where('is_cancelled', 0)
                ->update($update);

            $sum = (float) TravelSegment::where('travel_request_id', $travel->id)
                ->where('is_cancelled', 0)
                ->sum('transport_cost');

            // guarda meta coherente
            $desc['direction']   = $direction;
            $desc['multiplier']  = (int)$meta['mult'];
            $desc['per_segment'] = (float)$perSegment;
            $desc['total']       = (float)$sum;

            $tc->description = json_encode($desc, JSON_UNESCAPED_UNICODE);
            $tc->amount      = (float)$sum;
            $tc->save();

            return [
                'applied'      => true,
                'mode'         => 'same_for_all',
                'transport'    => $transport,
                'unit_amount'  => (float)$unit,
                'direction'    => $direction,
                'trip_type'    => (string)$meta['trip_type'],
                'multiplier'   => (int)$meta['mult'],
                'per_segment'  => (float)$perSegment,
                'sum'          => (float)$sum,
            ];
        }

        // ============================================================
        // MODO POR FECHA (by_date)
        // ============================================================
        $byDate = (array)($desc['by_date'] ?? []);

        $segments = TravelSegment::where('travel_request_id', $travel->id)
            ->where('is_cancelled', 0)
            ->get();

        foreach ($segments as $seg) {
            $metaNotes = $this->tryJson($seg->notes);
            $dateId = (string)($metaNotes['program_request_date_id'] ?? '');

            $cfg = $dateId !== '' ? (array)($byDate[$dateId] ?? []) : [];

            $transport = (string)($cfg['transport'] ?? 'bus');

            // ✅ dirección unificada
            $direction = $this->normalizeDirection((string)($cfg['direction'] ?? 'one_way'));
            $tripMeta  = $this->directionToTripMeta($direction);

            $unit = (float)($cfg['unit_amount'] ?? 0);
            if ($unit < 0) $unit = 0;
            if ($transport === 'motorcycle') $unit = 0;

            $per = $unit * (int)$tripMeta['mult'];

            $seg->trip_type = (string)$tripMeta['trip_type'];
            $seg->trips     = (int)$tripMeta['trips'];

            $segTransport = $this->mapTransportToSegmentEnum($transport);
            if ($segTransport) $seg->transport_type = $segTransport;

            $seg->transport_cost = (float)$per;
            $seg->total_cost = (float)($seg->per_diem_cost ?? 0) + (float)($seg->other_cost ?? 0) + (float)$per;
            $seg->save();

            $sum += (float)$per;
        }

        $desc['total'] = (float)$sum;
        $tc->description = json_encode($desc, JSON_UNESCAPED_UNICODE);
        $tc->amount = (float)$sum;
        $tc->save();

        return [
            'applied'   => true,
            'mode'      => 'by_date',
            'segments'  => (int)$segments->count(),
            'sum'       => (float)$sum
        ];
    }




    public function deleteAllowances(int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int) $user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->findOrFail($travelRequestId);

        if ($resp = $this->assertEditableByInstructor($travel)) return $resp;

        if ((string)$travel->person_type !== 'staff') {
            return back()->with('error', 'Los viáticos solo aplican para personal de planta.');
        }

        DB::transaction(function () use ($travel, $user) {

            // ✅ NO borrar fuel
            TravelAllowance::where('travel_request_id', $travel->id)
                ->where('allowance_type', '!=', 'fuel')
                ->delete();

            $this->recalcTotals($travel);

            $this->log($travel->id, (int)$user->id, 'allowances_deleted', [
                'travel_request_id' => (int)$travel->id,
                'kept' => ['fuel'],
            ]);
        });

        return back()->with('success', 'Viáticos eliminados (se conserva gasolina si existe).');
    }



    public function storeAllowances(Request $request, int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int) $user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->with(['allowances'])
            ->findOrFail($travelRequestId);

        if ($resp = $this->assertEditableByInstructor($travel)) return $resp;

        // ✅ Solo staff
        if ((string) $travel->person_type !== 'staff') {
            return back()->with('error', 'Los viáticos solo aplican para personal de planta.');
        }

        $deleteIds = array_map('intval', (array) $request->input('delete_ids', []));
        $rawRows   = (array) $request->input('allowances', []);

        // Normaliza filas (permitimos múltiples del mismo tipo)
        $rows = [];
        foreach ($rawRows as $r) {
            $id    = isset($r['id']) ? (int) $r['id'] : 0;
            $type  = isset($r['allowance_type']) ? (string) $r['allowance_type'] : '';
            $unit  = $r['unit_amount'] ?? null;
            $units = $r['units'] ?? 1;
            $desc  = isset($r['description']) ? trim((string) $r['description']) : null;

            // fila vacía => ignora
            if ($type === '' && ($unit === null || $unit === '') && ($desc === null || $desc === '')) {
                continue;
            }

            $rows[] = [
                'id'            => $id,
                'allowance_type' => $type,
                'unit_amount'   => $unit,
                'units'         => $units,
                'description'   => ($desc !== '' ? $desc : null),
            ];
        }

        $rules = [
            'allowances' => ['array'],
            'allowances.*.id'            => ['nullable', 'integer', 'min:0'],
            'allowances.*.allowance_type' => ['required', Rule::in(['lodging', 'meals', 'per_diem', 'other'])], // fuel NO aquí
            'allowances.*.unit_amount'   => ['required', 'numeric', 'min:0'],
            'allowances.*.units'         => ['required', 'integer', 'min:1'],
            'allowances.*.description'   => ['nullable', 'string', 'max:255'],

            'delete_ids'   => ['array'],
            'delete_ids.*' => ['integer', 'min:1'],
        ];

        $v = Validator::make(['allowances' => $rows, 'delete_ids' => $deleteIds], $rules);
        if ($v->fails()) return back()->withErrors($v)->withInput();

        DB::transaction(function () use ($travel, $rows, $deleteIds, $user) {

            // 1) borrar puntuales (NO fuel)
            if (!empty($deleteIds)) {
                TravelAllowance::where('travel_request_id', $travel->id)
                    ->whereIn('id', $deleteIds)
                    ->where('allowance_type', '!=', 'fuel')
                    ->delete();
            }

            // 2) upsert filas
            foreach ($rows as $row) {
                $id   = (int) ($row['id'] ?? 0);
                $type = (string) $row['allowance_type'];

                $unit  = (float) $row['unit_amount'];
                $units = (int) $row['units'];
                if ($units < 1) $units = 1;

                $calc = $unit * $units;

                if ($id > 0) {
                    // update SOLO si pertenece a este travel y no es fuel
                    TravelAllowance::where('travel_request_id', $travel->id)
                        ->where('id', $id)
                        ->where('allowance_type', '!=', 'fuel')
                        ->update([
                            'allowance_type'    => $type,
                            'status'            => 'draft',
                            'unit_amount'       => $unit,
                            'units'             => $units,
                            'calculated_amount' => $calc,
                            'approved_amount'   => null,
                            'description'       => $row['description'] ?? null,
                            'applies_to'        => 'staff',
                            'budget_item_id'    => $travel->budget_item_id,
                            'created_by'        => (int) $user->id,
                            'updated_at'        => now(),
                        ]);
                } else {
                    // create
                    TravelAllowance::create([
                        'travel_request_id' => $travel->id,
                        'allowance_type'    => $type,
                        'status'            => 'draft',
                        'unit_amount'       => $unit,
                        'units'             => $units,
                        'calculated_amount' => $calc,
                        'approved_amount'   => null,
                        'description'       => $row['description'] ?? null,
                        'applies_to'        => 'staff',
                        'budget_item_id'    => $travel->budget_item_id,
                        'created_by'        => (int) $user->id,
                    ]);
                }
            }

            $this->recalcTotals($travel);

            $this->log($travel->id, (int) $user->id, 'allowances_saved', [
                'upserted' => count($rows),
                'deleted'  => count($deleteIds),
            ]);
        });

        return redirect()
            ->route('gdf.instructor.sitrav.requests.edit', $travel->id)
            ->with('success', 'Viáticos actualizados.');
    }


    public function submit(int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int)$user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->findOrFail($travelRequestId);

        if ($resp = $this->assertEditableByInstructor($travel)) return $resp;

        // ✅ Debe tener segmentos activos
        $segmentsCount = (int) TravelSegment::where('travel_request_id', $travel->id)
            ->where('is_cancelled', 0)
            ->count();

        if ($segmentsCount <= 0) {
            return back()->with('error', 'Debes seleccionar al menos una fecha para poder enviar.');
        }

        // ✅ Re-aplica por seguridad
        $sync = $this->applyTransportCostToSegments($travel);

        // ✅ Debe existir costo de transporte
        $tc = TravelCost::where('travel_request_id', $travel->id)
            ->where('cost_type', 'transport')
            ->first();

        if (!$tc) {
            return back()->with('error', 'Debes registrar el transporte.');
        }

        // ✅ Determinar transporte real (same_for_all vs by_date)
        $desc = $this->tryJson($tc->description);
        $sameForAll = (bool)($desc['same_for_all'] ?? true);
        $transport  = (string)($desc['transport'] ?? 'bus');

        if (!$sameForAll) {
            $byDate = (array)($desc['by_date'] ?? []);
            foreach ($byDate as $cfg) {
                if (($cfg['transport'] ?? null) === 'motorcycle') {
                    $transport = 'motorcycle';
                    break;
                }
            }
        }

        // ✅ Validación de valor mínimo
        $tcAmount = (float)($tc->amount ?? 0);

        if ($transport === 'motorcycle') {
            $fuelAmt = (float) TravelAllowance::where('travel_request_id', $travel->id)
                ->where('allowance_type', 'fuel')
                ->whereIn('status', ['draft', 'liquidated', 'approved'])
                ->value('calculated_amount');

            if ($fuelAmt <= 0) {
                return back()->with('error', 'Si usas moto, debes registrar la gasolina (fuel) con valor mayor a 0.');
            }
        } else {
            if ($tcAmount <= 0) {
                return back()->with('error', 'El transporte debe tener un valor mayor a 0.');
            }
        }

        // ✅ Datos SIGAC para actualizar state
        $programId = ((string)($travel->source ?? '') === 'sigac') ? (int)($travel->source_request_id ?? 0) : 0;

        try {
            DB::transaction(function () use ($travel, $user, $sync, $programId) {

                // 1) seguridad: recalcular costos+totales antes de enviar
                $this->applyTransportCostToSegments($travel);
                $this->recalcTotals($travel);

                // 2) Enviar a Apoyo (cambia estado en travel_requests)
                $travel->status = 'submitted';     // ✅ "enviado a apoyo"
                $travel->submitted_at = now();     // ✅ si existe campo; si no existe, quítalo
                $travel->save();

                $this->log($travel->id, (int)$user->id, 'submitted', [
                    'total_amount'    => (float)$travel->total_amount,
                    'transport_sync'  => $sync,
                    'program_request_id' => $programId,
                ]);

                // 3) ✅ Cambiar state del ProgramRequest a Agendado (SIGAC)
                if ($programId > 0) {
                    ProgramRequest::query()
                        ->where('id', $programId)
                        ->whereIn('state', ['Confirmado', 'Preconfirmado']) // no pisar otros estados
                        ->update([
                            'state'      => 'Agendado',
                            'updated_at' => now(),
                        ]);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo enviar: ' . $e->getMessage());
        }

        return redirect()
            ->route('gdf.instructor.sitrav.requests.show', $travel->id)
            ->with('success', 'Solicitud SITRAV enviada a Apoyo y el programa quedó en estado AGENDADO.');
    }


    public function show(int $travelRequestId)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);

        if (function_exists('checkRol')) {
            if (!checkRol('gdf.instructor') && !checkRol('gdf.superadmin') && !checkRol('superadmin')) abort(403);
        }

        $personId = (int) $user->person->id;

        $travel = TravelRequest::query()
            ->where('module', 'sitrav')
            ->where('person_id', $personId)
            ->with(['segments', 'costs', 'allowances'])
            ->findOrFail($travelRequestId);

        if (in_array((string)$travel->status, ['draft', 'returned'], true)) {
            return redirect()->route('gdf.instructor.sitrav.requests.edit', ['travelRequestId' => $travel->id]);
        }

        $program = ProgramRequest::query()
            ->with(['area', 'budgetItem'])
            ->find((int) $travel->source_request_id);

        $authRelPath = "gdf/requests/{$travel->id}/autorizacion_{$travel->id}.pdf";
        $authUrl = Storage::disk('public')->exists($authRelPath)
            ? Storage::url($authRelPath)
            : null;

 
        $sigacDocs = collect();

        if ($program) {
            
            $sigacDocs = collect(DB::table('program_request_documents')
                ->where('program_request_id', $program->id)
                ->orderByDesc('id')
                ->get());
        }

        $docs = [];

        // docs SIGAC
        foreach ($sigacDocs as $d) {
            $path = (string)($d->path ?? $d->file_path ?? $d->url ?? '');
            // Si en BD guardas ruta relativa a "public/", perfecto.
            // Si guardas ruta absoluta, conviértela a relativa:
            $path = str_replace('\\', '/', $path);
            $path = preg_replace('#^.*storage/app/public/#', '', $path); // deja relativo

            if ($path === '' || !Storage::disk('public')->exists($path)) continue;

            $name = (string)($d->original_name ?? $d->name ?? basename($path));
            $url  = Storage::url($path);

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
            $isPdf   = ($ext === 'pdf');

            $docs[] = [
                'source'   => 'sigac',
                'name'     => $name,
                'path'     => $path,
                'url'      => $url,
                'ext'      => $ext,
                'is_image' => $isImage,
                'is_pdf'   => $isPdf,
            ];
        }

        // auth GDF
        if ($authUrl) {
            $docs[] = [
                'source'   => 'gdf',
                'name'     => "Autorización #{$travel->id}",
                'path'     => $authRelPath,
                'url'      => $authUrl,
                'ext'      => 'pdf',
                'is_image' => false,
                'is_pdf'   => true,
            ];
        }

        $transport = 0.0;
        $perDiem   = 0.0;
        $other     = 0.0;

        foreach (($travel->costs ?? collect()) as $c) {
            $amt = (float)($c->amount ?? 0);
            if ((string)$c->cost_type === 'transport') $transport += $amt;
            else $other += $amt;
        }

        foreach (($travel->allowances ?? collect()) as $a) {
            $amt = $a->approved_amount !== null ? (float)$a->approved_amount : (float)($a->calculated_amount ?? 0);

            if ((string)$a->allowance_type === 'fuel') $transport += $amt; // ✅ fuel => transporte
            elseif (in_array((string)$a->allowance_type, ['lodging', 'meals', 'per_diem'], true)) $perDiem += $amt;
            else $other += $amt;
        }

        $total = $transport + $perDiem + $other;

        return view('gdf::official.SITRAV.show', compact(
            'travel',
            'program',
            'docs',
            'transport',
            'perDiem',
            'other',
            'total'
        ));
    }


    private function assertEditableByInstructor(?TravelRequest $travel, bool $json = false)
    { /* ... */
    }

    private function resolvePersonType(int $personId): array
    {
        $employeeId = DB::table('employees')->where('person_id', $personId)->value('id');
        if ($employeeId) return ['staff', (int)$employeeId, null];

        $contractorId = DB::table('contractors')->where('person_id', $personId)->value('id');
        if ($contractorId) return ['contractor', null, (int)$contractorId];

        return ['staff', null, null];
    }

    private function selectedMapFromSegments(TravelRequest $travel): array
    {
        $map = [];
        foreach (($travel->segments ?? []) as $seg) {
            $meta = $this->tryJson($seg->notes);
            if (($meta['source'] ?? null) === 'sigac' && !empty($meta['program_request_date_id'])) {
                $map[(int)$meta['program_request_date_id']] = true;
            }
        }
        return $map;
    }

    private function tryJson($value): array
    {
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function log(int $travelRequestId, int $userId, string $action, array $payload): void
    {
        $l = new TravelLog();
        $l->travel_request_id = $travelRequestId;
        $l->user_id = $userId;
        $l->action = $action;
        $l->description = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $l->save();
    }

    private function recalcTotals(TravelRequest $travel): void
    {
        $transport = 0.0;
        $perDiem   = 0.0;
        $other     = 0.0;

        // travel_costs (resumen)
        $costs = TravelCost::where('travel_request_id', $travel->id)->get();
        foreach ($costs as $c) {
            $amt = (float) $c->amount;

            if ($c->cost_type === 'transport') {
                $transport += $amt;
                continue;
            }

            // si en algún momento guardas otros costos como travel_costs:
            if (in_array($c->cost_type, ['meals', 'lodging', 'per_diem'], true)) {
                $perDiem += $amt;
                continue;
            }

            $other += $amt;
        }

        // travel_allowances (viáticos)
        $allowances = TravelAllowance::where('travel_request_id', $travel->id)->get();
        foreach ($allowances as $a) {
            $amt = $a->approved_amount !== null ? (float) $a->approved_amount : (float) $a->calculated_amount;

            // ✅ fuel es TRANSPORTE, no per_diem
            if ($a->allowance_type === 'fuel') {
                $transport += $amt;
                continue;
            }

            // viáticos normales
            if (in_array($a->allowance_type, ['lodging', 'meals', 'per_diem'], true)) {
                $perDiem += $amt;
                continue;
            }

            $other += $amt;
        }

        $travel->total_transport = $transport;
        $travel->total_per_diem  = $perDiem;
        $travel->total_other     = $other;
        $travel->total_amount    = $transport + $perDiem + $other;
        $travel->save();
    }
    private function normalizeTripType(string $trip): string
    {
        return $this->normalizeDirection($trip);
    }


    private function tripMeta(string $trip): array
    {
        $trip = $this->normalizeDirection($trip);

        return match ($trip) {
            'round_trip' => ['trip_type' => 'round_trip', 'trips' => 2, 'mult' => 2],
            'two_way'    => ['trip_type' => 'two_way',    'trips' => 1, 'mult' => 1],
            default      => ['trip_type' => 'one_way',    'trips' => 1, 'mult' => 1],
        };
    }




    private function hasMotorcycle(int $personId, int $areaId): bool
    {
        return DB::table('motorcycle_assignments')
            ->where('person_id', $personId)
            ->where('area_id', $areaId)
            ->where('status', 'approved')
            ->whereNull('returned_at')
            ->exists();
    }

    private function detectPlaceType(ProgramRequest $program): string
    {
        $villageId = (int)($program->village_id ?? 0);
        return $villageId > 0 ? 'vereda' : 'municipio';
    }

    private function getTransportRateFromProgram(ProgramRequest $program, string $transportKey): float
    {
        $placeType = $this->detectPlaceType($program);

        $col = match ($transportKey) {
            'bus'        => 'bus_amount',
            'van'        => 'van_amount',
            'motorcycle' => 'motorcycle_amount',
            default      => 'bus_amount',
        };

        if ($placeType === 'vereda') {
            $villageId = (int)($program->village_id ?? 0);
            $munId     = (int)($program->municipality_id ?? 0);

            $q = DB::table('village_rates')->where('active', 1);
            if ($villageId > 0) $q->where('village_id', $villageId);
            elseif ($munId > 0) $q->where('municipality_id', $munId);
            else return 0.0;

            return (float)($q->value($col) ?? 0);
        }

        $munId = (int)($program->municipality_id ?? 0);
        if ($munId <= 0) return 0.0;

        return (float)(DB::table('municipality_rates')
            ->where('active', 1)
            ->where('municipality_id', $munId)
            ->value($col) ?? 0);
    }

    private function moneyToFloat($value): float
    {
        if (is_numeric($value)) return (float)$value;

        $s = trim((string)$value);
        if ($s === '') return 0.0;

        $s = preg_replace('/[^\d\.,-]/', '', $s);

        if (str_contains($s, '.') && !str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            return (float)$s;
        }

        if (str_contains($s, '.') && str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
            return (float)$s;
        }

        if (str_contains($s, ',')) $s = str_replace(',', '.', $s);
        return (float)$s;
    }

    private function buildTransportOptions(ProgramRequest $program, bool $hasMoto): array
    {
        $opts = ['bus' => 'Transporte público', 'van' => 'Camioneta'];
        if ($hasMoto) $opts = ['motorcycle' => 'Moto'] + $opts;
        return $opts;
    }

    private function buildDestinationFromProgram(ProgramRequest $program): string
    {
        $placeType = $this->detectPlaceType($program);
        $villageId = (int)($program->village_id ?? 0);
        $munId     = (int)($program->municipality_id ?? 0);

        $munName = $munId > 0 ? DB::table('municipalities')->where('id', $munId)->value('name') : null;

        if ($placeType === 'vereda' && $villageId > 0) {
            $villName = DB::table('villages')->where('id', $villageId)->value('name');
            if ($villName && $munName) return "{$villName} - {$munName}";
            if ($villName) return $villName;
        }

        return $munName ?: 'Municipio';
    }

    private function upsertRateFromProgramAndCost(ProgramRequest $program, string $transport, float $unitAmount): void
    {
        if ($transport === 'motorcycle') return;

        $unitAmount = (float)$unitAmount;
        if ($unitAmount <= 0) return;

        $col = match ($transport) {
            'bus'        => 'bus_amount',
            'van'        => 'van_amount',
            default      => null,
        };
        if (!$col) return;

        $placeType = $this->detectPlaceType($program); // vereda|municipio
        $munId = (int)($program->municipality_id ?? 0);
        $vilId = (int)($program->village_id ?? 0);

        $munName = $munId > 0 ? (string)(DB::table('municipalities')->where('id', $munId)->value('name') ?? '') : '';
        $vilName = $vilId > 0 ? (string)(DB::table('villages')->where('id', $vilId)->value('name') ?? '') : '';

        if ($placeType === 'vereda') {

            if ($vilId > 0) {
                $existingActiveId = DB::table('village_rates')
                    ->where('village_id', $vilId)
                    ->where('active', 1)
                    ->value('id');

                if ($existingActiveId) {
                    DB::table('village_rates')->where('id', $existingActiveId)->update([
                        $col => $unitAmount,
                        'updated_at' => now(),
                        'village_name' => $vilName !== '' ? $vilName : DB::raw('village_name'),
                        'municipality_id' => $munId ?: DB::raw('municipality_id'),
                        'municipality_name' => $munName !== '' ? $munName : DB::raw('municipality_name'),
                    ]);
                    return;
                }

                DB::table('village_rates')->insert([
                    'municipality_id' => $munId ?: null,
                    'village_id' => $vilId,
                    'village_name' => $vilName !== '' ? $vilName : 'Vereda',
                    'municipality_name' => $munName !== '' ? $munName : 'Municipio',
                    'bus_amount' => 0,
                    'van_amount' => 0,
                    'motorcycle_amount' => 0,
                    $col => $unitAmount,
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return;
            }

            if ($munId > 0) {
                $existingActiveId = DB::table('village_rates')
                    ->whereNull('village_id')
                    ->where('municipality_id', $munId)
                    ->where('active', 1)
                    ->value('id');

                if ($existingActiveId) {
                    DB::table('village_rates')->where('id', $existingActiveId)->update([
                        $col => $unitAmount,
                        'updated_at' => now(),
                        'municipality_id' => $munId,
                        'municipality_name' => $munName !== '' ? $munName : DB::raw('municipality_name'),
                    ]);
                    return;
                }

                DB::table('village_rates')->insert([
                    'municipality_id' => $munId,
                    'village_id' => null,
                    'village_name' => null,
                    'municipality_name' => $munName !== '' ? $munName : 'Municipio',
                    'bus_amount' => 0,
                    'van_amount' => 0,
                    'motorcycle_amount' => 0,
                    $col => $unitAmount,
                    'active' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        // ------------------------------------------------------------
        // MUNICIPIO: municipality_rates por municipality_id
        // ------------------------------------------------------------
        if ($munId <= 0) return;

        $existingActiveId = DB::table('municipality_rates')
            ->where('municipality_id', $munId)
            ->where('active', 1)
            ->value('id');

        if ($existingActiveId) {
            DB::table('municipality_rates')->where('id', $existingActiveId)->update([
                $col => $unitAmount,
                'updated_at' => now(),
                'municipality_name' => $munName !== '' ? $munName : DB::raw('municipality_name'),
            ]);
            return;
        }

        DB::table('municipality_rates')->insert([
            'municipality_id' => $munId,
            'municipality_name' => $munName !== '' ? $munName : 'Municipio',
            'bus_amount' => 0,
            'van_amount' => 0,
            'motorcycle_amount' => 0,
            $col => $unitAmount,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function inferByDateFromSegments(TravelRequest $travel): array
    {
        $map = [];

        foreach (($travel->segments ?? collect()) as $seg) {
            if ((int)$seg->is_cancelled === 1) continue;

            $meta = $this->tryJson($seg->notes);
            $dateId = (string)($meta['program_request_date_id'] ?? '');
            if ($dateId === '') continue;

            // transport_type segment -> cost transport
            $t = match ((string)$seg->transport_type) {
                'moto'      => 'motorcycle',
                'camioneta' => 'van',
                'terrestre' => 'bus',
                'aereo'     => 'air',
                default     => null,
            };

            // trip_type segment -> direction
            $dir = ((string)$seg->trip_type === 'round_trip') ? 'round_trip' : 'one_way';

            if ($t) {
                $map[$dateId] = [
                    'transport'   => $t,
                    'direction'   => $dir,
                    'unit_amount' => null, // esto lo pones sugerido en vista
                ];
            }
        }

        return $map;
    }



    private function syncDateRangeFromSegments(TravelRequest $travel): void
    {
        $minDep = TravelSegment::where('travel_request_id', $travel->id)
            ->where('is_cancelled', 0)
            ->min('departure_at');

        $maxRet = TravelSegment::where('travel_request_id', $travel->id)
            ->where('is_cancelled', 0)
            ->max('return_at');

        if ($minDep && $maxRet) {
            $travel->start_date = Carbon::parse($minDep)->toDateString();
            $travel->end_date   = Carbon::parse($maxRet)->toDateString();
            $travel->save();
        }
    }



    private function normalizeDirection(string $direction): string
    {
        $direction = trim((string)$direction);

        // Compatibilidad legacy: si aún llega "return" desde alguna vista vieja
        if ($direction === 'return') return 'two_way';

        return in_array($direction, ['one_way', 'two_way', 'round_trip'], true)
            ? $direction
            : 'one_way';
    }


    private function directionToTripMeta(string $direction): array
    {
        $direction = $this->normalizeDirection($direction);

        return match ($direction) {
            'round_trip' => ['trip_type' => 'round_trip', 'trips' => 2, 'mult' => 2],
            'two_way'    => ['trip_type' => 'two_way',    'trips' => 1, 'mult' => 1],
            default      => ['trip_type' => 'one_way',    'trips' => 1, 'mult' => 1],
        };
    }


    private function mapTransportToSegmentEnum(?string $transport): ?string
    {
        $transport = trim((string)$transport);

        return match ($transport) {
            'bus'        => 'terrestre',
            'van'        => 'camioneta',
            'motorcycle' => 'moto',
            'air', 'plane', 'flight', 'aereo' => 'aereo',
            default => null, // no setea si no se reconoce
        };
    }

    private function resolveActiveRateIdsFromProgram(ProgramRequest $program): array
    {
        $municipalityId = (int)($program->municipality_id ?? 0);
        $villageId      = (int)($program->village_id ?? 0);

        $municipalityRateId = null;
        $villageRateId = null;

        if ($municipalityId > 0) {
            $municipalityRateId = DB::table('municipality_rates')
                ->where('municipality_id', $municipalityId)
                ->where('active', 1)
                ->value('id');
        }

        if ($villageId > 0) {
            $villageRateId = DB::table('village_rates')
                ->where('village_id', $villageId)
                ->where('active', 1)
                ->value('id');

            if (!$villageRateId && $municipalityId > 0) {
                $villageRateId = DB::table('village_rates')
                    ->where('municipality_id', $municipalityId)
                    ->where('active', 1)
                    ->value('id');
            }
        }

        $destType = 'other';
        if ($villageId > 0) $destType = 'village';
        elseif ($municipalityId > 0) $destType = 'municipality';

        return [
            'destination_type'     => $destType,
            'municipality_rate_id' => $municipalityRateId,
            'village_rate_id'      => $villageRateId,
        ];
    }
    private function fillSegmentGeoRatesTransport(
        TravelSegment $seg,
        ProgramRequest $program,
        string $rawDestinationText,
        ?string $transportCostValue, // bus|van|motorcycle...
        string $direction
    ): void {
        $trip = $this->directionToTripMeta($direction);

        $seg->trip_type = $trip['trip_type'];
        $seg->trips     = $trip['trips'];

        $segTransport = $this->mapTransportToSegmentEnum($transportCostValue);
        if ($segTransport) $seg->transport_type = $segTransport;

        $seg->destination_query = $rawDestinationText;

        // Geo desde SIGAC
        $seg->municipality_id = $program->municipality_id ?? null;
        $seg->village_id      = $program->village_id ?? null;

        // department_id: si no viene en program_requests, derívalo del municipio
        $seg->department_id = $program->department_id ?? null;
        if (!$seg->department_id && !empty($seg->municipality_id)) {
            $seg->department_id = DB::table('municipalities')
                ->where('id', $seg->municipality_id)
                ->value('department_id');
        }

        // display_name bonito
        $munName = null;
        $vilName = null;
        if (!empty($seg->municipality_id)) {
            $munName = DB::table('municipalities')->where('id', $seg->municipality_id)->value('name');
        }
        if (!empty($seg->village_id)) {
            $vilName = DB::table('villages')->where('id', $seg->village_id)->value('name');
        }

        if ($vilName) {
            $seg->destination_display_name = trim($vilName . ($munName ? " - {$munName}" : ''));
        } elseif ($munName) {
            $seg->destination_display_name = $munName;
        } else {
            $seg->destination_display_name = $rawDestinationText;
        }

        // Rates + destination_type
        $rate = $this->resolveActiveRateIdsFromProgram($program);

        $seg->destination_type     = $rate['destination_type'];       // municipality|village|other
        $seg->municipality_rate_id = $rate['municipality_rate_id'] ?? null;
        $seg->village_rate_id      = $rate['village_rate_id'] ?? null;
    }
}
