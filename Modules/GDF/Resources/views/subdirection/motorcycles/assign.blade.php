@extends('gdf::layouts.masteruser')
@section('title','Motos | Subdirección')

@section('content')
<div class="container py-4">

  @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
  @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif

  @if ($errors->any())
    <div class="alert alert-warning">
      <ul class="mb-0">
        @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">Motos (Inventario)</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary" href="{{ route('gdf.subdirection.motorcycle_assignments.create') }}">
        Asignación directa
      </a>
      <a class="btn btn-primary" href="{{ route('gdf.subdirection.motorcycles.create') }}">
        Nueva moto
      </a>
    </div>
  </div>

  <form class="row g-2 mb-3" method="GET">
    <div class="col-md-5">
      <input class="form-control" name="q" value="{{ $q }}" placeholder="Placa / modelo / marca...">
    </div>
    <div class="col-md-5">
      <select class="form-select" name="area_id">
        <option value="">Todas las áreas</option>
        @foreach($areas as $a)
          <option value="{{ $a->id }}" @selected((string)$areaId===(string)$a->id)>{{ $a->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="col-md-2">
      <button class="btn btn-outline-secondary w-100">Filtrar</button>
    </div>
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped mb-0">
        <thead>
          <tr>
            <th>Placa</th>
            <th>Modelo</th>
            <th>Área</th>
            <th>Estado</th>
            <th>Kilometraje</th>
            <th style="width:320px;">Traslado</th>
          </tr>
        </thead>
        <tbody>
        @forelse($motorcycles as $m)
          <tr>
            <td><strong>{{ $m->plate }}</strong></td>
            <td>{{ $m->brand }} {{ $m->model }}</td>
            <td>{{ optional($m->currentArea)->name ?? '—' }}</td>
            <td><span class="badge bg-secondary">{{ strtoupper($m->status) }}</span></td>
            <td>{{ $m->current_odometer }}</td>
            <td>
              <form class="d-flex gap-2" method="POST" action="{{ route('gdf.subdirection.motorcycles.transfer',$m->id) }}">
                @csrf
                <select class="form-select form-select-sm" name="to_area_id" required>
                  @foreach($areas as $a)
                    <option value="{{ $a->id }}" @selected($m->current_area_id==$a->id)>{{ $a->name }}</option>
                  @endforeach
                </select>
                <input class="form-control form-control-sm" name="notes" placeholder="Notas (opcional)">
                <button class="btn btn-sm btn-outline-primary">Trasladar</button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="6" class="text-center text-muted py-4">No hay motos registradas.</td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">{{ $motorcycles->links() }}</div>
</div>
@endsection
