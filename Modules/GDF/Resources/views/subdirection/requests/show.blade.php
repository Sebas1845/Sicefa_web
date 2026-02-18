@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Solicitud #'.($tr->id ?? '—'))

@section('content')
@php
  use Illuminate\Support\Facades\Route;

  $money = fn($n) => '$ ' . number_format((float)$n, 0, ',', '.');

  $tr = $tr ?? null;
  if(!$tr) abort(404);

  $approveRouteName =
      Route::has('gdf.subdirection.requests.approve') ? 'gdf.subdirection.requests.approve' :
      (Route::has('gdf.subdirection.requests.confirm') ? 'gdf.subdirection.requests.confirm' : null);

  $indexRoute = Route::has('gdf.subdirection.requests.index') ? 'gdf.subdirection.requests.index' : 'gdf.subdirection.requests';

  $statusLabels = [
    'draft' => 'Borrador',
    'submitted' => 'Enviada',
    'pending_treasury' => 'Pendiente Tesorería',
    'approved_by_treasury' => 'Aprobada por Tesorería',
    'approved' => 'Pendiente Subdirección',
    'confirmed' => 'Confirmada (final)',
    'executed' => 'Ejecutada',
    'returned' => 'Devuelta',
    'rejected' => 'Rechazada',
    'cancelled' => 'Anulada',
  ];

  $statusLabel = fn($s) => $statusLabels[(string)$s] ?? (ucwords(str_replace('_',' ', (string)$s)) ?: '—');

  $badgeClass = function($s){
    return match((string)$s){
      'submitted','pending_treasury' => 'bg-warning text-dark',
      'approved','approved_by_treasury' => 'bg-info text-dark',
      'confirmed','executed' => 'bg-success',
      'returned' => 'bg-secondary',
      'rejected' => 'bg-danger',
      default => 'bg-dark',
    };
  };

  $canApprove = in_array((string)$tr->status, ['approved','approved_by_treasury','pending_subdirection','submitted'], true);

  $segments   = $segments   ?? collect();
  $costs      = $costs      ?? collect();
  $allowances = $allowances ?? collect();
  $logs       = $logs       ?? collect();
  $documents  = $documents  ?? collect();
  $sigacDocuments = $sigacDocuments ?? collect();
  $authorizationDoc = $authorizationDoc ?? null;

  $totalCosts = (float)($totalCosts ?? $costs->sum('amount'));

  $totalAllow = (float)($totalAllow ?? $allowances
    ->whereIn('status',['draft','liquidated','approved'])
    ->sum(function($a){
      $v = $a->approved_amount;
      if ($v === null || $v === '') $v = $a->calculated_amount;
      return (float)$v;
    }));

  $totalAll = (float)($totalAll ?? ($tr->total_amount ?? ($totalCosts + $totalAllow)));
  if($totalAll <= 0) $totalAll = (float)($totalCosts + $totalAllow);

  $destMain = $segments->first()->destination_name ?? ($tr->destination ?? '—');

  $areaPretty = '—';
  if(($tr->area_key ?? '') === 'campesena') $areaPretty = 'Campesena';
  elseif(($tr->area_key ?? '') === 'academic') $areaPretty = 'Académica';

  $downloadDocRouteExists = Route::has('gdf.subdirection.requests.documents.download');
  $downloadSigacRouteExists = Route::has('gdf.subdirection.requests.sigac_documents.download');
