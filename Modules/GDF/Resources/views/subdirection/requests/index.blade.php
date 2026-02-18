@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Subdirección · Solicitudes')

@section('content')
@php
  use Illuminate\Support\Facades\Route;

  $money = fn($n) => '$ ' . number_format((float)$n, 0, ',', '.');

  $approveRouteName = Route::has('gdf.subdirection.requests.approve')
    ? 'gdf.subdirection.requests.approve'
    : (Route::has('gdf.subdirection.requests.confirm') ? 'gdf.subdirection.requests.confirm' : null);

  $showRouteOk = Route::has('gdf.subdirection.requests.show');

  $indexRouteOk = Route::has('gdf.subdirection.requests.index')
    ? 'gdf.subdirection.requests.index'
    : 'gdf.subdirection.requests';

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

  $statusLabel = function($s) use ($statusLabels){
    $s = (string)$s;
    return $statusLabels[$s] ?? (ucwords(str_replace('_',' ', $s)) ?: '—');
  };

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
@endphp

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / Subdirección</div>
      <h4 class="mb-1">Bandeja de Solicitudes</h4>
      <div class="text-muted small">Vigencia: <strong>{{ $year }}</strong></div>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  <form class="card shadow-sm mb-3" method="GET" action="{{ route($indexRouteOk) }}">
    <div class="card-body row g-2 align-items-end">

      <div class="col-md-2">
        <label class="form-label small">Año</label>
        <input class="form-control" type="number" name="year" value="{{ $year }}">
      </div>

      <div class="col-md-2">
        <label class="form-label small">Módulo</label>
        <select class="form-select" name="module">
          <option value="">Todos</option>
          <option value="gdf" {{ $module === 'gdf' ? 'selected' : '' }}>GDF</option>
          <option value="sitrav" {{ $module === 'sitrav' ? 'selected' : '' }}>SITRAV</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small">Estado</label>
        <select class="form-select" name="status">
          <option value="submitted" {{ $status==='submitted'?'selected':'' }}>{{ $statusLabel('submitted') }}</option>
          <option value="approved" {{ $status==='approved'?'selected':'' }}>{{ $statusLabel('approved') }}</option>
          <option value="approved_by_treasury" {{ $status==='approved_by_treasury'?'selected':'' }}>{{ $statusLabel('approved_by_treasury') }}</option>
          <option value="confirmed" {{ $status==='confirmed'?'selected':'' }}>{{ $statusLabel('confirmed') }}</option>
          <option value="returned" {{ $status==='returned'?'selected':'' }}>{{ $statusLabel('returned') }}</option>
          <option value="rejected" {{ $status==='rejected'?'selected':'' }}>{{ $statusLabel('rejected') }}</option>
          <option value="all" {{ $status==='all'?'selected':'' }}>Todos</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label small">Buscar</label>
        <input class="form-control" name="q" value="{{ $q }}" placeholder="ID, origen, destino, radicado">
      </div>

      <div class="col-md-2 d-flex gap-2">
        <button class="btn btn-primary w-100" type="submit">Filtrar</button>
        <a class="btn btn-outline-secondary w-100" href="{{ route($indexRouteOk, ['year'=>$year]) }}">Reset</a>
      </div>
    </div>
  </form>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px">ID</th>
            <th>Módulo / Estado</th>
            <th>Persona</th>
            <th>Ruta</th>
            <th>Fechas</th>
            <th class="text-end">Transporte</th>
            <th class="text-end">Viáticos</th>
            <th class="text-end">Total</th>
            <th class="text-end" style="width:220px">Acción</th>
          </tr>
        </thead>

        <tbody>
        @forelse($rows as $r)
          @php
            $canApproveRow = in_array((string)$r->status, ['approved','approved_by_treasury','pending_subdirection','submitted'], true);

            $costs = (float)($r->total_costs ?? 0);
            $allow = (float)($r->total_allowances ?? 0);

            // total real (si tu total_amount está correcto, úsalo)
            $total = (float)($r->total_amount ?? 0);
            if($total <= 0) $total = (float)($r->total_calc ?? ($costs+$allow));
          @endphp

          <tr>
            <td class="fw-semibold">#{{ $r->id }}</td>

            <td>
              <span class="badge bg-dark">{{ strtoupper($r->module ?? 'gdf') }}</span>
              <span class="badge {{ $badgeClass($r->status) }}" title="{{ $r->status }}">
                {{ $statusLabel($r->status) }}
              </span>
              <div class="text-muted small">Tipo: {{ $r->person_type ?? '—' }}</div>
            </td>

            <td>{{ $r->person_name ?? '—' }}</td>

            <td class="text-truncate" style="max-width:220px">
              {{ $r->origin ?? '—' }} → {{ $r->destination ?? '—' }}
            </td>

            <td>{{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}</td>

            <td class="text-end">{{ $money($costs) }}</td>
            <td class="text-end">{{ $money($allow) }}</td>
            <td class="text-end fw-semibold">{{ $money($total) }}</td>

            <td class="text-end">
              <div class="d-inline-flex gap-2">
                @if($showRouteOk)
                  <a class="btn btn-sm btn-outline-primary" href="{{ route('gdf.subdirection.requests.show', $r->id) }}">Ver</a>
                @endif

                @if($approveRouteName && $canApproveRow)
                  <form method="POST" action="{{ route($approveRouteName, $r->id) }}"
                        onsubmit="return confirm('¿Confirmar aprobación de la solicitud #{{ $r->id }}?');">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-success">Aprobar</button>
                  </form>
                @endif
              </div>

              @if(!$approveRouteName)
                <div class="text-muted small mt-1">No existe ruta approve/confirm</div>
              @endif
            </td>
          </tr>

        @empty
          <tr><td colspan="9" class="text-center text-muted py-4">Sin resultados</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white">
      {{ $rows->links() }}
    </div>
  </div>

</div>
@endsection
