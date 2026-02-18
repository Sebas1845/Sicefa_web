{{-- Modules/GDF/Resources/views/support/motorcycles/return.blade.php --}}
@extends('gdf::layouts.masteruser')

@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;
  use Carbon\Carbon;

  $areaKey = $areaKey ?? 'academic';
  $routePrefix = $routePrefix ?? ($areaKey === 'campesena'
      ? 'gdf.support.campesena'
      : 'gdf.support.academic');

  $items = $items ?? collect();

  $year   = $year ?? (int)request('year', now()->year);
  $areaId = $areaId ?? (int)request('area_id', 0);
  $q      = $q ?? (string)request('q', '');

  $title = 'Devoluciones de Motos · ' . strtoupper($areaKey);

  $badgeAssign = fn($s) => match(strtolower((string)$s)){
    'approved'  => 'info',
    'delivered' => 'success',
    'returned'  => 'secondary',
    'cancelled' => 'dark',
    default     => 'secondary',
  };

  // Bloqueo por tesorería (para asignaciones ligadas a solicitud)
  $isLockedByTreasury = function($it){
    $tr = $it->travelRequest ?? null; // si tienes relación; si no, caerá en null
    if(!$tr) return false;

    $status = strtolower((string)($tr->status ?? ''));
    return in_array($status, ['pending_treasury','approved_by_treasury','confirmed','executed'], true);
  };

  $hasReturnRoute = Route::has($routePrefix.'.motorcycles.assignments.return');
  $hasReceiptRoute = Route::has($routePrefix.'.motorcycles.assignments.receipt');
@endphp

@section('title', 'GDF | '.$title)

@section('content')
@push('css')
<style>
  .soft-card{border-radius:16px}
  .soft-card .card-header{border-radius:16px 16px 0 0}
  .wrap{white-space:normal}
  .mono{font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;}
  .muted-help{font-size:.8rem;opacity:.75}
  .kpi{border-radius:16px}
  .kpi .label{font-size:.75rem;opacity:.75}
  .kpi .value{font-size:1.15rem;font-weight:800}
  .pill{
    display:inline-flex; align-items:center; gap:.35rem;
    padding:.25rem .55rem; border-radius:999px; font-size:.78rem;
    border:1px solid rgba(0,0,0,.08);
  }
</style>
@endpush

