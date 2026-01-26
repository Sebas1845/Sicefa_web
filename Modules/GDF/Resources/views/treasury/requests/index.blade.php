@extends('gdf::layouts.masteruser')
@section('title','GDF | Tesorería - Solicitudes')

@section('content')
<div class="container py-4">
  <div class="mb-3">
    <div class="text-muted small">GDF / Tesorería</div>
    <h4 class="fw-bold mb-0">Bandeja de solicitudes</h4>
    <div class="text-muted">Validación de recursos y envío a Coordinación.</div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k)) <div class="alert alert-{{ $type }}">{{ session($k) }}</div> @endif
  @endforeach

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form class="row g-2" method="GET" action="{{ route('gdf.treasury.requests.index') }}">
        <div class="col-md-4">
          <input name="q" class="form-control" value="{{ $q }}" placeholder="Buscar (id, origen, destino)">
        </div>
        <div class="col-md-3">
          <select name="area" class="form-select">
            <option value="all" {{ $area=='all'?'selected':'' }}>Todas las áreas</option>
            <option value="academic" {{ $area=='academic'?'selected':'' }}>Académica</option>
            <option value="campesena" {{ $area=='campesena'?'selected':'' }}>Campesena</option>
          </select>
        </div>
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="pending_treasury" {{ $status=='pending_treasury'?'selected':'' }}>Pendientes tesorería</option>
            <option value="approved_by_treasury" {{ $status=='approved_by_treasury'?'selected':'' }}>Aprobadas tesorería</option>
            <option value="all" {{ $status=='all'?'selected':'' }}>Todas</option>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-primary">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped mb-0 align-middle">
        <thead class="table-light">
          <tr>
            <th style="width:90px">ID</th>
            <th>Ruta</th>
            <th style="width:160px">Área</th>
            <th style="width:180px">Estado</th>
            <th class="text-end" style="width:160px">Valor</th>
            <th style="width:140px"></th>
          </tr>
        </thead>
        <tbody>
          @forelse($requests as $r)
            <tr>
              <td class="fw-semibold">#{{ $r->id }}</td>
              <td>
                <div class="fw-semibold">{{ $r->origin ?? '—' }} → {{ $r->destination ?? '—' }}</div>
                <small class="text-muted">{{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}</small>
              </td>
              <td>{{ strtoupper($r->area_key ?? '—') }}</td>
              <td><span class="badge bg-secondary">{{ $r->status }}</span></td>
              <td class="text-end">$ {{ number_format($r->amount ?? 0,0,',','.') }}</td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('gdf.treasury.requests.show',$r->id) }}">Ver</a>
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">Sin resultados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white">
      {{ $requests->links() }}
    </div>
  </div>
</div>
@endsection
