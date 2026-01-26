@extends('gdf::layouts.masteruser')
@section('title', 'GDF | '.$title.' - Detalle por rubro')

@section('content')
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0">{{ $title }} - Detalle por rubro</h4>
      <small class="text-muted">Listado de asignaciones (persona + rubro).</small>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.people.index', array_merge(request()->all(), ['budget_item_id'=>0])) }}">Volver a rubros</a>
      <a class="btn btn-outline-primary" href="{{ route($routePrefix.'.people.create') }}">+ Registrar persona</a>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route($routePrefix.'.people.index') }}">
        <input type="hidden" name="budget_item_id" value="{{ $budgetItemId }}">
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
    <div class="card-header">Asignaciones</div>
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead>
          <tr>
            <th>Persona</th>
            <th>Área</th>
            <th>Rubro</th>
            <th>Vigencia</th>
            <th>Estado</th>
            <th>Usuario</th>
          </tr>
        </thead>
        <tbody>
          @forelse($detail as $a)
            <tr>
              <td>
                <div class="fw-semibold">{{ $a->first_name }} {{ $a->first_last_name }} {{ $a->second_last_name }}</div>
                <small class="text-muted">CC {{ $a->document_number }} · assignment #{{ $a->assignment_id }}</small>
                @if($a->contractor_id)
                  <div><span class="badge bg-info text-dark">Contratista</span> <span class="text-muted small">contractor_id: {{ $a->contractor_id }}</span></div>
                @else
                  <div><span class="badge bg-secondary">Planta / Sin contrato</span></div>
                @endif
              </td>
              <td>{{ $a->area_name ?? ('Área '.$a->area_id) }}</td>
              <td>{{ $a->budget_item_name ?? ('Rubro '.$a->budget_item_id) }}</td>
              <td class="small text-muted">{{ $a->start_date ?? '-' }} → {{ $a->end_date ?? '-' }}</td>
              <td>
                @if($a->is_active) <span class="badge bg-success">Activa</span>
                @else <span class="badge bg-secondary">Inactiva</span>
                @endif
                @if($a->is_primary) <span class="badge bg-primary ms-1">Principal</span> @endif
              </td>
              <td>
                @if($a->user_email) <span class="fw-semibold">{{ $a->user_email }}</span>
                @else <span class="text-muted">Sin usuario</span>
                @endif
              </td>
            </tr>
          @empty
            <tr><td colspan="6" class="text-center text-muted py-4">No hay resultados.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer">
      {{ $detail->links() }}
    </div>
  </div>
</div>
@endsection
