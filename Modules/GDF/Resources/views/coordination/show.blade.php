{{-- Modules/GDF/Resources/views/coordination/show.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Coordinación · Solicitud #'.$r->id)

@php
  use Carbon\Carbon;
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;

  use Modules\GDF\Status\TravelStatus;
  use Modules\GDF\Status\AllowanceStatus;
  use Modules\GDF\Status\AllowanceType;
  use Modules\GDF\Services\GdfCostFormatter;

  $money = fn($n) => '$ '.number_format((float)($n ?? 0), 0, ',', '.');

  $path = request()->path();
  $areaKey = $areaKey ?? (str_contains($path,'campesena') ? 'campesena' : 'academic');
  $routePrefix = $routePrefix ?? ('gdf.' . ($areaKey === 'campesena' ? 'campesena' : 'academic'));

  $module = strtolower(trim((string)($r->module ?? 'gdf')));
  $isSitrav = $module === 'sitrav';
  $moduleLabel = $isSitrav ? 'SITRAV' : 'GDF';

  $person = $r->person ?? null;
  $personName = trim(
      ($person->first_name ?? '') . ' ' .
      ($person->first_last_name ?? '') . ' ' .
      ($person->second_last_name ?? '')
  );
  if ($personName === '') $personName = (string)($r->applicant_name ?? $r->instructor_name ?? '—');
  $docNum = (string)($person->document_number ?? $r->document_number ?? '—');

  $areaName = (string)($r->area->name ?? '—');
  $munName  = (string)($r->municipality->name ?? $r->municipality_name ?? '—');
  $vilName  = (string)($r->village->name ?? $r->village_name ?? '');

  // Origen/destino (fallback a SIGAC si SITRAV)
  $origin = (string)($r->origin ?? '');
  $dest   = (string)($r->destination ?? '');

  if ($isSitrav && empty($dest) && isset($pr) && $pr) {
    $dest = (string)($pr->address ?? '');
    if ($dest === '' && isset($pr->municipality) && $pr->municipality) $dest = (string)$pr->municipality->name;
    if ($dest === '' && isset($pr->village) && $pr->village) $dest = (string)$pr->village->name;
  }
  if ($origin === '') $origin = '—';
  if ($dest === '')   $dest   = '—';

  $requestType = (string)($r->request_type ?? '—');
  $personType  = (string)($r->person_type ?? '—');
  $radicado    = (string)($r->radicado_code ?? '—');

  $statusLabel = TravelStatus::label($r->status ?? null);
  $statusBadge = TravelStatus::badge($r->status ?? null);

  $allowances = $allowances ?? collect();
  $costs      = $costs ?? collect();
  $segments   = $segments ?? collect();

  $costTotal  = (float)($costTotal ?? ($costs?->sum('amount') ?? 0));
  $allowTotal = (float)($allowTotal ?? 0);
  $grandTotal = (float)($grandTotal ?? ($allowTotal + $costTotal));

  $canEditAllowances = ((string)($r->status ?? '')) === 'approved_by_treasury';

  // Rubro
  $rubroDisplay = '—';
  if ($isSitrav && isset($sigacRubro) && $sigacRubro) {
    $rubroDisplay = trim(($sigacRubro->code ?? '').' - '.($sigacRubro->name ?? 'Rubro'));
  } elseif (isset($r->budgetItem) && $r->budgetItem) {
    $rubroDisplay = trim(($r->budgetItem->code ?? '').' - '.($r->budgetItem->name ?? 'Rubro'));
  } elseif (!empty($r->budget_item_id)) {
    $rubroDisplay = 'Rubro #'.(int)$r->budget_item_id;
  }

  // Programa + caracterización
  $programName = '—';
  $programMeta = [];
  if ($isSitrav && isset($program) && $program) {
    $programName = (string)($program->name ?? '—');
    $programMeta[] = !empty($program->sofia_code) ? ('SOFIA: '.$program->sofia_code) : null;
    $programMeta[] = !empty($program->training_type) ? ('Tipo: '.$program->training_type) : null;
    $programMeta[] = !empty($program->program_type) ? ('Nivel: '.$program->program_type) : null;
    $programMeta[] = !empty($program->modality) ? ('Modalidad: '.$program->modality) : null;
    $programMeta[] = !empty($program->priority_bets) ? ('Apuesta: '.$program->priority_bets) : null;
    $programMeta = array_values(array_filter($programMeta));
  }
  $charDate = ($isSitrav && isset($pr) && $pr) ? ($pr->date_characterization ?? null) : null;
  $charStr  = $charDate ? Carbon::parse($charDate)->format('Y-m-d') : '—';

  // Docs
  $docs = $docs ?? collect();
  $docsSource = $docsSource ?? null;

  // Rutas
  $hasApproveRequest = Route::has($routePrefix.'.review.approve');
  $hasReturnRequest  = Route::has($routePrefix.'.review.return');
  $hasRejectRequest  = Route::has($routePrefix.'.review.reject');

  $hasApproveAllowance = Route::has($routePrefix.'.review.allowances.approve');
  $hasRejectAllowance  = Route::has($routePrefix.'.review.allowances.reject');
  $hasUpdateAllowance  = Route::has($routePrefix.'.review.allowances.update');

  // Helper URL doc: compatible con local/prod y rutas guardadas con/ sin prefijo.
  $docUrl = function(string $raw) {
    $raw = trim($raw);
    if ($raw === '') return null;

    // Si ya viene con URL absoluta, úsala tal cual.
    if (Str::startsWith($raw, ['http://','https://'])) return $raw;

    // Normaliza separadores y quita barras iniciales.
    $raw = str_replace('\\', '/', ltrim($raw, '/'));

    // Si viene como "public/..." o "storage/...", quita prefijo para evitar duplicados.
    if (Str::startsWith($raw, 'public/'))  $raw = substr($raw, 7);
    if (Str::startsWith($raw, 'storage/')) $raw = substr($raw, 8);

    // Usa el dominio actual (local o prod), sin hardcode.
    return asset('storage/'.$raw);
  };

  $dt = function($v){
    if (!$v) return '—';
    try { return Carbon::parse($v)->format('Y-m-d H:i'); } catch (\Throwable $e) { return (string)$v; }
  };

  $docBadge = function($name){
    $ext = strtolower(pathinfo((string)$name, PATHINFO_EXTENSION));
    return match($ext){
      'pdf' => 'bg-danger',
      'jpg','jpeg','png','webp' => 'bg-success',
      'doc','docx' => 'bg-primary',
      'xls','xlsx','csv' => 'bg-success',
      default => 'bg-secondary',
    };
  };
@endphp

@section('content')
<div class="container py-4">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <div class="text-muted small">{{ $moduleLabel }} / Coordinación / Revisión</div>

      <div class="d-flex align-items-center gap-2 flex-wrap">
        <h4 class="mb-0">Solicitud #{{ $r->id }}</h4>
        <span class="badge bg-{{ $statusBadge }}">{{ $statusLabel }}</span>
        @if($radicado !== '—')
          <span class="badge bg-secondary">Radicado: {{ $radicado }}</span>
        @endif
        <span class="badge bg-light text-dark border">Área: {{ $areaName }}</span>
      </div>

      <div class="text-muted small mt-1">
        Solicitante: <strong>{{ $personName }}</strong>
        <span class="mx-2">•</span>
        Documento: <strong>{{ $docNum }}</strong>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary"
         href="{{ route($routePrefix.'.review', ['tab'=>request('tab','all'), 'q'=>request('q','')]) }}">
        Volver
      </a>
    </div>
  </div>

  {{-- Flash --}}
  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  @if ($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- Resumen económico --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total viáticos</div>
          <div class="h5 mb-0">{{ $money($allowTotal) }}</div>
          <div class="text-muted small mt-1">Aprobado si existe; si no, calculado.</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total costos</div>
          <div class="h5 mb-0">{{ $money($costTotal) }}</div>
          <div class="text-muted small mt-1">Transporte y otros costos.</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total general</div>
          <div class="h5 mb-0">{{ $money($grandTotal) }}</div>
          <div class="text-muted small mt-1">Viáticos + costos.</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Datos principales --}}
  <div class="card mb-3 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Datos de la solicitud</strong>
      <span class="text-muted small">Verifique origen/destino, rubro y (si aplica) programa.</span>
    </div>
    <div class="card-body">
      <div class="row g-3">

        <div class="col-md-3">
          <div class="text-muted small">Módulo</div>
          <div class="fw-semibold">{{ $moduleLabel }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Tipo de solicitud</div>
          <div class="fw-semibold">{{ $requestType }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Tipo de persona</div>
          <div class="fw-semibold">{{ $personType }}</div>
        </div>

        <div class="col-md-3">
          <div class="text-muted small">Rubro</div>
          <div class="fw-semibold">{{ $rubroDisplay }}</div>
          @if($isSitrav && isset($sigacRubro) && $sigacRubro)
            <div class="text-muted small">Fuente: SIGAC</div>
          @endif
        </div>

        <div class="col-md-6">
          <div class="text-muted small">Origen</div>
          <div class="fw-semibold">{{ $origin }}</div>
        </div>

        <div class="col-md-6">
          <div class="text-muted small">Destino</div>
          <div class="fw-semibold">
            {{ $dest }}
            @if($munName !== '—')
              <span class="text-muted">({{ $munName }}@if($vilName !== ''), {{ $vilName }}@endif)</span>
            @endif
          </div>
        </div>

        @if($isSitrav)
          <div class="col-md-8">
            <div class="text-muted small">Programa (SIGAC)</div>
            <div class="fw-semibold">{{ $programName }}</div>
            @if(!empty($programMeta))
              <div class="text-muted small mt-1">{{ implode(' · ', $programMeta) }}</div>
            @endif
          </div>

          <div class="col-md-4">
            <div class="text-muted small">Caracterización (SIGAC)</div>
            <div class="fw-semibold">{{ $charStr }}</div>
            @if(isset($pr) && $pr && !empty($pr->hours))
              <div class="text-muted small">Horas: <span class="fw-semibold text-dark">{{ (int)$pr->hours }}</span></div>
            @endif
            @if(isset($pr) && $pr && !empty($pr->state))
              <div class="text-muted small">Estado SIGAC: <span class="fw-semibold text-dark">{{ $pr->state }}</span></div>
            @endif
          </div>
        @endif

        <div class="col-md-12">
          <div class="text-muted small">Observaciones</div>
          <div>{{ $r->notes ?? $r->description ?? '—' }}</div>
        </div>

      </div>
    </div>
  </div>

  {{-- Documentos --}}
  <div class="card mb-3 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Documentos</strong>
      <span class="text-muted small">
        @if($docsSource === 'sigac') Adjuntos desde SIGAC (program_request_documents)
        @elseif($docsSource === 'gdf') Adjuntos del GDF (travel_request_documents)
        @else Sin fuente definida
        @endif
      </span>
    </div>

    <div class="card-body">
      @if($docs && count($docs))
        <div class="list-group">
          @foreach($docs as $d)
            @php
              $name = (string)($d->name ?? ('Documento #'.($d->id ?? '')));
              $raw  = (string)($d->path ?? '');
              $url  = $docUrl($raw);
              $badge = $docBadge($name);
            @endphp

            <div class="list-group-item d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div class="me-2" style="min-width:260px">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge {{ $badge }}">{{ strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'DOC') }}</span>
                  <div class="fw-semibold">{{ $name }}</div>
                </div>
                @if($raw !== '')
                  <div class="text-muted small mt-1" style="word-break:break-all">{{ $raw }}</div>
                @endif
                @if(!empty($d->created_at))
                  <div class="text-muted small">Subido: {{ $dt($d->created_at) }}</div>
                @endif
              </div>

              <div class="d-flex gap-2">
                @if($url)
                  <a class="btn btn-outline-primary btn-sm" target="_blank" href="{{ $url }}">Ver</a>
                  <a class="btn btn-outline-secondary btn-sm" target="_blank" href="{{ $url }}" download>Descargar</a>
                @else
                  <span class="text-muted small">Sin ruta</span>
                @endif
              </div>
            </div>
          @endforeach
        </div>
      @else
        <div class="text-muted">No hay documentos adjuntos.</div>
      @endif
    </div>
  </div>

  {{-- Costos --}}
  <div class="card mb-3 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Costos</strong>
      <span class="text-muted small">Detalle de transporte y otros costos.</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Detalle</th>
            <th class="text-end">Valor</th>
          </tr>
        </thead>
        <tbody>
        @forelse($costs as $c)
          @php
            $type = strtolower((string)($c->cost_type ?? ''));
            $isTransport = $type === 'transport';
            $detail = (string)($c->description ?? '');
          @endphp
          <tr>
            <td>#{{ $c->id }}</td>
            <td class="fw-semibold">{{ $isTransport ? 'Transporte' : ($type !== '' ? strtoupper($type) : 'N/D') }}</td>
            <td>
              @if($isTransport && $detail !== '')
                {!! GdfCostFormatter::transport($detail) !!}
              @else
                {{ $detail !== '' ? $detail : '—' }}
              @endif
            </td>
            <td class="text-end fw-semibold">{{ $money($c->amount ?? 0) }}</td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">No hay costos registrados.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Segmentos --}}
  <div class="card mb-3 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Segmentos / trayectos</strong>
      <span class="text-muted small">Informativo.</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>#</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Medio</th>
            <th class="text-end">Costo</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
        @forelse($segments as $s)
          @php
            $cancel = (int)($s->is_cancelled ?? 0) === 1;
            $tType = (string)($s->transport_type ?? '—');
          @endphp
          <tr class="{{ $cancel ? 'table-light' : '' }}">
            <td>#{{ $s->id }}</td>
            <td>{{ $s->origin ?? $origin }}</td>
            <td>{{ $s->destination ?? $dest }}</td>
            <td>{{ $tType }}</td>
            <td class="text-end">{{ $money($s->transport_cost ?? 0) }}</td>
            <td>
              @if($cancel)
                <span class="badge bg-secondary">Cancelado</span>
              @else
                <span class="badge bg-success">Activo</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No hay segmentos.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Viáticos --}}
  <div class="card mb-3 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <strong>Viáticos</strong>
      <span class="text-muted small">
        @if($canEditAllowances) Revise y decida (aprobar/rechazar/modificar).
        @else Vista informativa (no está en estado de revisión).
        @endif
      </span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px">#</th>
            <th>Concepto</th>
            <th class="text-end" style="width:140px">Calculado</th>
            <th class="text-end" style="width:140px">Aprobado</th>
            <th style="width:140px">Estado</th>
            <th style="width:520px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse($allowances as $a)
          @php
            $typeLabel = AllowanceType::label($a->allowance_type ?? $a->type ?? null);
            $stLabel   = AllowanceStatus::label($a->status ?? null);
            $stBadge   = AllowanceStatus::badge($a->status ?? null);

            $calc = (float)($a->calculated_amount ?? 0);
            $appr = $a->approved_amount;
            $apprNum = ($appr === null || $appr === '') ? null : (float)$appr;

            $collapseId = 'mod_'.$a->id;
          @endphp

          <tr>
            <td>#{{ $a->id }}</td>

            <td>
              <div class="fw-semibold">{{ $typeLabel }}</div>
              @if(!empty($a->description))
                <div class="text-muted small">{{ $a->description }}</div>
              @endif
              @if(!empty($a->unit_amount) || !empty($a->units))
                <div class="text-muted small">
                  Unidad: {{ $money($a->unit_amount ?? 0) }} · Cantidad: {{ (int)($a->units ?? 0) }}
                </div>
              @endif
            </td>

            <td class="text-end">{{ $money($calc) }}</td>

            <td class="text-end">
              @if($apprNum !== null)
                <span class="fw-semibold">{{ $money($apprNum) }}</span>
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            <td><span class="badge bg-{{ $stBadge }}">{{ $stLabel }}</span></td>

            <td>
              @if($canEditAllowances)
                <div class="d-flex flex-wrap gap-2">

                  @if($hasApproveAllowance)
                    <form method="POST" action="{{ route($routePrefix.'.review.allowances.approve', ['id'=>$r->id,'allowanceId'=>$a->id]) }}">
                      @csrf
                      <button class="btn btn-sm btn-success" type="submit">Aprobar</button>
                    </form>
                  @endif

                  @if($hasRejectAllowance)
                    <form method="POST"
                          action="{{ route($routePrefix.'.review.allowances.reject', ['id'=>$r->id,'allowanceId'=>$a->id]) }}"
                          class="d-flex flex-wrap gap-2">
                      @csrf
                      <input class="form-control form-control-sm" name="comment" placeholder="Motivo (obligatorio)" required style="max-width:260px">
                      <button class="btn btn-sm btn-outline-danger" type="submit">Rechazar</button>
                    </form>
                  @endif

                  @if($hasUpdateAllowance)
                    <button class="btn btn-sm btn-primary"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#{{ $collapseId }}"
                            aria-expanded="false"
                            aria-controls="{{ $collapseId }}">
                      Modificar
                    </button>
                  @endif

                </div>

                @if($hasUpdateAllowance)
                  <div class="collapse mt-2" id="{{ $collapseId }}">
                    <div class="border rounded p-2 bg-light">
                      <form method="POST"
                            action="{{ route($routePrefix.'.review.allowances.update', ['id'=>$r->id,'allowanceId'=>$a->id]) }}"
                            class="d-flex flex-wrap gap-2 align-items-end">
                        @csrf
                        <div>
                          <label class="form-label small mb-1">Nuevo valor</label>
                          <input class="form-control form-control-sm text-end"
                                 name="calculated_amount"
                                 value="{{ $apprNum !== null ? $apprNum : $calc }}"
                                 style="max-width:180px"
                                 required>
                        </div>

                        <div class="flex-grow-1" style="min-width:260px">
                          <label class="form-label small mb-1">Justificación</label>
                          <input class="form-control form-control-sm"
                                 name="comment"
                                 placeholder="Ej: Ajuste por soporte / evidencia"
                                 required>
                        </div>

                        <div>
                          <button class="btn btn-sm btn-success" type="submit">Guardar cambios</button>
                        </div>
                      </form>
                      <div class="text-muted small mt-1">
                        Al guardar, el viático queda en estado aprobado (según tu controlador).
                      </div>
                    </div>
                  </div>
                @endif

              @else
                <span class="text-muted small">—</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No hay viáticos.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  {{-- Acciones finales solicitud --}}
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <strong>Acciones sobre la solicitud</strong>
        <div class="text-muted small">Cambian el estado general.</div>
      </div>

      @if(!$canEditAllowances)
        <span class="badge bg-light text-dark border">Acciones limitadas (status ≠ approved_by_treasury)</span>
      @endif
    </div>

    <div class="card-body d-flex gap-2 flex-wrap">

      @if($hasApproveRequest)
        <form method="POST" action="{{ route($routePrefix.'.review.approve', $r->id) }}">
          @csrf
          <button class="btn btn-success" @if(!$canEditAllowances) disabled @endif>
            Aprobar solicitud
          </button>
        </form>
      @endif

      @if($hasReturnRequest)
        <button class="btn btn-outline-info" type="button" data-bs-toggle="modal" data-bs-target="#returnModal"
                @if(!$canEditAllowances) disabled @endif>
          Devolver
        </button>
      @endif

      @if($hasRejectRequest)
        <button class="btn btn-outline-dark" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal"
                @if(!$canEditAllowances) disabled @endif>
          Rechazar
        </button>
      @endif

      @if(!$canEditAllowances)
        <div class="w-100 text-muted small mt-2">
          Nota: la solicitud no está en <strong>Aprobada por Tesorería</strong>, por eso algunas acciones están deshabilitadas.
        </div>
      @endif

    </div>
  </div>

  {{-- Modal Devolver --}}
  @if($hasReturnRequest)
    <div class="modal fade" id="returnModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Devolver solicitud #{{ $r->id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>

          <form method="POST" action="{{ route($routePrefix.'.review.return', $r->id) }}">
            @csrf
            <div class="modal-body">
              <div class="row g-3">
                <div class="col-md-5">
                  <label class="form-label">¿A quién devolver?</label>
                  <select name="target" class="form-select" required>
                    <option value="treasury">Tesorería</option>
                    <option value="support">Apoyo</option>
                    <option value="applicant">Solicitante</option>
                  </select>
                  <div class="text-muted small mt-1">
                    OJO: el controlador debe permitir <code>treasury</code> en el validate().
                  </div>
                </div>

                <div class="col-md-7">
                  <label class="form-label">Motivo</label>
                  <textarea name="comment" class="form-control" rows="3" maxlength="2000" required
                            placeholder="Describe claramente qué falta o qué se debe corregir..."></textarea>
                </div>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-info text-white">Confirmar devolución</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

  {{-- Modal Rechazar --}}
  @if($hasRejectRequest)
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Rechazar solicitud #{{ $r->id }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
          </div>

          <form method="POST" action="{{ route($routePrefix.'.review.reject', $r->id) }}">
            @csrf
            <div class="modal-body">
              <label class="form-label">Motivo (obligatorio)</label>
              <textarea name="comment" class="form-control" rows="4" maxlength="2000" required
                        placeholder="Explica el motivo del rechazo..."></textarea>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-dark">Confirmar rechazo</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  @endif

</div>
@endsection
