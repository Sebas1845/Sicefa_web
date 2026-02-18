@extends('gdf::layouts.masteruser')
@section('title','GDF | Apoyo · Solicitudes')

@section('content')
@php
  $tab = $tab ?? 'pending';
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3">
    <div>
      <h4 class="mb-0">Bandeja Apoyo · Solicitudes (GDF/SITRAV)</h4>
      <small class="text-muted">Fuente: <code>program_requests</code></small>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('gdf.support.rates.index', ['year'=>$year]) }}">
        Tarifas Municipio/Vereda
      </a>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if(session('warning'))
    <div class="alert alert-warning">{{ session('warning') }}</div>
  @endif

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <a class="card text-decoration-none" href="{{ route('gdf.support.dashboard',['tab'=>'pending','year'=>$year]) }}">
        <div class="card-body">
          <div class="text-muted">Por revisar</div>
          <div class="h4 mb-0">{{ $kpis['pending'] ?? 0 }}</div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a class="card text-decoration-none" href="{{ route('gdf.support.dashboard',['tab'=>'seen','year'=>$year]) }}">
        <div class="card-body">
          <div class="text-muted">Vistas</div>
          <div class="h4 mb-0">{{ $kpis['seen'] ?? 0 }}</div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a class="card text-decoration-none" href="{{ route('gdf.support.dashboard',['tab'=>'returned','year'=>$year]) }}">
        <div class="card-body">
          <div class="text-muted">Devueltas</div>
          <div class="h4 mb-0">{{ $kpis['returned'] ?? 0 }}</div>
        </div>
      </a>
    </div>
    <div class="col-md-3">
      <a class="card text-decoration-none" href="{{ route('gdf.support.dashboard',['tab'=>'sent','year'=>$year]) }}">
        <div class="card-body">
          <div class="text-muted">Enviadas Tesorería</div>
          <div class="h4 mb-0">{{ $kpis['sent'] ?? 0 }}</div>
        </div>
      </a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form class="row g-2" method="GET" action="{{ route('gdf.support.dashboard') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="col-md-2">
          <input class="form-control" type="number" name="year" value="{{ $year }}">
        </div>
        <div class="col-md-7">
          <input class="form-control" name="q" value="{{ $q ?? '' }}" placeholder="Buscar por ID / solicitante / correo / observación...">
        </div>
        <div class="col-md-3 d-grid">
          <button class="btn btn-primary">Buscar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>#</th>
            <th>Solicitante</th>
            <th>Lugar</th>
            <th>Fechas</th>
            <th>Horas</th>
            <th>Estado SIGAC</th>
            <th>Estado Apoyo</th>
            <th>Transporte</th>
            <th class="text-end">Acción</th>
          </tr>
        </thead>
        <tbody>
          @forelse($requests as $r)
            @php
              $place = ($r->place_type ?? 'municipio') === 'vereda'
                ? ('Vereda: '.optional($r->village)->name)
                : ('Municipio: '.optional($r->municipality)->name);

              $supportStatus = $r->support_status ?? 'Pendiente';
              $transport = ($r->transport_mode ?? 'ninguno');
            @endphp
            <tr>
              <td>{{ $r->id }}</td>
              <td>
                <div class="fw-semibold">{{ optional($r->person)->fullname ?? ('Person #'.$r->person_id) }}</div>
                <small class="text-muted">{{ $r->email ?? '' }}</small>
              </td>
              <td>
                <div>{{ $place }}</div>
                <small class="text-muted">{{ $r->address ?? '' }}</small>
              </td>
              <td>
                <div>{{ $r->start_date }} @if($r->end_date) → {{ $r->end_date }} @endif</div>
              </td>
              <td>{{ $r->hours }}</td>
              <td><span class="badge bg-secondary">{{ $r->state }}</span></td>
              <td>
                <span class="badge bg-info text-dark">{{ $supportStatus }}</span>
                @if($r->support_locked_at)
                  <small class="text-muted d-block">Bloqueada</small>
                @endif
              </td>
              <td>
                <div class="text-capitalize">{{ $transport }}</div>
                @if(!is_null($r->transport_cost))
                  <small class="text-muted">$ {{ number_format((float)$r->transport_cost, 0, ',', '.') }}</small>
                @endif
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="{{ route('gdf.support.requests.show', $r->id) }}">
                  Abrir
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted py-4">No hay registros.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-body">
      {{ $requests->links() }}
    </div>
  </div>

</div>
@endsection
