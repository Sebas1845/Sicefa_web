@extends('gdf::layouts.masteruser')
@section('title','GDF | Apoyo · Tarifas')

@section('content')
@php
  use Illuminate\Support\Str;

  $areaKey = $areaKey ?? (Str::contains(request()->path(), 'support/campesena') ? 'campesena' : 'academic');
  $routePrefix = $routePrefix ?? ($areaKey === 'campesena' ? 'gdf.support.campesena' : 'gdf.support.academic');

  $departmentId = (int) ($departmentId ?? request('department_id', 0));
@endphp

<div class="container-fluid">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="mb-0">Tarifas de control · Municipio / Vereda</h4>
      <small class="text-muted">
        Solo se muestran las tarifas <b>activas</b> registradas. Filtra por departamento para reducir datos.
      </small>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="{{ route($routePrefix . '.dashboard') }}">Volver</a>

      {{-- Export faltantes filtrado --}}
      <a class="btn btn-outline-success"
         href="{{ route($routePrefix . '.rates.exportMissing', ['type' => 'municipality', 'department_id' => $departmentId]) }}">
        Descargar faltantes Municipios
      </a>

      <a class="btn btn-outline-success"
         href="{{ route($routePrefix . '.rates.exportMissing', ['type' => 'village', 'department_id' => $departmentId]) }}">
        Descargar faltantes Veredas
      </a>

      {{-- Import --}}
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#importRatesModal">
        Subir Excel
      </button>
    </div>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  {{-- Filtro --}}
  <div class="card mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route($routePrefix . '.rates.index') }}" class="row g-2">
        <div class="col-md-6">
          <label class="form-label">Departamento (Colombia)</label>
          <select class="form-select" name="department_id">
            <option value="0">— Todos —</option>
            @foreach(($departments ?? []) as $d)
              <option value="{{ $d->id }}" {{ (int)$d->id === (int)$departmentId ? 'selected' : '' }}>
                {{ $d->name }}
              </option>
            @endforeach
          </select>
          <div class="form-text">Esto limita municipios y veredas a ese departamento.</div>
        </div>

        <div class="col-md-3 d-grid align-items-end">
          <button class="btn btn-primary">Aplicar filtro</button>
        </div>
      </form>
    </div>
  </div>

  <div class="row g-3">

    {{-- MUNICIPIOS --}}
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
          <span>Municipios (tarifas activas)</span>
          <span class="badge bg-light text-dark">{{ count($municipalities ?? []) }}</span>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Municipio</th>
                <th class="text-end" style="width:110px;">Bus</th>
                <th class="text-end" style="width:110px;">Van</th>
                <th class="text-end" style="width:110px;">Moto</th>
                <th class="text-end" style="width:110px;">Aéreo</th>
                <th class="text-end" style="width:110px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              @forelse($municipalities as $m)
                @php $fid = 'munForm'.$m->municipality_id; @endphp
                <tr>
                  <td class="fw-semibold">
                    {{ $m->name }}
                    <div class="text-muted small">{{ $m->department_name ?? '' }}</div>

                    {{-- Form vacío (válido) --}}
                    <form id="{{ $fid }}" method="POST" action="{{ route($routePrefix . '.rates.upsert') }}">
                      @csrf
                      <input type="hidden" name="type" value="municipality">
                      <input type="hidden" name="id" value="{{ $m->municipality_id }}">
                    </form>
                  </td>

                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="bus_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($m->bus_amount ?? 0) }}">
                  </td>
                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="van_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($m->van_amount ?? 0) }}">
                  </td>
                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="motorcycle_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($m->motorcycle_amount ?? 0) }}">
                  </td>
                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="air_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($m->air_amount ?? 0) }}">
                  </td>

                  <td class="text-end">
                    <button form="{{ $fid }}" class="btn btn-sm btn-primary">Guardar</button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-muted p-3">
                    No hay tarifas activas registradas para municipios con este filtro.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- VEREDAS --}}
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
          <span>Veredas (tarifas activas)</span>
          <span class="badge bg-light text-dark">{{ count($villages ?? []) }}</span>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th>Vereda</th>
                <th class="text-end" style="width:110px;">Bus</th>
                <th class="text-end" style="width:110px;">Van</th>
                <th class="text-end" style="width:110px;">Moto</th>
                <th class="text-end" style="width:110px;">Acción</th>
              </tr>
            </thead>
            <tbody>
              @forelse($villages as $v)
                @php $fid = 'vilForm'.$v->village_id; @endphp
                <tr>
                  <td class="fw-semibold">
                    {{ $v->name }}
                    <div class="text-muted small">
                      {{ $v->municipality_name ?? '' }} · {{ $v->department_name ?? '' }}
                    </div>

                    <form id="{{ $fid }}" method="POST" action="{{ route($routePrefix . '.rates.upsert') }}">
                      @csrf
                      <input type="hidden" name="type" value="village">
                      <input type="hidden" name="id" value="{{ $v->village_id }}">
                    </form>
                  </td>

                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="bus_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($v->bus_amount ?? 0) }}">
                  </td>
                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="van_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($v->van_amount ?? 0) }}">
                  </td>
                  <td>
                    <input form="{{ $fid }}" class="form-control form-control-sm text-end"
                      name="motorcycle_amount" type="number" step="0.01" min="0"
                      value="{{ (float)($v->motorcycle_amount ?? 0) }}">
                  </td>

                  <td class="text-end">
                    <button form="{{ $fid }}" class="btn btn-sm btn-primary">Guardar</button>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="5" class="text-muted p-3">
                    No hay tarifas activas registradas para veredas con este filtro.
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</div>

{{-- MODAL IMPORT EXCEL --}}
<div class="modal fade" id="importRatesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form method="POST" action="{{ route($routePrefix . '.rates.import') }}" enctype="multipart/form-data" class="modal-content">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Subir Excel de tarifas</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" name="department_id" value="{{ $departmentId }}">

        <div class="mb-3">
          <label class="form-label">Tipo de carga</label>
          <select name="type" class="form-select" required>
            <option value="municipality">Municipios</option>
            <option value="village">Veredas</option>
          </select>
          <div class="form-text">
            Sube el Excel. Si descargaste “faltantes” con departamento, aquí se respeta el mismo filtro.
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Archivo Excel</label>
          <input type="file" name="file" class="form-control" accept=".xlsx,.xls" required>
        </div>

        <div class="alert alert-info mb-0">
          Tip: no dejes IDs vacíos. Si un valor queda en 0, tu import puede ignorarlo (según tu lógica).
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-primary">Importar</button>
      </div>
    </form>
  </div>
</div>
@endsection
