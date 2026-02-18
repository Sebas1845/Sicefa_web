@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Firmantes de Autorización')

@section('content')
@php
  $module  = $module ?? 'gdf';      // gdf|sitrav|both
  $areaKey = $areaKey ?? 'all';     // all|academic|campesena
  $type    = $type ?? 'all';        // all|global|area
  $q       = $q ?? '';
  $signers = $signers ?? [];

  $roleNames = [
    'subdirection' => 'Subdirección',
    'coordination' => 'Coordinación',
    'treasury'     => 'Tesorería',
    'support'      => 'Apoyo',
  ];
@endphp

<div class="container-fluid">

  <div class="row mb-3 align-items-center">
    <div class="col">
      <h4 class="mb-0">Firmantes de Autorización</h4>
      <small class="text-muted">
        Config JSON: <code>storage/app/{{ $jsonPath ?? 'gdf/authorization_signers.json' }}</code>
        · Firmas: <code>storage/app/public/gdf/Firmas</code>
      </small>
    </div>
    <div class="col text-end">
      <a href="{{ route('gdf.subdirection.authorization_signers.create', ['module'=>$module,'area_key'=>$areaKey]) }}"
         class="btn btn-primary">
        + Nuevo firmante
      </a>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$t)
    @if(session($k)) <div class="alert alert-{{ $t }}">{{ session($k) }}</div> @endif
  @endforeach

  <form method="GET" class="row g-2 mb-3">
    <div class="col-md-2">
      <label class="form-label mb-1">Módulo</label>
      <select name="module" class="form-control" onchange="this.form.submit()">
        <option value="gdf"   {{ $module==='gdf' ? 'selected':'' }}>GDF</option>
        <option value="sitrav"{{ $module==='sitrav' ? 'selected':'' }}>SITRAV</option>
        <option value="both"  {{ $module==='both' ? 'selected':'' }}>AMBOS</option>
      </select>
    </div>

    <div class="col-md-3">
      <label class="form-label mb-1">Área</label>
      <select name="area_key" class="form-control" onchange="this.form.submit()">
        <option value="all"      {{ $areaKey==='all' ? 'selected':'' }}>Todas</option>
        <option value="academic" {{ $areaKey==='academic' ? 'selected':'' }}>Académica</option>
        <option value="campesena"{{ $areaKey==='campesena' ? 'selected':'' }}>Campesena</option>
      </select>
      <small class="text-muted">Si filtras por área, muestra Global + esa área.</small>
    </div>

    <div class="col-md-2">
      <label class="form-label mb-1">Tipo</label>
      <select name="type" class="form-control" onchange="this.form.submit()">
        <option value="all"    {{ $type==='all' ? 'selected':'' }}>Todos</option>
        <option value="global" {{ $type==='global' ? 'selected':'' }}>Solo Global</option>
        <option value="area"   {{ $type==='area' ? 'selected':'' }}>Solo por Área</option>
      </select>
      <small class="text-muted">Global = Subdirección/Tesorería.</small>
    </div>

    <div class="col-md-3">
      <label class="form-label mb-1">Buscar</label>
      <input type="text" name="q" value="{{ $q }}" class="form-control"
             placeholder="Nombre / cédula / rol / cargo">
    </div>

    <div class="col-md-2 d-flex align-items-end">
      <button class="btn btn-secondary w-100">Buscar</button>
    </div>
  </form>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-sm mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th style="width:60px;">#</th>
              <th>Persona</th>
              <th style="width:120px;">Módulo</th>
              <th style="width:170px;">Alcance</th>
              <th style="width:160px;">Rol</th>
              <th>Cargo</th>
              <th style="width:120px;" class="text-center">Firma</th>
              <th style="width:90px;"  class="text-center">Estado</th>
              <th style="width:190px;" class="text-center">Acciones</th>
            </tr>
          </thead>

          <tbody>
          @forelse($signers as $s)
            @php
              $id = (int)($s['id'] ?? 0);
              $active = (int)($s['active'] ?? 1) === 1;

              $rk = $s['area_key'] ?? null;
              $isGlobal = is_null($rk) || $rk === '';
              $sig = (string)($s['signature_path'] ?? '');

              $m = $s['module'] ?? 'gdf';
              $roleKey = $s['role_key'] ?? '';
            @endphp
            <tr>
              <td>{{ $id }}</td>

              <td>
                <strong>{{ $s['person_name'] ?? '—' }}</strong><br>
                <small class="text-muted">{{ $s['person_document'] ?? '' }}</small>

                @if(!empty($s['person_email']) || !empty($s['person_phone']))
                  <br>
                  <small class="text-muted">
                    @if(!empty($s['person_email'])) ✉ {{ $s['person_email'] }} @endif
                    @if(!empty($s['person_phone'])) · ☎ {{ $s['person_phone'] }} @endif
                  </small>
                @endif

                @if(!empty($s['label']))
                  <div class="mt-1">
                    <span class="badge bg-light text-dark border">{{ $s['label'] }}</span>
                  </div>
                @endif
              </td>

              <td>
                @if($m === 'both')
                  <span class="badge bg-primary">GDF + SITRAV</span>
                @else
                  <span class="badge bg-secondary text-uppercase">{{ $m }}</span>
                @endif
              </td>

              <td>
                @if($isGlobal)
                  <span class="badge bg-dark">GLOBAL</span>
                  <small class="text-muted d-block">Todas las áreas</small>
                @else
                  <span class="badge bg-info text-dark">{{ strtoupper((string)$rk) }}</span>
                  <small class="text-muted d-block">Solo esta área</small>
                @endif
              </td>

              <td>
                <span class="badge bg-light text-dark border">
                  {{ $roleNames[$roleKey] ?? ($roleKey ?: '—') }}
                </span>
                @if($roleKey === 'subdirection' || $roleKey === 'treasury')
                  <small class="text-muted d-block">Orden automático</small>
                @endif
              </td>

              <td>{{ $s['position'] ?? '—' }}</td>

              <td class="text-center">
                @if($sig !== '')
                  <img src="{{ asset('storage/'.$sig) }}" style="height:45px" alt="Firma">
                @else
                  —
                @endif
              </td>

              <td class="text-center">
                <span class="badge bg-{{ $active ? 'success' : 'secondary' }}">
                  {{ $active ? 'Activo' : 'Inactivo' }}
                </span>
              </td>

              <td class="text-center">
                <a href="{{ route('gdf.subdirection.authorization_signers.edit', $id) }}"
                   class="btn btn-sm btn-warning">Editar</a>

                <form action="{{ route('gdf.subdirection.authorization_signers.destroy', $id) }}"
                      method="POST" class="d-inline">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-sm btn-danger"
                          onclick="return confirm('¿Deseas eliminar este firmante?')">
                    Eliminar
                  </button>
                </form>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="9" class="text-center text-muted p-4">
                No hay firmantes configurados.
              </td>
            </tr>
          @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-3 text-muted">
    Total: <strong>{{ is_array($signers) ? count($signers) : 0 }}</strong>
  </div>

</div>
@endsection