@endphp

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / Subdirección / Solicitudes</div>
      <h4 class="mb-1">Solicitud #{{ $tr->id }}</h4>
      <div class="text-muted small">
        Módulo: <span class="badge bg-dark">{{ strtoupper($tr->module ?? 'gdf') }}</span>
        <span class="badge {{ $badgeClass($tr->status) }}" title="{{ $tr->status }}">{{ $statusLabel($tr->status) }}</span>
        @if(!empty($tr->radicado_code))
          <span class="ms-2">Radicado: <strong>{{ $tr->radicado_code }}</strong></span>
        @endif
      </div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route($indexRoute, request()->only(['year','module','status','q'])) }}">Volver</a>

      @if($approveRouteName && $canApprove)
        <form method="POST" action="{{ route($approveRouteName, $tr->id) }}"
              onsubmit="return confirm('¿Confirmar aprobación de la solicitud #{{ $tr->id }}?');">
          @csrf
          <button type="submit" class="btn btn-success">Aprobar</button>
        </form>
      @endif
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  <div class="row g-3">
    {{-- Resumen --}}
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Resumen</strong></div>
        <div class="card-body">
          <div class="mb-2">
            <div class="text-muted small">Persona</div>
            <div class="fw-semibold">{{ $tr->person_name ?? '—' }}</div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Tipo</div>
            <div class="fw-semibold">{{ $tr->person_type ?? '—' }}</div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Área</div>
            <div class="fw-semibold">{{ $areaPretty }}</div>
            <div class="text-muted small">{{ $tr->area_name ?? '—' }}</div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Fechas</div>
            <div class="fw-semibold">{{ $tr->start_date ?? '—' }} → {{ $tr->end_date ?? '—' }}</div>
          </div>

          <div class="mb-2">
            <div class="text-muted small">Ruta</div>
            <div class="fw-semibold">{{ $tr->origin ?? '—' }} → {{ $destMain }}</div>
          </div>

          <hr>

          <div class="d-flex justify-content-between">
            <span class="text-muted">Transporte</span>
            <span class="fw-semibold">{{ $money($totalCosts) }}</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Viáticos</span>
            <span class="fw-semibold">{{ $money($totalAllow) }}</span>
          </div>
          <div class="d-flex justify-content-between mt-2">
            <span class="fw-bold">Total</span>
            <span class="fw-bold">{{ $money($totalAll) }}</span>
          </div>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white"><strong>Auditoría</strong></div>
        <div class="card-body small">
          <div class="d-flex justify-content-between">
            <span class="text-muted">Creada</span>
            <span>{{ $tr->created_at ?? '—' }}</span>
          </div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Actualizada</span>
            <span>{{ $tr->updated_at ?? '—' }}</span>
          </div>
        </div>
      </div>
    </div>

    {{-- Detalle --}}
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
          <strong>Detalle de la solicitud</strong>
          <span class="text-muted small">ID interno: {{ $tr->id }}</span>
        </div>

        <div class="card-body">
          <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-route" type="button">Ruta & Segmentos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-costs" type="button">Costos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-allow" type="button">Viáticos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button">Documentos</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-log" type="button">Historial</button></li>
          </ul>

          <div class="tab-content pt-3">

            {{-- Ruta --}}
            <div class="tab-pane fade show active" id="tab-route">
              <div class="row g-3">
                <div class="col-md-6">
                  <div class="text-muted small">Origen</div>
                  <div class="fw-semibold">{{ $tr->origin ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                  <div class="text-muted small">Destino</div>
                  <div class="fw-semibold">{{ $destMain }}</div>
                </div>
              </div>

              <hr>

              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Segmentos</strong>
                <span class="text-muted small">{{ $segments->count() }} segmento(s)</span>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>#</th><th>Salida</th><th>Llegada</th><th>Origen</th><th>Destino</th><th>Medio</th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($segments as $i=>$s)
                    @php
                      $salida  = $s->departure_at ?? $tr->start_date ?? '—';
                      $llegada = $s->return_at ?? $tr->end_date ?? '—';
                      $orSeg = $s->origin_place ?? ($tr->origin ?? '—');
                      $deSeg = $s->destination_name ?? $destMain;
                      $medio = $s->transport_type ?? '—';
                    @endphp
                    <tr>
                      <td>{{ $i+1 }}</td>
                      <td>{{ $salida }}</td>
                      <td>{{ $llegada }}</td>
                      <td>{{ $orSeg }}</td>
                      <td>{{ $deSeg }}</td>
                      <td>{{ $medio }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Sin segmentos</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            {{-- Costos --}}
            <div class="tab-pane fade" id="tab-costs">
              <div class="d-flex justify-content-between mb-2">
                <strong>Costos</strong>
                <span class="fw-semibold">{{ $money($totalCosts) }}</span>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Tipo</th>
                      <th>Descripción</th>
                      <th class="text-end">Valor</th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($costs as $c)
                    <tr>
                      <td>{{ $c->cost_type ?? '—' }}</td>
                      <td class="text-muted">{{ $c->description ?? '—' }}</td>
                      <td class="text-end fw-semibold">{{ $money($c->amount ?? 0) }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">Sin costos</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            {{-- Viáticos --}}
            <div class="tab-pane fade" id="tab-allow">
              <div class="d-flex justify-content-between mb-2">
                <strong>Viáticos</strong>
                <span class="fw-semibold">{{ $money($totalAllow) }}</span>
              </div>

              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead class="table-light">
                    <tr>
                      <th>Tipo</th>
                      <th class="text-end">Unitario</th>
                      <th class="text-end">Unidades</th>
                      <th class="text-end">Calculado</th>
                      <th class="text-end">Aprobado</th>
                      <th>Estado</th>
                      <th>Detalle</th>
                    </tr>
                  </thead>
                  <tbody>
                  @forelse($allowances as $a)
                    @php
                      $calc = (float)($a->calculated_amount ?? 0);
                      $final = ($a->approved_amount === null || $a->approved_amount === '')
                        ? $calc
                        : (float)$a->approved_amount;
                    @endphp
                    <tr>
                      <td>{{ $a->allowance_type ?? '—' }}</td>
                      <td class="text-end">{{ $money($a->unit_amount ?? 0) }}</td>
                      <td class="text-end">{{ (int)($a->units ?? 1) }}</td>
                      <td class="text-end">{{ $money($calc) }}</td>
                      <td class="text-end fw-semibold">{{ $money($final) }}</td>
                      <td><span class="badge bg-light text-dark">{{ $a->status ?? '—' }}</span></td>
                      <td class="text-muted">{{ $a->description ?? '—' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="7" class="text-center text-muted py-3">Sin viáticos</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>

            {{-- Documentos --}}
            <div class="tab-pane fade" id="tab-docs">
              {{-- Autorización --}}
              <div class="d-flex justify-content-between align-items-center mb-2">
                <strong>Autorización</strong>
                <span class="text-muted small">
                  @if($authorizationDoc) Generada @else Pendiente @endif
                </span>
              </div>

              @if($authorizationDoc && $downloadDocRouteExists)
                <div class="alert alert-success d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-semibold">{{ $authorizationDoc->name ?? 'Autorización' }}</div>
                    <div class="text-muted small">{{ $authorizationDoc->created_at ?? '—' }}</div>
                  </div>
                  <a class="btn btn-sm btn-outline-success"
                     href="{{ route('gdf.subdirection.requests.documents.download', [$tr->id, $authorizationDoc->id]) }}">
                    Descargar
                  </a>
                </div>
              @else
                <div class="alert alert-secondary">Aún no se ha generado la autorización para esta solicitud.</div>
              @endif

              {{-- Docs GDF --}}
              <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                <strong>Documentos (GDF/SICEFA)</strong>
                <span class="text-muted small">{{ $documents->count() }} archivo(s)</span>
              </div>

              <ul class="list-group mb-3">
                @forelse($documents as $d)
                  @php
                    // omitir repetir la autorización en la lista general
                    $isAuth = false;
                    if ($authorizationDoc && (int)$authorizationDoc->id === (int)$d->id) $isAuth = true;

                    $download = ($downloadDocRouteExists && !$isAuth)
                      ? route('gdf.subdirection.requests.documents.download', [$tr->id, $d->id])
                      : null;
                  @endphp

                  @if(!$isAuth)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">{{ $d->name ?? ($d->type ?? 'Documento') }}</div>
                        <div class="text-muted small">{{ $d->created_at ?? '—' }}</div>
                      </div>
                      @if($download)
                        <a class="btn btn-sm btn-outline-primary" href="{{ $download }}">Descargar</a>
                      @endif
                    </li>
                  @endif
                @empty
                  <li class="list-group-item text-muted">Sin documentos adjuntos</li>
                @endforelse
              </ul>

              {{-- Docs SIGAC (solo SITRAV) --}}
              @if(strtolower((string)($tr->module ?? '')) === 'sitrav')
                <div class="d-flex justify-content-between align-items-center mb-2">
                  <strong>Documentos SIGAC</strong>
                  <span class="text-muted small">{{ $sigacDocuments->count() }} archivo(s)</span>
                </div>

                <ul class="list-group">
                  @forelse($sigacDocuments as $sd)
                    @php
                      $downloadSigac = $downloadSigacRouteExists
                        ? route('gdf.subdirection.requests.sigac_documents.download', [$tr->id, $sd->id])
                        : null;
                    @endphp
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                      <div>
                        <div class="fw-semibold">{{ $sd->name ?? 'Documento SIGAC' }}</div>
                        <div class="text-muted small">{{ $sd->created_at ?? '—' }}</div>
                      </div>
                      @if($downloadSigac)
                        <a class="btn btn-sm btn-outline-primary" href="{{ $downloadSigac }}">Descargar</a>
                      @endif
                    </li>
                  @empty
                    <li class="list-group-item text-muted">Sin documentos SIGAC</li>
                  @endforelse
                </ul>
              @endif
            </div>

            {{-- Historial --}}
            <div class="tab-pane fade" id="tab-log">
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead class="table-light">
                    <tr><th>Fecha</th><th>Acción</th><th>Detalle</th></tr>
                  </thead>
                  <tbody>
                  @forelse($logs as $l)
                    <tr>
                      <td>{{ $l->created_at ?? '—' }}</td>
                      <td class="fw-semibold">{{ $l->action ?? '—' }}</td>
                      <td class="text-muted">{{ $l->comments ?? '—' }}</td>
                    </tr>
                  @empty
                    <tr><td colspan="3" class="text-center text-muted py-3">Sin historial</td></tr>
                  @endforelse
                  </tbody>
                </table>
              </div>
            </div>

          </div>{{-- tab-content --}}
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
