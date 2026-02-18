{{-- Modules/GDF/Resources/views/official/SITRAV/create.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'SITRAV | Crear desde SIGAC')

@section('content')
@php
  /** @var \Modules\SIGAC\Entities\ProgramRequest $program */
  $program = $program ?? null;
  if(!$program) abort(404);

  $preview = $preview ?? [];
  $dates   = $dates ?? collect();

  $hasMoto         = (bool)($hasMoto ?? false);
  $isStaff         = (bool)($isStaff ?? false);
  $personType      = (string)($personType ?? '');
  $forceMotorcycle = (bool)($forceMotorcycle ?? false);
  $isVillage       = (bool)($isVillage ?? false);

  $transportOptions = $forceMotorcycle
    ? ['motorcycle' => 'Moto (obligatoria)']
    : ($hasMoto
        ? ['motorcycle' => 'Moto', 'bus' => 'Transporte público']
        : ['bus' => 'Transporte público']
      );

  $defaultTransport = old('cost.transport',
    $forceMotorcycle ? 'motorcycle' : (array_key_exists('motorcycle',$transportOptions) ? 'motorcycle' : 'bus')
  );
  $defaultDirection = old('cost.direction','one_way');
    if ($defaultDirection === 'return') $defaultDirection = 'two_way';


  // ✅ CRÍTICO: Asegurar que ratesByTransport existe
  $ratesByTransport = $ratesByTransport ?? ['bus' => 0, 'van' => 0, 'motorcycle' => 0];
  $baseRate = (float)($ratesByTransport['bus'] ?? 0);

  $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

  $totalDates   = is_countable($dates) ? $dates->count() : 0;
  $areaName     = $preview['area_name'] ?? ($preview['area_key'] ?? '—');
  $budgetName   = $preview['budget_item_name'] ?? '—';

  $sameTransportAll = old('cost.same_for_all', '1') === '1';
  if ($forceMotorcycle) $sameTransportAll = true;

  // Gasolina
  $fuelBaseRate = (float)($fuelBaseRate ?? 0);
  $fuelUnit  = old('fuel.unit_amount', $fuelBaseRate > 0 ? $fuelBaseRate : '');
  $fuelUnits = old('fuel.units', 1);
  $fuelDesc  = old('fuel.description', 'Gasolina - Moto');

  // Viáticos staff
  $allowEnabled = old('allowances_enabled', '0') === '1';
  $oldAllowances = old('allowances', []);
  if (!is_array($oldAllowances) || count($oldAllowances) === 0) {
      $oldAllowances = [
        ['allowance_type' => 'meals', 'unit_amount' => '', 'units' => 1, 'description' => 'Alimentación'],
      ];
  }

  $allowTypeLabels = [
    'lodging'  => 'Hospedaje',
    'meals'    => 'Alimentación',
    'per_diem' => 'Viático diario (Per diem) - dinero por día',
    'other'    => 'Otro',
  ];
@endphp

<style>
  :root { --sitrav-bg-primary:#fff; --sitrav-bg-secondary:#f8f9fa; --sitrav-bg-card:#fff; --sitrav-border:#dee2e6;
    --sitrav-text-primary:#212529; --sitrav-text-secondary:#6c757d; --sitrav-text-muted:#868e96;
    --sitrav-shadow:rgba(0,0,0,.08); --sitrav-shadow-hover:rgba(0,0,0,.12);
    --sitrav-accent:#0d6efd; --sitrav-accent-light:#e7f1ff;
    --sitrav-success:#198754; --sitrav-success-light:#d1e7dd;
    --sitrav-danger:#dc3545; --sitrav-danger-light:#f8d7da;
    --sitrav-warning:#ffc107; --sitrav-warning-light:#fff3cd;
    --sitrav-info:#0dcaf0; --sitrav-info-light:#cff4fc;
  }
  @media (prefers-color-scheme: dark){
    :root { --sitrav-bg-primary:#1a1d23; --sitrav-bg-secondary:#13161a; --sitrav-bg-card:#23262d; --sitrav-border:#3a3f47;
      --sitrav-text-primary:#e8eaed; --sitrav-text-secondary:#b8bcc4; --sitrav-text-muted:#8a8f98;
      --sitrav-shadow:rgba(0,0,0,.3); --sitrav-shadow-hover:rgba(0,0,0,.5);
      --sitrav-accent:#4a9eff; --sitrav-accent-light:#1a3a5c;
      --sitrav-success:#28a745; --sitrav-success-light:#1a4028;
      --sitrav-danger:#dc3545; --sitrav-danger-light:#4a1f24;
      --sitrav-warning:#ffc107; --sitrav-warning-light:#4a3a0a;
      --sitrav-info:#17a2b8; --sitrav-info-light:#1a3a42;
    }
  }
  .sitrav-container{background:var(--sitrav-bg-primary);color:var(--sitrav-text-primary);min-height:100vh;padding:2rem 0;}
  .sitrav-card{background:var(--sitrav-bg-card);border:1px solid var(--sitrav-border);border-radius:12px;box-shadow:0 2px 8px var(--sitrav-shadow);transition:.3s;overflow:hidden;}
  .sitrav-card:hover{box-shadow:0 4px 16px var(--sitrav-shadow-hover);}
  .sitrav-card-header{background:var(--sitrav-bg-secondary);border-bottom:1px solid var(--sitrav-border);padding:1.25rem 1.5rem;}
  .sitrav-card-body{padding:1.5rem;}
  .sitrav-chip{display:inline-flex;align-items:center;padding:.375rem .875rem;background:var(--sitrav-accent-light);color:var(--sitrav-accent);border-radius:999px;font-size:.875rem;font-weight:500;gap:.375rem;}
  .sitrav-info-label{color:var(--sitrav-text-muted);font-size:.875rem;font-weight:500;margin-bottom:.25rem;}
  .sitrav-info-value{color:var(--sitrav-text-primary);font-weight:600;font-size:1rem;}
  .sitrav-divider{border:0;height:1px;background:linear-gradient(to right,transparent,var(--sitrav-border),transparent);margin:1.5rem 0;}
  .sitrav-form-control{background:var(--sitrav-bg-secondary);border:1px solid var(--sitrav-border);color:var(--sitrav-text-primary);border-radius:8px;padding:.625rem .875rem;transition:.2s;}
  .sitrav-form-control:focus{background:var(--sitrav-bg-card);border-color:var(--sitrav-accent);box-shadow:0 0 0 3px var(--sitrav-accent-light);outline:none;}
  .sitrav-switch{background:var(--sitrav-bg-secondary);border:1px solid var(--sitrav-border);border-radius:10px;padding:1rem 1.25rem;transition:.2s;}
  .sitrav-switch:hover{background:var(--sitrav-accent-light);}
  .sitrav-table{background:var(--sitrav-bg-card);border-radius:8px;overflow:hidden;}
  .sitrav-table thead{background:var(--sitrav-bg-secondary);}
  .sitrav-table thead th{border-bottom:2px solid var(--sitrav-border);color:var(--sitrav-text-secondary);font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.6px;padding:1rem .75rem;white-space:nowrap;}
  .sitrav-table tbody tr{border-bottom:1px solid var(--sitrav-border);transition:.2s;}
  .sitrav-table tbody tr:hover{background:var(--sitrav-bg-secondary);}
  .sitrav-table tbody td{padding:1rem .75rem;color:var(--sitrav-text-primary);vertical-align:middle;}
  .sitrav-btn{padding:.625rem 1.5rem;border-radius:8px;font-weight:600;transition:.2s;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;}
  .sitrav-btn-primary{background:var(--sitrav-accent);color:#fff;}
  .sitrav-btn-primary:hover{transform:translateY(-2px);box-shadow:0 4px 12px var(--sitrav-shadow-hover);filter:brightness(1.1);}
  .sitrav-btn-ghost{background:transparent;color:var(--sitrav-text-secondary);border:1px solid var(--sitrav-border);}
  .sitrav-btn-ghost:hover{background:var(--sitrav-bg-secondary);border-color:var(--sitrav-accent);color:var(--sitrav-accent);}
  .sitrav-alert{padding:1rem 1.25rem;border-radius:8px;border-left:4px solid;margin-bottom:1.5rem;}
  .sitrav-alert-success{background:var(--sitrav-success-light);border-color:var(--sitrav-success);color:var(--sitrav-success);}
  .sitrav-alert-danger{background:var(--sitrav-danger-light);border-color:var(--sitrav-danger);color:var(--sitrav-danger);}
  .sitrav-alert-warning{background:var(--sitrav-warning-light);border-color:var(--sitrav-warning);color:#8a6d00;}
  .sitrav-hint{background:var(--sitrav-info-light);border-left:3px solid var(--sitrav-info);padding:.875rem 1rem;border-radius:6px;color:var(--sitrav-text-secondary);font-size:.9rem;}
  .sitrav-badge{display:inline-flex;align-items:center;gap:.45rem;padding:.3rem .6rem;border-radius:999px;font-size:.85rem;border:1px solid var(--sitrav-border);background:var(--sitrav-bg-secondary);color:var(--sitrav-text-secondary);}
  .sitrav-badge strong{color:var(--sitrav-text-primary);}
</style>

<div class="sitrav-container">
  <div class="container-fluid px-4">

    @if (session('success'))
      <div class="sitrav-alert sitrav-alert-success"><strong>✓</strong> {{ session('success') }}</div>
    @endif
    @if (session('error'))
      <div class="sitrav-alert sitrav-alert-danger"><strong>✗</strong> {{ session('error') }}</div>
    @endif
    @if ($errors->any())
      <div class="sitrav-alert sitrav-alert-warning">
        <div class="fw-semibold mb-2">Revisa los siguientes campos:</div>
        <ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="d-flex align-items-center justify-content-between mb-4">
      <div>
        <h3 class="mb-2" style="color: var(--sitrav-text-primary); font-weight: 700;">Crear Solicitud SITRAV desde SIGAC</h3>
        <div class="d-flex flex-wrap gap-2">
          <span class="sitrav-chip">Fuente: SIGAC</span>
          <span class="sitrav-chip">ProgramRequest #{{ $program->id }}</span>
          <span class="sitrav-chip">Estado: <strong>{{ strtoupper((string)($program->state ?? '')) }}</strong></span>
          <span class="sitrav-chip">{{ $personType ? strtoupper($personType) : ($isStaff ? 'PLANTA' : 'CONTRATISTA') }}</span>
          @if($forceMotorcycle)
            <span class="sitrav-chip" style="background: var(--sitrav-warning-light); color: #8a6d00;">Moto obligatoria</span>
          @endif
          <span class="sitrav-chip">{{ $isVillage ? 'Destino: VEREDA' : 'Destino: MUNICIPIO' }}</span>
        </div>
      </div>
      <a class="sitrav-btn sitrav-btn-ghost" href="{{ route('gdf.instructor.sitrav.programs.index') }}">← Volver</a>
    </div>

    <form method="POST" action="{{ route('gdf.instructor.sitrav.programs.store', $program->id) }}" id="createForm">
      @csrf

      <div class="row g-4">
        <div class="col-lg-4">
          <div class="sitrav-card">
            <div class="sitrav-card-header">
              <div class="fw-semibold" style="color: var(--sitrav-text-primary);">Datos del Programa</div>
            </div>
            <div class="sitrav-card-body">
              <div class="mb-3">
                <div class="sitrav-info-label">Área</div>
                <div class="sitrav-info-value">{{ $areaName }}</div>
              </div>
              <div class="mb-3">
                <div class="sitrav-info-label">Rubro</div>
                <div class="sitrav-info-value">{{ $budgetName }}</div>
              </div>

              <hr class="sitrav-divider">

              <div class="mb-3">
                <div class="sitrav-info-label">Origen</div>
                <div class="sitrav-info-value">{{ $preview['origin'] ?? 'CEFA Campoalegre' }}</div>
              </div>
              <div class="mb-3">
                <div class="sitrav-info-label">Destino</div>
                <div class="sitrav-info-value">{{ $preview['destination'] ?? '—' }}</div>
              </div>
              <div class="mb-3">
                <div class="sitrav-info-label">Trayectos (fechas)</div>
                <div class="sitrav-info-value">{{ $totalDates }}</div>
              </div>

              <div class="mb-0">
                <div class="sitrav-info-label">Rango</div>
                <div class="sitrav-info-value" style="font-size:.95rem;">
                  {{ $preview['start_date'] ?? '—' }} — {{ $preview['end_date'] ?? '—' }}
                </div>
              </div>

              <div class="sitrav-hint mt-3">
                SITRAV trabaja por <strong>fecha/hora</strong> (mismo día con inicio/fin).
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="sitrav-card">
            <div class="sitrav-card-header">
              <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="fw-semibold" style="color: var(--sitrav-text-primary);">Configuración de Transporte</div>
                <div class="d-flex gap-2 flex-wrap">
                  <span class="sitrav-badge">Activos: <strong id="daysActive">0</strong></span>
                  <span class="sitrav-badge">Total ref: <strong id="transportTotalRef">—</strong></span>
                  <span class="sitrav-badge">Viáticos ref: <strong id="allowTotalRef">—</strong></span>
                </div>
              </div>
            </div>

            <div class="sitrav-card-body">
              <div class="sitrav-hint mb-3">
                El valor es <strong>por trayecto (IDA)</strong>. Ida/Vuelta multiplica x2 y se multiplica por los <strong>trayectos activos</strong>.
                @if(!$hasMoto) <strong style="color:#8a6d00;">· Moto no disponible</strong> @endif
              </div>

              <div class="sitrav-switch mb-4">
                <div class="form-check m-0">
                  <input class="form-check-input" type="checkbox"
                         name="cost[same_for_all]" value="1"
                         id="sameForAll"
                         @checked($sameTransportAll)
                         @disabled($forceMotorcycle)>
                  <label class="form-check-label" for="sameForAll" style="cursor:pointer;">
                    <strong>Usar el mismo transporte</strong> para todos los trayectos
                    @if($forceMotorcycle)
                      <span style="color:#8a6d00;"> (bloqueado por política)</span>
                    @endif
                  </label>
                  @if($forceMotorcycle)
                    <input type="hidden" name="cost[same_for_all]" value="1">
                  @endif
                </div>
              </div>

              <div id="globalCostBlock" class="{{ $sameTransportAll ? '' : 'd-none' }}">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label sitrav-info-label">Tipo de transporte</label>
                    <select name="cost[transport]" class="form-select sitrav-form-control" id="transportSel" @disabled($forceMotorcycle)>
                      @foreach($transportOptions as $k=>$lbl)
                        <option value="{{ $k }}" @selected($defaultTransport===$k)>{{ $lbl }}</option>
                      @endforeach
                    </select>
                    @if($forceMotorcycle)
                      <input type="hidden" name="cost[transport]" value="motorcycle">
                    @endif
                  </div>

                  <div class="col-md-4">
                    <label class="form-label sitrav-info-label">Dirección</label>
                    <select name="cost[direction]" class="form-select sitrav-form-control" id="directionSel">
                      <option value="one_way"    @selected($defaultDirection==='one_way')>Ida</option>
                      <option value="two_way"    @selected($defaultDirection==='two_way')>Solo vuelta</option>
                      <option value="round_trip" @selected($defaultDirection==='round_trip')>Ida/Vuelta</option>
                    </select>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label sitrav-info-label">Valor (IDA)</label>
                    <input name="cost[unit_amount]"
                           id="unitAmount"
                           class="form-control sitrav-form-control"
                           value="{{ old('cost.unit_amount', $baseRate > 0 ? $baseRate : '') }}"
                           placeholder="Sugerido (editable)"
                           data-suggested="{{ (float)$baseRate }}">
                    <small class="form-text" style="color: var(--sitrav-text-muted);">
                      Sugerido: <strong id="suggestedHint">{{ $baseRate>0 ? $fmtMoney($baseRate) : '—' }}</strong>
                      <span class="ms-1" id="globalMotoLockHint" style="color:#8a6d00; display:none;">· Bloqueado por moto</span>
                    </small>
                  </div>
                </div>
              </div>

              <div id="perDateHint" class="{{ $sameTransportAll ? 'd-none' : '' }}">
                <div class="sitrav-hint">
                  <strong>Modo por fecha:</strong> define el transporte individualmente en la tabla de fechas para cada trayecto.
                </div>
              </div>

              <div id="fuelPanel" class="mt-4 d-none">
                <div class="sitrav-card" style="border-color: var(--sitrav-warning);">
                  <div class="sitrav-card-header" style="background: var(--sitrav-warning-light);">
                    <div class="fw-semibold" style="color: var(--sitrav-text-primary);">Viático gasolina (obligatorio si usas moto)</div>
                  </div>
                  <div class="sitrav-card-body">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label sitrav-info-label">Valor unitario</label>
                        <input class="form-control sitrav-form-control" name="fuel[unit_amount]" id="fuelUnitAmount"
                               value="{{ $fuelUnit }}" placeholder="Ej: 25000">
                      </div>
                      <div class="col-md-3">
                        <label class="form-label sitrav-info-label">Unid. x trayecto</label>
                        <input class="form-control sitrav-form-control" type="number" min="1"
                               name="fuel[units]" id="fuelUnits" value="{{ $fuelUnits }}">
                        <small class="form-text" style="color: var(--sitrav-text-muted);">
                          Mínimo sugerido-solo si es Trasporte publico: <strong id="fuelMinHint">—</strong>
                        </small>
                      </div>
                      <div class="col-md-5">
                        <label class="form-label sitrav-info-label">Descripción</label>
                        <input class="form-control sitrav-form-control" name="fuel[description]" id="fuelDesc"
                               value="{{ $fuelDesc }}" placeholder="Gasolina - Moto">
                      </div>
                    </div>
                    <div class="sitrav-hint mt-3">Este viático se guarda como <strong>fuel</strong>.</div>
                  </div>
                </div>
              </div>

              @if($isStaff)
                <div class="mt-4 sitrav-card">
                  <div class="sitrav-card-header">
                    <div class="fw-semibold" style="color: var(--sitrav-text-primary);">Viáticos (solo personal de planta)</div>
                    <small style="color: var(--sitrav-text-muted);">"Per diem" = dinero diario asignado.</small>
                  </div>
                  <div class="sitrav-card-body">
                    <div class="sitrav-switch mb-3">
                      <div class="form-check m-0">
                        <input class="form-check-input" type="checkbox" id="allowEnabled"
                               name="allowances_enabled" value="1" @checked($allowEnabled)>
                        <label class="form-check-label" for="allowEnabled" style="cursor:pointer;">
                          <strong>Incluir viáticos</strong> (hospedaje, alimentación, viático diario/per diem, otros)
                        </label>
                      </div>
                    </div>

                    <div id="allowBody" class="{{ $allowEnabled ? '' : 'd-none' }}">
                      <div class="table-responsive">
                        <table class="table sitrav-table mb-0">
                          <thead>
                            <tr>
                              <th style="width:280px;">Tipo</th>
                              <th style="width:180px;">Valor unitario</th>
                              <th style="width:160px;">Unid. x trayecto</th>
                              <th>Descripción</th>
                              <th style="width:120px;"></th>
                            </tr>
                          </thead>
                          <tbody id="allowRows">
                            @foreach($oldAllowances as $i => $row)
                              @php
                                $t = (string)($row['allowance_type'] ?? 'meals');
                                $u = $row['unit_amount'] ?? '';
                                $n = (int)($row['units'] ?? 1); if($n<1) $n=1;
                                $d = (string)($row['description'] ?? '');
                              @endphp
                              <tr class="allowRow">
                                <td>
                                  <select class="form-select sitrav-form-control allowType" name="allowances[{{ $i }}][allowance_type]">
                                    @foreach($allowTypeLabels as $k=>$lbl)
                                      <option value="{{ $k }}" @selected($t===$k)>{{ $lbl }}</option>
                                    @endforeach
                                  </select>
                                </td>
                                <td><input class="form-control sitrav-form-control allowUnit" name="allowances[{{ $i }}][unit_amount]" value="{{ $u }}" placeholder="Ej: 85000"></td>
                                <td><input type="number" min="1" class="form-control sitrav-form-control allowUnits" name="allowances[{{ $i }}][units]" value="{{ $n }}"></td>
                                <td><input class="form-control sitrav-form-control allowDesc" name="allowances[{{ $i }}][description]" value="{{ $d }}" placeholder="Opcional (máx 255)"></td>
                                <td class="text-end"><button type="button" class="sitrav-btn sitrav-btn-ghost btnRemoveAllow">Quitar</button></td>
                              </tr>
                            @endforeach
                          </tbody>
                        </table>
                      </div>

                      <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <div class="sitrav-hint" style="flex:1;">
                          Total ref = <strong>unitario</strong> × <strong>unid. x trayecto</strong> × <strong>trayectos activos</strong>.
                        </div>
                        <div><button type="button" class="sitrav-btn sitrav-btn-primary" id="btnAddAllow">+ Agregar viático</button></div>
                      </div>
                    </div>
                  </div>
                </div>
              @endif

            </div>
          </div>
        </div>
      </div>

      <div class="mt-4 sitrav-card">
        <div class="sitrav-card-header">
          <div>
            <div class="fw-semibold mb-1" style="color: var(--sitrav-text-primary);">Fechas del Programa (SIGAC)</div>
            <small style="color: var(--sitrav-text-muted);">Selecciono / Reprogramo / Excluyo. Reprogramo es local SITRAV (no toca SIGAC).</small>
          </div>
        </div>

        <div class="sitrav-card-body p-0">
          <div class="table-responsive">
            <table class="table sitrav-table mb-0">
              <thead>
                <tr>
                  <th style="width:170px;">Decisión</th>
                  <th style="width:230px;">Fecha / Hora</th>
                  <th>Motivo</th>
                  <th style="width:360px;">Propuesta</th>
                  <th class="colPerDate" style="width:180px;">Transporte</th>
                  <th class="colPerDate" style="width:160px;">Dirección</th>
                  <th class="colPerDate" style="width:220px;">Valor (IDA)</th>
                </tr>
              </thead>
              <tbody>
                @foreach($dates as $d)
                  @php
                    $dateId = (int)$d->id;
                    $defaultStart = $d->date.'T'.substr((string)$d->start_time,0,5);
                    $defaultEnd   = $d->date.'T'.substr((string)$d->end_time,0,5);

                    $oldMode   = old("decisions.$dateId.mode", 'selected');
                    $oldReason = old("decisions.$dateId.reason", '');
                    $oldPS     = old("decisions.$dateId.proposed_start", $defaultStart);
                    $oldPE     = old("decisions.$dateId.proposed_end", $defaultEnd);

                    $rowTransport = old("costs_by_date.$dateId.transport", $defaultTransport);
                    $rowDir = old("costs_by_date.$dateId.direction", $defaultDirection);
                      if ($rowDir === 'return') $rowDir = 'two_way';
                    $rowUnit      = old("costs_by_date.$dateId.unit_amount", $baseRate>0 ? $baseRate : '');

                    $isRowMoto    = ((string)$rowTransport === 'motorcycle');
                  @endphp

                  <tr data-date-id="{{ $dateId }}">
                    <td>
                      <select class="form-select sitrav-form-control decisionMode"
                              name="decisions[{{ $dateId }}][mode]" data-date-id="{{ $dateId }}">
                        <option value="selected"   @selected($oldMode==='selected')>✓ Selecciono</option>
                        <option value="reschedule" @selected($oldMode==='reschedule')>↻ Reprogramo</option>
                        <option value="excluded"   @selected($oldMode==='excluded')>✕ Excluyo</option>
                      </select>
                    </td>

                    <td>
                      <div class="fw-semibold" style="color: var(--sitrav-text-primary);">{{ $d->date }}</div>
                      <small style="color: var(--sitrav-text-muted);">{{ substr((string)$d->start_time,0,5) }} - {{ substr((string)$d->end_time,0,5) }}</small>
                    </td>

                    <td>
                      <input class="form-control sitrav-form-control reasonInput"
                             name="decisions[{{ $dateId }}][reason]"
                             value="{{ $oldReason }}"
                             placeholder="Obligatorio si excluyes o reprogramas"
                             data-date-id="{{ $dateId }}">
                    </td>

                    <td>
                      <div class="row g-2">
                        <div class="col-6">
                          <input type="datetime-local" class="form-control sitrav-form-control psInput"
                                 name="decisions[{{ $dateId }}][proposed_start]" value="{{ $oldPS }}"
                                 data-date-id="{{ $dateId }}">
                        </div>
                        <div class="col-6">
                          <input type="datetime-local" class="form-control sitrav-form-control peInput"
                                 name="decisions[{{ $dateId }}][proposed_end]" value="{{ $oldPE }}"
                                 data-date-id="{{ $dateId }}">
                        </div>
                      </div>
                      <small style="color: var(--sitrav-text-muted);">Solo si reprogramas</small>
                    </td>

                    <td class="colPerDate">
                      <select class="form-select sitrav-form-control perDateField perDateTransport"
                              name="costs_by_date[{{ $dateId }}][transport]"
                              @disabled($forceMotorcycle)>
                        @foreach($transportOptions as $k=>$lbl)
                          <option value="{{ $k }}" @selected($rowTransport===$k)>{{ $lbl }}</option>
                        @endforeach
                      </select>
                      @if($forceMotorcycle)
                        <input type="hidden" name="costs_by_date[{{ $dateId }}][transport]" value="motorcycle">
                      @endif
                    </td>

                    <td class="colPerDate">
                      <select class="form-select sitrav-form-control perDateField perDateDirection"
                              name="costs_by_date[{{ $dateId }}][direction]">
                        <option value="one_way"    @selected($rowDir==='one_way')>Ida</option>
                        <option value="two_way"    @selected($rowDir==='two_way')>Solo vuelta</option>
                        <option value="round_trip" @selected($rowDir==='round_trip')>Ida/Vuelta</option>
                      </select>
                    </td>

                    <td class="colPerDate">
                      <input class="form-control sitrav-form-control perDateField perDateUnit"
                             name="costs_by_date[{{ $dateId }}][unit_amount]"
                             value="{{ $isRowMoto ? '0' : $rowUnit }}"
                             placeholder="Sugerido (editable)"
                             data-base-rate="{{ (float)$baseRate }}">
                      <small class="form-text" style="color: var(--sitrav-text-muted);">
                        Sugerido: <strong>{{ $baseRate>0 ? $fmtMoney($baseRate) : '—' }}</strong>
                        <span class="ms-1 motoLockHint" style="color:#8a6d00; display: {{ $isRowMoto ? 'inline' : 'none' }};">· Bloqueado por moto</span>
                      </small>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end p-3" style="background: var(--sitrav-bg-secondary); border-top: 1px solid var(--sitrav-border);">
            <button class="sitrav-btn sitrav-btn-primary" id="submitBtn">✓ Crear solicitud</button>
          </div>
        </div>
      </div>

    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
  // ✅ CRÍTICO: Cargar tarifas desde PHP
  const transportRates = @json($ratesByTransport);

  function moneyToFloatJS(v){
    if (typeof v === 'number') return v;
    let s = (v ?? '').toString().trim();
    if(!s) return 0;
    s = s.replace(/[^\d.,-]/g,'');
    if (s.includes('.') && !s.includes(',')) { s = s.replaceAll('.',''); return parseFloat(s || '0') || 0; }
    if (s.includes('.') && s.includes(',')) { s = s.replaceAll('.',''); s = s.replaceAll(',','.'); return parseFloat(s || '0') || 0; }
    if (s.includes(',')) s = s.replaceAll(',','.');
    return parseFloat(s || '0') || 0;
  }

  function fmtCOP(n){
    n = Number(n || 0);
    try { return '$ ' + n.toLocaleString('es-CO', { maximumFractionDigits: 0 }); }
    catch(e){ return '$ ' + Math.round(n).toString(); }
  }

  function isActiveMode(mode){ return mode === 'selected' || mode === 'reschedule'; }

  function toggleRow(dateId, mode){
    const reason = document.querySelector(`.reasonInput[data-date-id="${dateId}"]`);
    const ps = document.querySelector(`.psInput[data-date-id="${dateId}"]`);
    const pe = document.querySelector(`.peInput[data-date-id="${dateId}"]`);
    if(!reason || !ps || !pe) return;

    if(mode === 'selected'){
      reason.disabled = true; reason.value = '';
      ps.disabled = true; pe.disabled = true;
    } else if (mode === 'excluded'){
      reason.disabled = false;
      ps.disabled = true; pe.disabled = true;
    } else {
      reason.disabled = false;
      ps.disabled = false; pe.disabled = false;
    }
  }

  function getActiveDays(){
    let c = 0;
    document.querySelectorAll('.decisionMode').forEach(sel=>{
      if(isActiveMode((sel.value || '').toString())) c++;
    });
    return c;
  }

  function getMultiplierFrom(dir){
  if(dir === 'round_trip') return 2;
  return 1; // one_way / two_way
}


  function applyPerDateVisibility(){
    const sameAll = document.getElementById('sameForAll')?.checked;
    document.getElementById('globalCostBlock')?.classList.toggle('d-none', !sameAll);
    document.getElementById('perDateHint')?.classList.toggle('d-none', sameAll);
    document.querySelectorAll('.colPerDate').forEach(el => el.classList.toggle('d-none', sameAll));
  }

  function isMotoValue(v){ return (v || '').toString() === 'motorcycle'; }

  function anyMotoSelected(){
    const sameAll = document.getElementById('sameForAll')?.checked;

    if(sameAll){
      return isMotoValue(document.getElementById('transportSel')?.value);
    }

    let any = false;
    document.querySelectorAll('tbody tr[data-date-id]').forEach(tr=>{
      const dateId = tr.getAttribute('data-date-id');
      const mode = document.querySelector(`.decisionMode[data-date-id="${dateId}"]`)?.value || 'selected';
      if(!isActiveMode(mode)) return;

      const tSel = tr.querySelector('.perDateTransport');
      if (tSel && isMotoValue(tSel.value)) any = true;
    });
    return any;
  }

  function countMotoActiveTrips(){
    const sameAll = document.getElementById('sameForAll')?.checked;

    if(sameAll){
      const t = document.getElementById('transportSel')?.value || 'bus';
      return (t === 'motorcycle') ? getActiveDays() : 0;
    }

    let c = 0;
    document.querySelectorAll('tbody tr[data-date-id]').forEach(tr=>{
      const dateId = tr.getAttribute('data-date-id');
      const mode = document.querySelector(`.decisionMode[data-date-id="${dateId}"]`)?.value || 'selected';
      if(!isActiveMode(mode)) return;

      const tSel = tr.querySelector('.perDateTransport');
      if(tSel && tSel.value === 'motorcycle') c++;
    });
    return c;
  }

  function syncGlobalUnitLock(){
    const sel = document.getElementById('transportSel');
    const unit = document.getElementById('unitAmount');
    const hint = document.getElementById('globalMotoLockHint');
    if(!sel || !unit) return;

    const isMoto = (sel.value || '') === 'motorcycle';

    if(isMoto){
      unit.value = '0';
      unit.readOnly = true;
      if(hint) hint.style.display = 'inline';
      return;
    }

    unit.readOnly = false;
    if(hint) hint.style.display = 'none';

    const current = moneyToFloatJS(unit.value);
    const suggested = moneyToFloatJS(unit.getAttribute('data-suggested') || '0');
    if(current <= 0 && suggested > 0){
      unit.value = String(suggested);
    }
  }

  function setPerDateUnitLock(tr){
  if(!tr) return;

  const tSel = tr.querySelector('.perDateTransport');
  const unit = tr.querySelector('.perDateUnit');
  const hint = tr.querySelector('.motoLockHint');
  if(!tSel || !unit) return;

  const t = (tSel.value || 'bus').toString();
  const isMoto = (t === 'motorcycle');

  // ✅ tarifa sugerida dinámica por transporte
  const suggested = Number(transportRates[t] || 0);
  unit.setAttribute('data-base-rate', String(suggested));

  if(isMoto){
    unit.value = '0';
    unit.readOnly = true;
    if(hint) hint.style.display = 'inline';
    return;
  }

  unit.readOnly = false;
  if(hint) hint.style.display = 'none';

  // Si está en 0/vacío, rellena con sugerido
  const current = moneyToFloatJS(unit.value);
  if(current <= 0 && suggested > 0){
    unit.value = String(suggested);
  }
}


  function refreshAllPerDateLocks(){
    document.querySelectorAll('tbody tr[data-date-id]').forEach(tr => setPerDateUnitLock(tr));
  }

  function toggleFuelPanel(){
    const on = anyMotoSelected();
    const panel = document.getElementById('fuelPanel');
    if(panel) panel.classList.toggle('d-none', !on);

    const unit = document.getElementById('fuelUnitAmount');
    const units = document.getElementById('fuelUnits');
    const hint = document.getElementById('fuelMinHint');

    if(unit) unit.required = on;
    if(units) units.required = on;

    if(on && units){
      const min = Math.max(1, countMotoActiveTrips());
      units.min = String(min);
      if(hint) hint.textContent = String(min);
      if((parseInt(units.value || '0', 10) || 0) < min) units.value = String(min);
    } else {
      if(hint) hint.textContent = '—';
    }
  }

  function recalcTransportRef(){
    const sameAll = document.getElementById('sameForAll')?.checked;
    let total = 0;
    const days = getActiveDays();

    const readUnitWithSuggested = (inputEl) => {
      if(!inputEl) return 0;
      const current = moneyToFloatJS(inputEl.value);
      if(current > 0) return current;

      const suggested = moneyToFloatJS(
        inputEl.getAttribute('data-suggested') || inputEl.getAttribute('data-base-rate') || '0'
      );
      return suggested > 0 ? suggested : 0;
    };

    if(sameAll){
      const t = document.getElementById('transportSel')?.value || 'bus';
      const dir = document.getElementById('directionSel')?.value || 'one_way';
      const unitEl = document.getElementById('unitAmount');

      const unit = (t === 'motorcycle') ? 0 : readUnitWithSuggested(unitEl);
      total = Math.max(0, unit) * getMultiplierFrom(dir) * Math.max(0, days);

    } else {
      document.querySelectorAll('tbody tr[data-date-id]').forEach(tr=>{
        const dateId = tr.getAttribute('data-date-id');
        const mode = document.querySelector(`.decisionMode[data-date-id="${dateId}"]`)?.value || 'selected';
        if(!isActiveMode(mode)) return;

        const tSel = tr.querySelector('.perDateTransport');
        const dirSel = tr.querySelector('.perDateDirection');
        const unitEl = tr.querySelector('.perDateUnit');

        const t = tSel ? tSel.value : 'bus';
        const dir = dirSel ? dirSel.value : 'one_way';

        const unit = (t === 'motorcycle') ? 0 : readUnitWithSuggested(unitEl);
        total += Math.max(0, unit) * getMultiplierFrom(dir);
      });
    }

    const daysEl = document.getElementById('daysActive');
    const totEl  = document.getElementById('transportTotalRef');
    if(daysEl) daysEl.textContent = String(days);
    if(totEl)  totEl.textContent  = total > 0 ? fmtCOP(total) : '—';
  }

  function recalcAllowRef(){
    const out = document.getElementById('allowTotalRef');
    if(!out) return;

    const enabled = document.getElementById('allowEnabled')?.checked;
    const body = document.getElementById('allowBody');
    if(!enabled || !body || body.classList.contains('d-none')){
      out.textContent = '—';
      return;
    }

    const active = Math.max(1, getActiveDays());
    let sum = 0;
    document.querySelectorAll('#allowRows .allowRow').forEach(tr=>{
      const u = moneyToFloatJS(tr.querySelector('.allowUnit')?.value);
      const perTripUnits = parseInt(tr.querySelector('.allowUnits')?.value || '1', 10) || 1;
      sum += Math.max(0, u) * Math.max(1, perTripUnits) * active;
    });
    out.textContent = sum > 0 ? fmtCOP(sum) : '—';
  }

  function renumberAllowRows(){
    const rows = document.querySelectorAll('#allowRows .allowRow');
    rows.forEach((tr, idx)=>{
      const type = tr.querySelector('.allowType');
      const unit = tr.querySelector('.allowUnit');
      const units= tr.querySelector('.allowUnits');
      const desc = tr.querySelector('.allowDesc');
      if(type)  type.name  = `allowances[${idx}][allowance_type]`;
      if(unit)  unit.name  = `allowances[${idx}][unit_amount]`;
      if(units) units.name = `allowances[${idx}][units]`;
      if(desc)  desc.name  = `allowances[${idx}][description]`;
    });
  }

  function allowRowTemplate(){
    return `
      <tr class="allowRow">
        <td>
          <select class="form-select sitrav-form-control allowType" name="">
            <option value="lodging">Hospedaje</option>
            <option value="meals" selected>Alimentación</option>
            <option value="per_diem">Viático diario (Per diem) - dinero por día</option>
            <option value="other">Otro</option>
          </select>
        </td>
        <td><input class="form-control sitrav-form-control allowUnit" name="" placeholder="Ej: 85000"></td>
        <td><input type="number" min="1" class="form-control sitrav-form-control allowUnits" name="" value="1"></td>
        <td><input class="form-control sitrav-form-control allowDesc" name="" placeholder="Opcional (máx 255)"></td>
        <td class="text-end"><button type="button" class="sitrav-btn sitrav-btn-ghost btnRemoveAllow">Quitar</button></td>
      </tr>
    `;
  }

  // ===== Event Listeners =====

  document.getElementById('allowEnabled')?.addEventListener('change', ()=>{
    const body = document.getElementById('allowBody');
    if(body) body.classList.toggle('d-none', !document.getElementById('allowEnabled').checked);
    recalcAllowRef();
  });

  document.getElementById('btnAddAllow')?.addEventListener('click', ()=>{
    const tbody = document.getElementById('allowRows');
    if(!tbody) return;
    tbody.insertAdjacentHTML('beforeend', allowRowTemplate());
    renumberAllowRows();
    recalcAllowRef();
  });

  document.addEventListener('click', (e)=>{
    if(e.target && e.target.classList.contains('btnRemoveAllow')){
      const tr = e.target.closest('tr');
      if(tr) tr.remove();
      renumberAllowRows();
      recalcAllowRef();
    }
  });

  document.addEventListener('input', (e)=>{
    if(e.target && (e.target.classList.contains('allowUnit') || e.target.classList.contains('allowUnits'))){
      recalcAllowRef();
    }
    if(e.target && (e.target.id === 'unitAmount' || e.target.classList.contains('perDateUnit'))){
      recalcTransportRef();
      toggleFuelPanel();
    }
  });

  document.querySelectorAll('.decisionMode').forEach(sel=>{
    toggleRow(sel.dataset.dateId, sel.value);
    sel.addEventListener('change', e=>{
      toggleRow(sel.dataset.dateId, e.target.value);
      recalcTransportRef();
      toggleFuelPanel();
      recalcAllowRef();
    });
  });

  document.getElementById('sameForAll')?.addEventListener('change', ()=>{
    applyPerDateVisibility();
    refreshAllPerDateLocks();
    syncGlobalUnitLock();
    recalcTransportRef();
    toggleFuelPanel();
    recalcAllowRef();
  });

  document.getElementById('directionSel')?.addEventListener('change', ()=>{
    recalcTransportRef();
    toggleFuelPanel();
  });

  // ✅ NUEVO: Listener para cambio de transporte con actualización de tarifa sugerida
  document.getElementById('transportSel')?.addEventListener('change', function() {
    const unit = document.getElementById('unitAmount');
    const hintEl = document.getElementById('suggestedHint');
    if (!unit) return;
    
    const transportType = this.value;
    const suggestedRate = transportRates[transportType] || 0;
    
    // Actualizar data-suggested
    unit.setAttribute('data-suggested', String(suggestedRate));
    
    if (transportType === 'motorcycle') {
      unit.value = '0';
      unit.readOnly = true;
    } else {
      unit.readOnly = false;
      
      // Solo actualizar value si está vacío o en 0
      const current = moneyToFloatJS(unit.value);
      if (current === 0 && suggestedRate > 0) {
        unit.value = String(suggestedRate);
      }
    }
    
    // Actualizar hint visual
    if (hintEl) {
      hintEl.textContent = suggestedRate > 0 ? fmtCOP(suggestedRate) : '—';
    }
    
    syncGlobalUnitLock();
    recalcTransportRef();
    toggleFuelPanel();
  });

  document.querySelectorAll('.perDateField').forEach(el=>{
  el.addEventListener('change', ()=>{
    const tr = el.closest('tr');
    if(tr) setPerDateUnitLock(tr);
    recalcTransportRef();
    toggleFuelPanel();
    recalcAllowRef();
  });
});


  // ===== Inicialización =====
  applyPerDateVisibility();
  refreshAllPerDateLocks();
  syncGlobalUnitLock();
  recalcTransportRef();
  toggleFuelPanel();
  recalcAllowRef();
</script>
@endpush