{{-- Modules/GDF/Resources/views/official/SITRAV/edit.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'SITRAV | Editar Solicitud')

@php
  use Carbon\Carbon;

  $travel = $travel ?? null;
  if(!$travel) abort(404);

  $program = $program ?? null;
  $dates = $program?->programRequestDates ?? collect();

  $hasMoto = (bool)($hasMoto ?? false);
  $isStaff = (bool)($isStaff ?? false);
  $canEdit = (bool)($canEdit ?? false);

  $transportCost = ($travel->costs ?? collect())->firstWhere('cost_type','transport');
  $transportMeta = [];
  if($transportCost){
    if(is_string($transportCost->description)){
      $tmp = json_decode($transportCost->description, true);
      if(is_array($tmp)) $transportMeta = $tmp;
    } elseif (is_array($transportCost->description)) {
      $transportMeta = $transportCost->description;
    }
  }

  $ratesByTransport = $ratesByTransport ?? ['bus' => 0.0, 'motorcycle' => 0.0];
  $transportOptions = $transportOptions ?? ['bus'=>'Transporte público'];

  unset($transportOptions['van']);

  if($hasMoto && !array_key_exists('motorcycle', $transportOptions)){
    $transportOptions = ['motorcycle' => 'Moto'] + $transportOptions;
  }

  $sameForAll = old('cost.same_for_all', (string)($transportMeta['same_for_all'] ?? '1')) === '1';

  $curTransport = (string) old('costs.0.transport', (string)($transportMeta['transport'] ?? array_key_first($transportOptions)));
  if(!in_array($curTransport, ['bus','motorcycle'], true)) $curTransport = 'bus';
  if(!$hasMoto && $curTransport === 'motorcycle') $curTransport = 'bus';

  $firstSeg = ($travel->segments ?? collect())->where('is_cancelled', 0)->first();
  $curDirection = 'one_way';
  if($firstSeg) {
      $t = (string)($firstSeg->trip_type ?? 'one_way');
      $curDirection = in_array($t, ['one_way','two_way','round_trip'], true) ? $t : 'one_way';
  } else {
      $t = (string) old('costs.0.direction', (string)($transportMeta['direction'] ?? 'one_way'));
      if($t === 'return') $t = 'two_way';
      $curDirection = in_array($t, ['one_way','two_way','round_trip'], true) ? $t : 'one_way';
  }

  $curUnit = (string) old('costs.0.unit_amount', (string)($transportMeta['unit_amount'] ?? ($ratesByTransport[$curTransport] ?? '0')));
  $byDateSaved = (array)($transportMeta['by_date'] ?? []);
  $segmentByDateId = $segmentByDateId ?? collect();

  $existingAllow = $travel->allowances ?? collect();
  $oldAllow = old('allowances', null);

  $existingFuel = ($existingAllow ?? collect())->firstWhere('allowance_type', 'fuel');
  $fuelUnitExisting  = $existingFuel?->unit_amount ?? null;
  $fuelUnitsExisting = $existingFuel?->units ?? null;
  $fuelDescExisting  = $existingFuel?->description ?? null;

  $hasAllowData = false;
  if(is_array($oldAllow)){
    foreach($oldAllow as $r){
      if(!empty($r['unit_amount']) || !empty($r['description'])){ $hasAllowData = true; break; }
    }
  } else {
    $hasAllowData = ($existingAllow->whereNotIn('allowance_type',['fuel'])->count() > 0);
  }

  $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

  $selectedMap = $selectedMap ?? [];
  $lastLogByDateId = $lastLogByDateId ?? [];

  $initialAllowances = [];
  if(is_array($oldAllow)){
    $initialAllowances = array_values($oldAllow);
  } else {
    $initialAllowances = ($existingAllow ?? collect())
      ->whereNotIn('allowance_type', ['fuel'])
      ->map(function($a){
        return [
          'id'            => (int)$a->id,
          'allowance_type'=> (string)$a->allowance_type,
          'unit_amount'   => $a->unit_amount,
          'units'         => $a->units,
          'description'   => $a->description,
        ];
      })->values()->all();
  }

  $fuelBaseRate = (float)($fuelBaseRate ?? (config('gdf.fuel.default_unit_amount') ?? 25000));

  $status = (string)($travel->status ?? 'draft');
  $statusLabel = strtoupper($status);
  $statusStyle = match($status){
    'draft' => ['bg'=>'var(--sitrav-info-light)','bd'=>'var(--sitrav-info)','tx'=>'var(--sitrav-text-primary)','icon'=>'📝','txt'=>'Borrador'],
    'returned' => ['bg'=>'var(--sitrav-warning-light)','bd'=>'var(--sitrav-warning)','tx'=>'#8a6d00','icon'=>'↩️','txt'=>'Devuelta'],
    'submitted' => ['bg'=>'var(--sitrav-accent-light)','bd'=>'var(--sitrav-accent)','tx'=>'var(--sitrav-text-primary)','icon'=>'📤','txt'=>'Enviada'],
    'pending_treasury' => ['bg'=>'var(--sitrav-accent-light)','bd'=>'var(--sitrav-accent)','tx'=>'var(--sitrav-text-primary)','icon'=>'🏦','txt'=>'Tesorería'],
    'approved' => ['bg'=>'var(--sitrav-success-light)','bd'=>'var(--sitrav-success)','tx'=>'var(--sitrav-text-primary)','icon'=>'✅','txt'=>'Aprobada'],
    'executed' => ['bg'=>'var(--sitrav-success-light)','bd'=>'var(--sitrav-success)','tx'=>'var(--sitrav-text-primary)','icon'=>'🏁','txt'=>'Ejecutada'],
    default => ['bg'=>'var(--sitrav-bg-secondary)','bd'=>'var(--sitrav-border)','tx'=>'var(--sitrav-text-secondary)','icon'=>'ℹ️','txt'=>$statusLabel],
  };

  $isActiveMode = fn($m) => in_array((string)$m, ['selected','reschedule'], true);

  $activeServer = 0;
  foreach($dates as $d){
    $dateId = (int)$d->id;
    $mode = 'excluded';
    if(!empty($selectedMap[$dateId])) $mode = 'selected';
    $action = $lastLogByDateId[$dateId]['action'] ?? null;
    if($action === 'date_reschedule') $mode = 'reschedule';
    if($isActiveMode($mode)) $activeServer++;
  }

  $tcAmount = (float)($transportCost->amount ?? 0);

  $usesMotoSaved = false;
  if(($transportMeta['same_for_all'] ?? true)){
    $usesMotoSaved = ((string)($transportMeta['transport'] ?? '') === 'motorcycle');
  } else {
    foreach((array)($transportMeta['by_date'] ?? []) as $cfg){
      if(($cfg['transport'] ?? null) === 'motorcycle'){ $usesMotoSaved = true; break; }
    }
  }

  $fuelOk = $existingFuel && ((float)($existingFuel->unit_amount ?? 0) > 0) && ((int)($existingFuel->units ?? 0) >= 1);

  $sendOkDates = $activeServer > 0;
  $sendOkTransport = $usesMotoSaved ? $fuelOk : ($tcAmount > 0);
  $sendOk = $canEdit && $sendOkDates && $sendOkTransport;

  $sendWhy = [];
  if(!$canEdit) $sendWhy[] = "Estado actual no permite edición/envío.";
  if(!$sendOkDates) $sendWhy[] = "Debes seleccionar o reprogramar al menos una fecha.";
  if(!$sendOkTransport){
    $sendWhy[] = $usesMotoSaved
      ? "Si usas moto, debes registrar gasolina (fuel) con valor > 0."
      : "Debes registrar transporte con valor total > 0.";
  }

  $transportTotalBD = (float)($transportTotalBD ?? 0);
  $fuelTotalBD = (float)($fuelTotalBD ?? 0);

  $areaName = $areaName ?? null;
  $budgetItemName = $budgetItemName ?? null;

  $tripMixed = (bool)($tripMixed ?? false);
  $tripTypesList = $tripTypesList ?? [];
@endphp

@push('styles')
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
  .status-pill{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .7rem;border-radius:999px;border:1px solid; font-weight:700; font-size:.85rem;}
  .checklist li{margin:.25rem 0;}
  .mini-note{color:var(--sitrav-text-muted);font-size:.85rem;}
</style>
@endpush

@section('content')
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
        <h3 class="mb-2" style="font-weight:700;">Editar Solicitud SITRAV</h3>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="sitrav-chip">Solicitud #{{ $travel->id }}</span>

          <span class="status-pill"
                style="background: {{ $statusStyle['bg'] }}; border-color: {{ $statusStyle['bd'] }}; color: {{ $statusStyle['tx'] }};">
            <span>{{ $statusStyle['icon'] }}</span>
            <span>{{ $statusStyle['txt'] }}</span>
          </span>

          <span class="sitrav-chip">Fuente: SIGAC</span>
          @if($program)
            <span class="sitrav-chip">ProgramRequest #{{ $program->id }}</span>
          @endif
          @if(!$hasMoto)
            <span class="sitrav-chip" style="background: var(--sitrav-warning-light); color:#8a6d00;">Moto no disponible</span>
          @endif
        </div>
      </div>

      <div class="d-flex gap-2">
        <a class="sitrav-btn sitrav-btn-ghost" href="{{ route('gdf.instructor.requests.index') }}">← Volver</a>

        <form method="POST" action="{{ route('gdf.instructor.sitrav.requests.submit', $travel->id) }}">
          @csrf
          <button class="sitrav-btn sitrav-btn-primary"
                  @disabled(!$sendOk)
                  title="{{ !$sendOk ? implode(' | ', $sendWhy) : 'Enviar a Apoyo' }}">
            Enviar a Apoyo
          </button>
        </form>
      </div>
    </div>

    @if($tripMixed)
      <div class="sitrav-alert sitrav-alert-warning">
        <strong>⚠️</strong> Esta solicitud tiene mezcla de <strong>trip_type</strong> en segmentos:
        <strong>{{ implode(', ', $tripTypesList) }}</strong>.
      </div>
    @endif

    <div class="row g-4">

      <div class="col-lg-4">
        <div class="sitrav-card">
          <div class="sitrav-card-header"><div class="fw-semibold">Resumen</div></div>
          <div class="sitrav-card-body">
            <div class="mb-3">
              <div class="sitrav-info-label">Área</div>
              <div class="sitrav-info-value">{{ $areaName ?: ($travel->area_id ?? '—') }}</div>
            </div>
            <div class="mb-3">
              <div class="sitrav-info-label">Rubro</div>
              <div class="sitrav-info-value">{{ $budgetItemName ?: ($travel->budget_item_id ?? '—') }}</div>
            </div>
            <div class="mb-3">
              <div class="sitrav-info-label">Origen</div>
              <div class="sitrav-info-value">{{ $travel->origin ?? 'CEFA Campoalegre' }}</div>
            </div>
            <div class="mb-3">
              <div class="sitrav-info-label">Destino</div>
              <div class="sitrav-info-value">{{ $travel->destination ?? '—' }}</div>
            </div>

            <hr class="sitrav-divider">

            <div class="d-flex justify-content-between"><span class="sitrav-info-label">Transporte</span><strong>{{ $fmtMoney($travel->total_transport) }}</strong></div>
            <div class="d-flex justify-content-between"><span class="sitrav-info-label">Viáticos</span><strong>{{ $fmtMoney($travel->total_per_diem) }}</strong></div>
            <div class="d-flex justify-content-between"><span class="sitrav-info-label">Otros</span><strong>{{ $fmtMoney($travel->total_other) }}</strong></div>
            <div class="d-flex justify-content-between mt-2"><span class="sitrav-info-label">Total</span><strong>{{ $fmtMoney($travel->total_amount) }}</strong></div>

            <hr class="sitrav-divider">

            <div class="fw-semibold mb-2">Checklist para enviar</div>
            <ul class="checklist mb-0 ps-3">
              <li>
                Fechas activas:
                @if($sendOkDates)
                  <strong style="color:var(--sitrav-success);">OK</strong>
                @else
                  <strong style="color:var(--sitrav-danger);">FALTA</strong>
                @endif
                <span class="mini-note">({{ $activeServer }} activas)</span>
              </li>

              <li>
                Transporte / Gasolina:
                @if($sendOkTransport)
                  <strong style="color:var(--sitrav-success);">OK</strong>
                @else
                  <strong style="color:var(--sitrav-danger);">FALTA</strong>
                @endif
                <div class="mini-note">
                  @if($usesMotoSaved)
                    Usa moto: requiere fuel > 0.
                  @else
                    Requiere total transporte > 0.
                  @endif
                </div>
              </li>

              <li>
                Estado editable:
                @if($canEdit)
                  <strong style="color:var(--sitrav-success);">OK</strong>
                @else
                  <strong style="color:var(--sitrav-danger);">BLOQUEADO</strong>
                @endif
                <div class="mini-note">Solo draft/returned.</div>
              </li>
            </ul>

            @if(!$sendOk)
              <div class="sitrav-alert sitrav-alert-warning mt-3 mb-0">
                <div class="fw-semibold mb-1">No puedes enviar aún:</div>
                <ul class="mb-0 ps-3">
                  @foreach($sendWhy as $w)<li>{{ $w }}</li>@endforeach
                </ul>
              </div>
            @endif
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="sitrav-card">
          <div class="sitrav-card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="fw-semibold">Costos</div>
              <div class="d-flex gap-2 flex-wrap">
                <span class="sitrav-badge">Activos: <strong id="daysActive">{{ $activeServer }}</strong></span>
                <span class="sitrav-badge">
                  Total transp ref: <strong id="transportTotalRef">{{ $transportTotalBD > 0 ? $fmtMoney($transportTotalBD) : '—' }}</strong>
                </span>
              </div>
            </div>
          </div>

          <div class="sitrav-card-body">

            <form method="POST" action="{{ route('gdf.instructor.sitrav.requests.storeCosts', $travel->id) }}" id="costsForm">
              @csrf
              <input type="hidden" name="costs[0][cost_type]" value="transport">

              <div class="sitrav-switch mb-4">
                <div class="form-check m-0">
                  <input class="form-check-input" type="checkbox" id="sameForAll"
                         name="cost[same_for_all]" value="1"
                         @checked($sameForAll) @disabled(!$canEdit)>
                  <label class="form-check-label" for="sameForAll" style="cursor:pointer;">
                    <strong>Usar el mismo transporte</strong> para todos los trayectos
                  </label>
                </div>
                <div class="mini-note mt-2">
                  Si desactivas, el “por fecha” se guarda cuando presionas <strong>Guardar fechas/decisiones</strong>.
                </div>
              </div>

              <div id="globalCostBlock" class="{{ $sameForAll ? '' : 'd-none' }}">
                <div class="row g-3">
                  <div class="col-md-4">
                    <label class="form-label sitrav-info-label">Tipo de transporte</label>
                    <select name="costs[0][transport]" class="form-select sitrav-form-control" id="transportSel" @disabled(!$canEdit)>
                      @foreach($transportOptions as $k=>$lbl)
                        @continue(!in_array($k, ['bus','motorcycle'], true))
                        <option value="{{ $k }}" @selected($curTransport===$k)>{{ $lbl }}</option>
                      @endforeach
                    </select>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label sitrav-info-label">Dirección</label>
                    <select name="costs[0][direction]" class="form-select sitrav-form-control" id="directionSel" @disabled(!$canEdit)>
                      <option value="one_way"    @selected($curDirection==='one_way')>Ida</option>
                      <option value="two_way"    @selected($curDirection==='two_way')>Solo vuelta</option>
                      <option value="round_trip" @selected($curDirection==='round_trip')>Ida/Vuelta</option>
                    </select>
                  </div>

                  <div class="col-md-4">
                    <label class="form-label sitrav-info-label">Valor (IDA)</label>
                    <input name="costs[0][unit_amount]" id="unitAmount"
                           class="form-control sitrav-form-control"
                           value="{{ $curUnit }}"
                           placeholder="Sugerido (editable)"
                           data-suggested="{{ (float)($ratesByTransport[$curTransport] ?? 0) }}"
                           @disabled(!$canEdit)>
                    <small class="form-text" style="color: var(--sitrav-text-muted);">
                      Sugerido: <strong id="suggestedHint">{{ (float)($ratesByTransport[$curTransport] ?? 0) > 0 ? $fmtMoney($ratesByTransport[$curTransport]) : '—' }}</strong>
                      <span class="ms-1" id="globalMotoLockHint" style="color:#8a6d00; display:none;">· Bloqueado por moto</span>
                    </small>
                  </div>
                </div>
              </div>

              <div id="perDateHint" class="{{ $sameForAll ? 'd-none' : '' }}">
                <div class="sitrav-hint">
                  <strong>Modo por fecha:</strong> define el transporte en la tabla de fechas (abajo) y guarda con “Guardar fechas/decisiones”.
                </div>
              </div>

              <div id="fuelPanel" class="mt-4 d-none">
                <div class="sitrav-card" style="border-color: var(--sitrav-warning);">
                  <div class="sitrav-card-header" style="background: var(--sitrav-warning-light);">
                    <div class="fw-semibold">Viático gasolina (obligatorio si usas moto)</div>
                    @if($existingFuel)
                      <div class="mini-note">Ya existe un registro fuel guardado.</div>
                    @endif
                  </div>
                  <div class="sitrav-card-body">
                    <div class="row g-3">
                      <div class="col-md-4">
                        <label class="form-label sitrav-info-label">Valor unitario</label>
                        <input class="form-control sitrav-form-control" name="fuel[unit_amount]" id="fuelUnitAmount"
                               value="{{ old('fuel.unit_amount', $fuelUnitExisting ?? ($fuelBaseRate > 0 ? $fuelBaseRate : '')) }}"
                               placeholder="Ej: 25000" @disabled(!$canEdit)>
                      </div>
                      <div class="col-md-3">
                        <label class="form-label sitrav-info-label">Unidades</label>
                        <input class="form-control sitrav-form-control" type="number" min="1"
                               name="fuel[units]" id="fuelUnits"
                               value="{{ old('fuel.units', $fuelUnitsExisting ?? 1) }}" @disabled(!$canEdit)>
                        <small class="form-text" style="color: var(--sitrav-text-muted);">
                          Mínimo (según moto activa): <strong id="fuelMinHint">—</strong>
                        </small>
                      </div>
                      <div class="col-md-5">
                        <label class="form-label sitrav-info-label">Descripción</label>
                        <input class="form-control sitrav-form-control" name="fuel[description]" id="fuelDesc"
                               value="{{ old('fuel.description', $fuelDescExisting ?? 'Gasolina - Moto') }}"
                               placeholder="Gasolina - Moto" @disabled(!$canEdit)>
                      </div>
                    </div>

                    <div class="sitrav-hint mt-3">
                      Se guarda como <strong>allowance_type = fuel</strong>.
                    </div>
                  </div>
                </div>
              </div>

              <div class="d-flex justify-content-end mt-4">
                <button class="sitrav-btn sitrav-btn-primary" @disabled(!$canEdit)>Guardar transporte</button>
              </div>
            </form>

            @if($isStaff)
              <hr class="sitrav-divider">

              <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                  <div class="fw-semibold">Viáticos (opcional · solo planta)</div>
                  <div class="mini-note">Puedes agregar varios, editarlos y eliminarlos.</div>
                </div>

                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" id="hasAllowances" @checked($hasAllowData) @disabled(!$canEdit)>
                  <label class="form-check-label" for="hasAllowances">Incluir</label>
                </div>
              </div>

              <div id="allowancesWrap" class="mt-3 {{ $hasAllowData ? '' : 'd-none' }}">
                <form method="POST"
                      action="{{ route('gdf.instructor.sitrav.requests.allowances.store', $travel->id) }}"
                      id="allowancesForm">
                  @csrf
                  <div id="allowancesList"></div>

                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <button type="button" class="sitrav-btn sitrav-btn-ghost" id="addAllowanceBtn" @disabled(!$canEdit)>
                      + Agregar viático
                    </button>

                    <button class="sitrav-btn sitrav-btn-primary" @disabled(!$canEdit)>
                      Guardar viáticos
                    </button>
                  </div>

                  <div class="mini-note mt-2">
                    Se guardan en <code>travel_allowances</code>. Fuel va aparte.
                  </div>
                </form>
              </div>
            @endif

          </div>
        </div>
      </div>
    </div>

    <div class="mt-4 sitrav-card">
      <div class="sitrav-card-header">
        <div>
          <div class="fw-semibold mb-1">Fechas del Programa (SIGAC)</div>
          <small style="color: var(--sitrav-text-muted);">
            Selecciono / Reprogramo / Excluyo. Reprogramo es local SITRAV.
          </small>
        </div>
      </div>

      <div class="sitrav-card-body p-0">
        <form method="POST" action="{{ route('gdf.instructor.sitrav.requests.storeDates', $travel->id) }}" id="datesForm">
          @csrf

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
                @forelse($dates as $d)
                  @php
                    $dateId = (int)$d->id;

                    $defaultStart = $d->date.'T'.substr((string)$d->start_time,0,5);
                    $defaultEnd   = $d->date.'T'.substr((string)$d->end_time,0,5);

                    $mode = 'excluded';
                    if(!empty($selectedMap[$dateId])) $mode = 'selected';

                    $payload = $lastLogByDateId[$dateId]['payload'] ?? [];
                    $action  = $lastLogByDateId[$dateId]['action'] ?? null;

                    if($action === 'date_reschedule') $mode = 'reschedule';
                    elseif($action === 'date_excluded' && empty($selectedMap[$dateId])) $mode = 'excluded';

                    $oldMode   = old("decisions.$dateId.mode", $mode);
                    $oldReason = old("decisions.$dateId.reason", (string)($payload['reason'] ?? ''));
                    $oldPS     = old("decisions.$dateId.proposed_start", (string)($payload['proposed_start'] ?? $defaultStart));
                    $oldPE     = old("decisions.$dateId.proposed_end",   (string)($payload['proposed_end']   ?? $defaultEnd));

                    $seg = null;
                    if($segmentByDateId instanceof \Illuminate\Support\Collection){
                      $seg = $segmentByDateId->get($dateId);
                    } elseif (is_array($segmentByDateId)) {
                      $seg = $segmentByDateId[$dateId] ?? null;
                    }

                    $savedRow = (array)($byDateSaved[(string)$dateId] ?? []);

                    $rowTransport = old("costs_by_date.$dateId.transport",
                      (string)(
                        $seg->transport ?? $seg->transport_type ?? ($savedRow['transport'] ?? $curTransport)
                      )
                    );
                    if(!in_array($rowTransport, ['bus','motorcycle'], true)) $rowTransport = 'bus';
                    if(!$hasMoto && $rowTransport === 'motorcycle') $rowTransport = 'bus';

                    $rowDir = 'one_way';
                    if($seg && isset($seg->trip_type)) {
                        $t = (string)$seg->trip_type;
                        $rowDir = in_array($t, ['one_way','two_way','round_trip'], true) ? $t : 'one_way';
                    } else {
                        $t = (string) old("costs_by_date.$dateId.direction", (string)($savedRow['direction'] ?? $curDirection));
                        if($t === 'return') $t = 'two_way';
                        $rowDir = in_array($t, ['one_way','two_way','round_trip'], true) ? $t : 'one_way';
                    }

                    $rowUnit = old("costs_by_date.$dateId.unit_amount",
                      (string)(
                        $seg->unit_amount ?? $seg->unit_cost ?? ($savedRow['unit_amount'] ?? ($ratesByTransport[$rowTransport] ?? 0))
                      )
                    );

                    $isRowMoto = ((string)$rowTransport === 'motorcycle');
                  @endphp

                  <tr data-date-id="{{ $dateId }}">
                    <td>
                      <select class="form-select sitrav-form-control decisionMode"
                              name="decisions[{{ $dateId }}][mode]"
                              data-date-id="{{ $dateId }}" @disabled(!$canEdit)>
                        <option value="selected"   @selected($oldMode==='selected')>✓ Selecciono</option>
                        <option value="reschedule" @selected($oldMode==='reschedule')>↻ Reprogramo</option>
                        <option value="excluded"   @selected($oldMode==='excluded')>✕ Excluyo</option>
                      </select>
                    </td>

                    <td>
                      <div class="fw-semibold">{{ $d->date }}</div>
                      <small style="color: var(--sitrav-text-muted);">{{ substr((string)$d->start_time,0,5) }} - {{ substr((string)$d->end_time,0,5) }}</small>
                    </td>

                    <td>
                      <input class="form-control sitrav-form-control reasonInput"
                             name="decisions[{{ $dateId }}][reason]"
                             value="{{ $oldReason }}"
                             placeholder="Obligatorio si excluyes o reprogramas"
                             data-date-id="{{ $dateId }}" @disabled(!$canEdit)>
                    </td>

                    <td>
                      <div class="row g-2">
                        <div class="col-6">
                          <input type="datetime-local" class="form-control sitrav-form-control psInput"
                                 name="decisions[{{ $dateId }}][proposed_start]" value="{{ $oldPS }}"
                                 data-date-id="{{ $dateId }}" @disabled(!$canEdit)>
                        </div>
                        <div class="col-6">
                          <input type="datetime-local" class="form-control sitrav-form-control peInput"
                                 name="decisions[{{ $dateId }}][proposed_end]" value="{{ $oldPE }}"
                                 data-date-id="{{ $dateId }}" @disabled(!$canEdit)>
                        </div>
                      </div>
                      <small style="color: var(--sitrav-text-muted);">Solo si reprogramas</small>
                    </td>

                    <td class="colPerDate">
                      <select class="form-select sitrav-form-control perDateField perDateTransport"
                              name="costs_by_date[{{ $dateId }}][transport]" @disabled(!$canEdit)>
                        @foreach($transportOptions as $k=>$lbl)
                          @continue(!in_array($k, ['bus','motorcycle'], true))
                          <option value="{{ $k }}" @selected($rowTransport===$k)>{{ $lbl }}</option>
                        @endforeach
                      </select>
                    </td>

                    <td class="colPerDate">
                      <select class="form-select sitrav-form-control perDateField perDateDirection"
                              name="costs_by_date[{{ $dateId }}][direction]" @disabled(!$canEdit)>
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
                             data-base-rate="{{ (float)($ratesByTransport[$rowTransport] ?? 0) }}"
                             @disabled(!$canEdit)>
                      <small class="form-text" style="color: var(--sitrav-text-muted);">
                        Sugerido: <strong class="rowSuggested">{{ (float)($ratesByTransport[$rowTransport] ?? 0) > 0 ? $fmtMoney($ratesByTransport[$rowTransport]) : '—' }}</strong>
                        <span class="ms-1 motoLockHint" style="color:#8a6d00; display: {{ $isRowMoto ? 'inline' : 'none' }};">· Bloqueado por moto</span>
                      </small>
                    </td>
                  </tr>
                @empty
                  <tr><td colspan="7" class="text-muted p-4">No hay fechas registradas en SIGAC.</td></tr>
                @endforelse
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-end p-3" style="background: var(--sitrav-bg-secondary); border-top: 1px solid var(--sitrav-border);">
            <button class="sitrav-btn sitrav-btn-primary" @disabled(!$canEdit)>Guardar fechas/decisiones</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</div>

<template id="allowanceRowTpl">
  <div class="row g-2 mb-2 allowanceRow align-items-end">
    <input type="hidden" data-name="id">
    <div class="col-md-4">
      <label class="form-label">Tipo</label>
      <select class="form-select sitrav-form-control" data-name="allowance_type">
        <option value="lodging">Hospedaje</option>
        <option value="meals">Alimentación</option>
        <option value="per_diem">Por días</option>
        <option value="other">Otro</option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Valor unitario</label>
      <input class="form-control sitrav-form-control" data-name="unit_amount" placeholder="Ej: 35000">
    </div>
    <div class="col-md-2">
      <label class="form-label">Unidades</label>
      <input class="form-control sitrav-form-control" data-name="units" type="number" min="1" value="1">
    </div>
    <div class="col-md-3 d-flex gap-2">
      <div class="flex-grow-1">
        <label class="form-label">Descripción</label>
        <input class="form-control sitrav-form-control" data-name="description" placeholder="Opcional">
      </div>
      <button type="button" class="sitrav-btn sitrav-btn-ghost btnRemove">Eliminar</button>
    </div>
  </div>
</template>
@endsection

@push('scripts')
<script>
  const transportRates = @json($ratesByTransport);
  const initialAllowances = @json($initialAllowances);

  const transportTotalBD = {{ $transportTotalBD }};
  const fuelTotalBD = {{ $fuelTotalBD }};

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

  function syncGlobalUnitLock(){
    const sel = document.getElementById('transportSel');
    const unit = document.getElementById('unitAmount');
    const hint = document.getElementById('globalMotoLockHint');
    const hinted = document.getElementById('suggestedHint');
    if(!sel || !unit) return;

    const isMoto = (sel.value || '') === 'motorcycle';
    const suggestedRate = Number(transportRates[sel.value] || 0);

    unit.setAttribute('data-suggested', String(suggestedRate));
    if(hinted) hinted.textContent = suggestedRate > 0 ? fmtCOP(suggestedRate) : '—';

    if(isMoto){
      unit.value = '0';
      unit.readOnly = true;
      if(hint) hint.style.display = 'inline';
    } else {
      unit.readOnly = false;
      if(hint) hint.style.display = 'none';
      const current = moneyToFloatJS(unit.value);
      if(current <= 0 && suggestedRate > 0) unit.value = String(suggestedRate);
    }
  }

  function setPerDateUnitLock(tr){
    if(!tr) return;
    const tSel = tr.querySelector('.perDateTransport');
    const unit = tr.querySelector('.perDateUnit');
    const hint = tr.querySelector('.motoLockHint');
    const sugEl= tr.querySelector('.rowSuggested');
    if(!tSel || !unit) return;

    const t = (tSel.value || 'bus').toString();
    const isMoto = (t === 'motorcycle');
    const suggested = Number(transportRates[t] || 0);

    unit.setAttribute('data-base-rate', String(suggested));
    if(sugEl) sugEl.textContent = suggested > 0 ? fmtCOP(suggested) : '—';

    if(isMoto){
      unit.value = '0';
      unit.readOnly = true;
      if(hint) hint.style.display = 'inline';
    } else {
      unit.readOnly = false;
      if(hint) hint.style.display = 'none';
      const current = moneyToFloatJS(unit.value);
      if(current <= 0 && suggested > 0) unit.value = String(suggested);
    }
  }

  function refreshAllPerDateLocks(){
    document.querySelectorAll('tbody tr[data-date-id]').forEach(tr => setPerDateUnitLock(tr));
  }

  function anyMotoSelected(){
    const sameAll = document.getElementById('sameForAll')?.checked;
    if(sameAll){
      return (document.getElementById('transportSel')?.value || '') === 'motorcycle';
    }
    let any = false;
    document.querySelectorAll('tbody tr[data-date-id]').forEach(tr=>{
      const dateId = tr.getAttribute('data-date-id');
      const mode = document.querySelector(`.decisionMode[data-date-id="${dateId}"]`)?.value || 'selected';
      if(!isActiveMode(mode)) return;
      const tSel = tr.querySelector('.perDateTransport');
      if(tSel && (tSel.value || '') === 'motorcycle') any = true;
    });
    return any;
  }

  function countMotoActiveTrips(){
    const sameAll = document.getElementById('sameForAll')?.checked;
    if(sameAll){
      return ((document.getElementById('transportSel')?.value || '') === 'motorcycle') ? getActiveDays() : 0;
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
      const cur = parseInt(units.value || '0', 10) || 0;
      if(cur < min) units.value = String(min);
    } else {
      if(hint) hint.textContent = '—';
    }
  }

  function recalcTransportRef(){
    const sameAll = document.getElementById('sameForAll')?.checked;
    let calculatedTotal = 0;

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
      calculatedTotal = Math.max(0, unit) * getMultiplierFrom(dir) * Math.max(0, getActiveDays());
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

        calculatedTotal += Math.max(0, unit) * getMultiplierFrom(dir);
      });
    }

    let displayTotal = calculatedTotal;

    if (calculatedTotal === 0 && transportTotalBD > 0) {
      displayTotal = transportTotalBD;
    }

    if (anyMotoSelected() && fuelTotalBD > 0) {
      displayTotal += fuelTotalBD;
    }

    document.getElementById('daysActive').textContent = String(getActiveDays());
    document.getElementById('transportTotalRef').textContent = displayTotal > 0 ? fmtCOP(displayTotal) : '—';
  }

  const delIds = new Set();

  function addAllowanceRow(row){
    const tpl = document.getElementById('allowanceRowTpl');
    const list = document.getElementById('allowancesList');
    if(!tpl || !list) return;

    const node = tpl.content.cloneNode(true);
    const wrap = node.querySelector('.allowanceRow');

    const hid = wrap.querySelector('[data-name="id"]');
    const t  = wrap.querySelector('[data-name="allowance_type"]');
    const ua = wrap.querySelector('[data-name="unit_amount"]');
    const un = wrap.querySelector('[data-name="units"]');
    const ds = wrap.querySelector('[data-name="description"]');

    if(hid) hid.value = row?.id ?? '';
    if(t)  t.value  = row?.allowance_type ?? 'meals';
    if(ua) ua.value = row?.unit_amount ?? '';
    if(un) un.value = row?.units ?? 1;
    if(ds) ds.value = row?.description ?? '';

    wrap.querySelector('.btnRemove')?.addEventListener('click', () => {
      const currentId = (hid?.value || '').toString().trim();
      if(currentId) delIds.add(currentId);
      wrap.remove();
      renumberAllowances();
      syncDeleteIdsField();
    });

    list.appendChild(node);
  }

  function syncDeleteIdsField(){
    const form = document.getElementById('allowancesForm');
    if(!form) return;
    form.querySelectorAll('input[name="delete_ids[]"]').forEach(n => n.remove());
    [...delIds].forEach(id => {
      const inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'delete_ids[]';
      inp.value = id;
      form.appendChild(inp);
    });
  }

  function renumberAllowances(){
    document.querySelectorAll('#allowancesList .allowanceRow').forEach((r, i) => {
      r.querySelector('[data-name="id"]').setAttribute('name', `allowances[${i}][id]`);
      r.querySelector('[data-name="allowance_type"]').setAttribute('name', `allowances[${i}][allowance_type]`);
      r.querySelector('[data-name="unit_amount"]').setAttribute('name',    `allowances[${i}][unit_amount]`);
      r.querySelector('[data-name="units"]').setAttribute('name',          `allowances[${i}][units]`);
      r.querySelector('[data-name="description"]').setAttribute('name',    `allowances[${i}][description]`);
    });
  }

  function mountAllowances(){
    const list = document.getElementById('allowancesList');
    if(!list) return;
    list.innerHTML = '';
    delIds.clear();

    if(!Array.isArray(initialAllowances) || initialAllowances.length === 0){
      addAllowanceRow({ allowance_type:'meals', unit_amount:'', units:1, description:'' });
    } else {
      initialAllowances.forEach(r => addAllowanceRow(r));
    }
    renumberAllowances();
    syncDeleteIdsField();
  }

  (function init(){
    document.querySelectorAll('.decisionMode').forEach(sel=>{
      toggleRow(sel.dataset.dateId, sel.value);
      sel.addEventListener('change', e=>{
        toggleRow(sel.dataset.dateId, e.target.value);
        recalcTransportRef();
        toggleFuelPanel();
      });
    });

    document.getElementById('sameForAll')?.addEventListener('change', ()=>{
      applyPerDateVisibility();
      refreshAllPerDateLocks();
      syncGlobalUnitLock();
      recalcTransportRef();
      toggleFuelPanel();
    });

    document.getElementById('transportSel')?.addEventListener('change', ()=>{
      syncGlobalUnitLock();
      recalcTransportRef();
      toggleFuelPanel();
    });

    document.getElementById('directionSel')?.addEventListener('change', ()=>{
      recalcTransportRef();
      toggleFuelPanel();
    });

    document.getElementById('unitAmount')?.addEventListener('input', ()=>{
      recalcTransportRef();
      toggleFuelPanel();
    });

    document.querySelectorAll('.perDateField').forEach(el=>{
      el.addEventListener('change', ()=>{
        const tr = el.closest('tr');
        if(tr) setPerDateUnitLock(tr);
        recalcTransportRef();
        toggleFuelPanel();
      });
      el.addEventListener('input', ()=>{
        recalcTransportRef();
        toggleFuelPanel();
      });
    });

    const chk = document.getElementById('hasAllowances');
    const wrap = document.getElementById('allowancesWrap');
    if(chk && wrap){
      chk.addEventListener('change', () => {
        const on = chk.checked;
        wrap.classList.toggle('d-none', !on);
        if(on) mountAllowances();
        else document.getElementById('allowancesList')?.replaceChildren();
      });
      if(chk.checked) mountAllowances();
    }

    document.getElementById('addAllowanceBtn')?.addEventListener('click', ()=>{
      addAllowanceRow({ allowance_type:'other', unit_amount:'', units:1, description:'' });
      renumberAllowances();
      syncDeleteIdsField();
    });

    applyPerDateVisibility();
    refreshAllPerDateLocks();
    syncGlobalUnitLock();
    recalcTransportRef();
    toggleFuelPanel();
  })();
</script>
@endpush
