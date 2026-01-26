@extends('gdf::layouts.masteruser')
@section('title', 'GDF | '.$title.' - Personas por rubro')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">{{ $title }} - Personas por rubro</h4>
      <small class="text-muted">Agrupado por rubro (asignaciones).</small>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-primary" href="{{ route($routePrefix.'.people.create') }}">+ Registrar persona</a>
      <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.review') }}">Volver</a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route($routePrefix.'.people.index') }}">
        <div class="row g-2 align-items-end">
          <div class="col-md-3">
            <label class="form-label">Área</label>
            <select name="area_id" class="form-select">
              @foreach($areas as $a)
                <option value="{{ $a->id }}" @selected((int)$areaId===(int)$a->id)>{{ $a->name }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-md-4">
            <label class="form-label">Buscar</label>
            <input name="q" class="form-control" value="{{ $q }}" placeholder="Cédula, nombre o email">
          </div>

          <div class="col-md-2">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="only_active" value="1" id="only_active" @checked($onlyActive)>
              <label class="form-check-label" for="only_active">Solo activos</label>
            </div>
          </div>

          <div class="col-md-3">
            <button class="btn btn-primary w-100">Filtrar</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Rubros</div>
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr>
            <th>Rubro</th>
            <th class="text-end">Personas (distinct)</th>
            <th class="text-end">Asignaciones activas</th>
            <th style="width:160px">Acción</th>
          </tr>
        </thead>
        <tbody>
          @forelse($grouped as $g)
            <tr>
              <td class="fw-semibold">{{ $g->budget_item_name }}</td>
              <td class="text-end">{{ $g->total_people }}</td>
              <td class="text-end">{{ $g->active_assignments }}</td>
              <td>
                <a class="btn btn-sm btn-outline-primary"
                   href="{{ route($routePrefix.'.people.index', array_merge(request()->all(), ['budget_item_id'=>$g->budget_item_id])) }}">
                  Ver detalle
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center text-muted py-4">No hay datos.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $grouped->links() }}
    </div>
  </div>
</div>
@endsection