<div class="container-fluid">

  {{-- HEADER --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
    <div>
      <h4 class="mb-0">{{ $title }}</h4>
      <div class="text-muted small">
        Asignaciones en estado <code>delivered</code> pendientes por devolución (vigencia {{ $year }})
      </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary"
         href="{{ route($routePrefix.'.motorcycles.queue', ['year'=>$year, 'area_id'=>$areaId]) }}">
        <i class="bi bi-arrow-left"></i> Volver a cola
      </a>

      <a class="btn btn-outline-primary"
         href="{{ route($routePrefix.'.motorcycles.return', ['year'=>$year, 'q'=>$q]) }}">
        <i class="bi bi-arrow-repeat"></i> Refrescar
      </a>
    </div>
  </div>

  {{-- FLASH --}}
  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
  @if($errors->any())
    <div class="alert alert-danger">
      <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  {{-- KPIs --}}
  @php
    $totalRows = method_exists($items,'total') ? (int)$items->total() : (int)$items->count();
    $lockedRows = 0;

    if ($totalRows > 0) {
      foreach ($items as $tmp) { if($isLockedByTreasury($tmp)) $lockedRows++; }
    }
  @endphp

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card kpi shadow-sm soft-card">
        <div class="card-body">
          <div class="label">Pendientes de devolución</div>
          <div class="value">{{ $totalRows }}</div>
          <div class="muted-help">Filtradas por la vista (delivered + sin returned_at)</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card kpi shadow-sm soft-card">
        <div class="card-body">
          <div class="label">Bloqueadas por Tesorería</div>
          <div class="value">{{ $lockedRows }}</div>
          <div class="muted-help">Solicitudes en <code>pending_treasury</code> o posteriores</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card kpi shadow-sm soft-card">
        <div class="card-body">
          <div class="label">Acción permitida</div>
          <div class="value">{{ max(0, $totalRows - $lockedRows) }}</div>
          <div class="muted-help">Se permite devolver solo si NO está bloqueada</div>
        </div>
      </div>
    </div>
  </div>

  {{-- FILTROS --}}
  <div class="card soft-card shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route($routePrefix.'.motorcycles.return') }}" class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Año</label>
          <input class="form-control" type="number" name="year" value="{{ $year }}">
        </div>

        <div class="col-md-5">
          <label class="form-label">Buscar</label>
          <input class="form-control" name="q" value="{{ $q }}" placeholder="Placa (ABC123) o parte...">
          <div class="muted-help">Filtra por placa de la moto.</div>
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
          <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
          <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.motorcycles.return') }}">Limpiar</a>
        </div>
      </form>
    </div>
  </div>

  {{-- LISTA --}}
  <div class="card soft-card shadow-sm">
    <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
      <div><i class="bi bi-arrow-repeat"></i> Pendientes de devolución</div>
      <small class="text-muted">Regla: solo se devuelve si está en <code>delivered</code> y no está bloqueada</small>
    </div>

    <div class="card-body p-0">
      @if($items->isEmpty())
        <div class="p-3 text-muted">No hay devoluciones pendientes con los filtros actuales.</div>
      @else
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="min-width:80px;">#</th>
                <th style="min-width:260px;">Persona</th>
                <th style="min-width:170px;">Área</th>
                <th style="min-width:180px;">Moto</th>
                <th style="min-width:220px;">Tipo / Solicitud</th>
                <th style="min-width:170px;">Entregada</th>
                <th style="min-width:140px;">Estado</th>
                <th style="min-width:420px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              @foreach($items as $it)
                @php
                  $personName = optional($it->person)->fullname
                    ?? trim((string)($it->person_name ?? ''))
                    ?? ('Person #'.$it->person_id);

                  $areaName   = optional($it->area)->name ?? ('Area #'.$it->area_id);
                  $plate      = optional($it->motorcycle)->plate ?? ('Moto #'.$it->motorcycle_id);

                  $deliveredAt = $it->delivered_at ? Carbon::parse($it->delivered_at) : null;
                  $delivered  = $deliveredAt ? $deliveredAt->format('Y-m-d H:i') : '—';

                  $isRequest = !empty($it->travel_requestable_id);
                  $tr = $it->travelRequest ?? null; // si tienes la relación
                  $trId = $tr?->id ?? ($isRequest ? (int)$it->travel_requestable_id : null);
                  $trStatus = strtolower((string)($tr?->status ?? ''));

                  $locked = $isLockedByTreasury($it);

                  $typePill = $isRequest
                    ? '<span class="pill bg-light"><i class="bi bi-file-earmark-text"></i> Solicitud</span>'
                    : '<span class="pill bg-light"><i class="bi bi-person-check"></i> Directa</span>';

                  $lockPill = $locked
                    ? '<span class="pill text-danger bg-light"><i class="bi bi-shield-lock"></i> Bloqueada Tesorería</span>'
                    : '<span class="pill text-success bg-light"><i class="bi bi-shield-check"></i> Editable</span>';
                @endphp
                <tr>
                  <td class="mono">#{{ $it->id }}</td>

                  <td class="wrap">
                    <div class="fw-semibold">{{ $personName }}</div>
                    <div class="text-muted small mono">
                      Person ID: {{ (int)$it->person_id }}
                    </div>
                  </td>

                  <td class="wrap">
                    <div>{{ $areaName }}</div>
                    <div class="text-muted small mono">Area ID: {{ (int)$it->area_id }}</div>
                  </td>

                  <td class="wrap">
                    <div class="fw-semibold">{{ $plate }}</div>
                    <div class="text-muted small mono">Moto ID: {{ (int)$it->motorcycle_id }}</div>
                  </td>

                  <td class="wrap">
                    {!! $typePill !!} {!! $lockPill !!}
                    @if($isRequest)
                      <div class="text-muted small mt-1">
                        Solicitud: <span class="mono">#{{ $trId }}</span>
                        @if($trStatus !== '')
                          · Estado: <span class="mono">{{ $trStatus }}</span>
                        @endif
                      </div>
                    @else
                      <div class="text-muted small mt-1">Sin solicitud asociada.</div>
                    @endif
                  </td>

                  <td class="mono">{{ $delivered }}</td>

                  <td>
                    <span class="badge bg-{{ $badgeAssign($it->status) }}">{{ $it->status }}</span>
                  </td>

                  <td>
                    @if(!$hasReturnRoute)
                      <div class="text-muted small">
                        Falta la ruta <code>{{ $routePrefix }}.motorcycles.assignments.return</code>.
                      </div>
                    @else
                      @if($locked)
                        <div class="alert alert-warning py-2 mb-2">
                          <i class="bi bi-shield-lock"></i>
                          <b>Bloqueado</b>: esta moto está ligada a una solicitud en proceso de Tesorería o posterior.
                          No se permite devolución aquí.
                        </div>
                      @endif

                      <form method="POST"
                            action="{{ route($routePrefix.'.motorcycles.assignments.return', ['assignment'=>$it->id]) }}"
                            class="row g-2">
                        @csrf

                        <div class="col-md-3">
                          <input class="form-control form-control-sm" type="number" min="0"
                                 name="odometer_in" placeholder="Km entrada"
                                 required @disabled($locked)>
                        </div>

                        <div class="col-md-6">
                          <input class="form-control form-control-sm"
                                 name="observations_in"
                                 placeholder="Observación (opcional)"
                                 @disabled($locked)>
                        </div>

                        <div class="col-md-3 d-grid">
                          <button class="btn btn-sm btn-success" @disabled($locked)>
                            <i class="bi bi-check2-circle"></i> Devolver
                          </button>
                        </div>

                        <div class="col-12 d-flex gap-2 flex-wrap">
                          @if($hasReceiptRoute)
                            <a class="btn btn-sm btn-outline-secondary"
                               href="{{ route($routePrefix.'.motorcycles.assignments.receipt', ['assignment'=>$it->id]) }}">
                              <i class="bi bi-receipt"></i> Recibo
                            </a>
                          @endif

                          @if($isRequest && $trId)
                            <span class="text-muted small">
                              Si necesitas “quitar por error”, hazlo solo cuando NO esté en Tesorería.
                            </span>
                          @endif
                        </div>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <div class="p-3">
          @if(method_exists($items,'links'))
            {{ $items->links() }}
          @endif
        </div>
      @endif
    </div>
  </div>

</div>
@endsection
