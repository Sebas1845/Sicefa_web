@extends('gdf::layouts.masteruser')

@section('title', 'GDF | Firmantes de Autorización')

@section('content')
@php
  $mode   = $mode ?? 'create';
  $module = $module ?? 'gdf';
  $areaKey = $areaKey ?? 'academic';

  $row   = $row ?? [];
  $rowId = $row['id'] ?? null;

  $areas = $areas ?? collect();

  $roleNames = [
    'subdirection' => 'Subdirección (GLOBAL)',
    'coordination' => 'Coordinación (por Área)',
    'treasury'     => 'Tesorería (GLOBAL)',
    'support'      => 'Apoyo (por Área)',
  ];
@endphp

<div class="container-fluid">

  <div class="row mb-3">
    <div class="col">
      <h4 class="mb-0">{{ $mode === 'create' ? 'Crear firmante' : 'Editar firmante' }}</h4>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k)) <div class="alert alert-{{ $type }}">{{ session($k) }}</div> @endif
  @endforeach

  @if($errors->any())
    <div class="alert alert-danger">
      <strong>Hay errores:</strong>
      <ul class="mb-0">
        @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
      </ul>
    </div>
  @endif

  <form method="POST"
        action="{{ $mode === 'create'
              ? route('gdf.subdirection.authorization_signers.store')
              : route('gdf.subdirection.authorization_signers.update', $rowId) }}"
        enctype="multipart/form-data">
    @csrf
    @if($mode === 'edit') @method('PUT') @endif

    {{-- ids ocultos --}}
    <input type="hidden" name="person_id" id="person_id" value="{{ old('person_id', $row['person_id'] ?? '') }}">
    <input type="hidden" name="user_id"   id="user_id"   value="{{ old('user_id',   $row['user_id'] ?? '') }}">

    {{-- Rol / Módulo / Alcance --}}
    <div class="card mb-3">
      <div class="card-body">
        <div class="row">

          <div class="col-md-4 mb-3">
            <label class="form-label">Rol (define el ORDEN)</label>
            <select name="role_key" id="role_key" class="form-control" required>
              @foreach($roleNames as $k => $txt)
                <option value="{{ $k }}" {{ old('role_key', $row['role_key'] ?? '') === $k ? 'selected':'' }}>
                  {{ $txt }}
                </option>
              @endforeach
            </select>
            <small class="text-muted">
              El orden se calcula automático: Subdirección → Coordinación → Tesorería → Apoyo.
            </small>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Módulo</label>
            <select name="module" class="form-control" required>
              <option value="gdf"   {{ old('module', $row['module'] ?? $module) === 'gdf' ? 'selected':'' }}>GDF</option>
              <option value="sitrav"{{ old('module', $row['module'] ?? $module) === 'sitrav' ? 'selected':'' }}>SITRAV</option>
              <option value="both"  {{ old('module', $row['module'] ?? $module) === 'both' ? 'selected':'' }}>AMBOS</option>
            </select>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Alcance (Área)</label>
            <select name="area_key" id="area_key" class="form-control">
              <option value="" {{ old('area_key', $row['area_key'] ?? '') === '' ? 'selected':'' }}>GLOBAL (todas las áreas)</option>
              <option value="academic" {{ old('area_key', $row['area_key'] ?? $areaKey) === 'academic' ? 'selected':'' }}>Académica</option>
              <option value="campesena"{{ old('area_key', $row['area_key'] ?? $areaKey) === 'campesena' ? 'selected':'' }}>Campesena</option>
            </select>
            <small class="text-muted">
              Subdirección y Tesorería deben ser GLOBAL.
            </small>
          </div>

        </div>
      </div>
    </div>

    {{-- Selección de persona: por Área + buscar por nombre/cédula --}}
    <div class="card mb-3">
      <div class="card-body">

        <div class="row">
          <div class="col-md-4 mb-3">
            <label class="form-label">Buscar por ÁREA</label>
            <select id="area_id" class="form-control">
              <option value="">Seleccione...</option>
              @foreach($areas as $a)
                <option value="{{ $a->id }}">{{ $a->name }}</option>
              @endforeach
            </select>
            <small class="text-muted">Lista personas asignadas a esa área (gdf_area_user).</small>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Buscar dentro del área (opcional)</label>
            <input type="text" id="area_q" class="form-control" placeholder="Nombre o cédula (en esa área)">
            <small class="text-muted">Escribe 2+ caracteres y recarga lista.</small>
          </div>

          <div class="col-md-4 mb-3">
            <label class="form-label">Persona (por área)</label>
            <select id="person_pick_area" class="form-control">
              <option value="">Seleccione un área primero...</option>
            </select>
          </div>
        </div>

        <hr class="my-3">

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Buscar GLOBAL (sin área) por nombre/cédula</label>
            <div class="input-group">
              <input type="text" id="global_q" class="form-control" placeholder="Ej: Juan / 12345">
              <button type="button" id="btn_global_search" class="btn btn-outline-secondary">Buscar</button>
            </div>
            <small class="text-muted">Útil para Subdirección/Tesorería (global).</small>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Resultados búsqueda GLOBAL</label>
            <select id="person_pick_global" class="form-control">
              <option value="">Escribe y busca...</option>
            </select>
          </div>
        </div>

        <div class="mt-2">
          <small class="text-muted">Áreas asignadas de la persona:</small>
          <div id="person_areas_box" class="mt-1"></div>
        </div>

      </div>
    </div>

    {{-- Datos a guardar en JSON --}}
    <div class="card mb-3">
      <div class="card-body">

        <div class="row">
          <div class="col-md-7 mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="person_name" id="person_name" class="form-control"
                   value="{{ old('person_name', $row['person_name'] ?? '') }}" required>
          </div>

          <div class="col-md-5 mb-3">
            <label class="form-label">Documento (cédula)</label>
            <input type="text" name="person_document" id="person_document" class="form-control"
                   value="{{ old('person_document', $row['person_document'] ?? '') }}" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Correo (opcional)</label>
            <input type="text" name="person_email" id="person_email" class="form-control"
                   value="{{ old('person_email', $row['person_email'] ?? '') }}">
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Teléfono (opcional)</label>
            <input type="text" name="person_phone" id="person_phone" class="form-control"
                   value="{{ old('person_phone', $row['person_phone'] ?? '') }}">
          </div>
        </div>

      </div>
    </div>

    {{-- Etiqueta/Cargo --}}
    <div class="card mb-3">
      <div class="card-body">
        <div class="row">

          <div class="col-md-6 mb-3">
            <label class="form-label">Etiqueta</label>
            <input type="text" name="label" class="form-control"
                   value="{{ old('label', $row['label'] ?? '') }}"
                   placeholder="Ej: Subdirección / Coordinación / Tesorería / Apoyo" required>
          </div>

          <div class="col-md-6 mb-3">
            <label class="form-label">Cargo (opcional)</label>
            <input type="text" name="position" class="form-control"
                   value="{{ old('position', $row['position'] ?? '') }}"
                   placeholder="Ej: Subdirector(a), Coordinador(a), Tesorero(a)">
          </div>

        </div>
      </div>
    </div>

    {{-- Firma --}}
    <div class="card mb-3">
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Archivo de firma (opcional)</label>
          <input type="file" name="signature" class="form-control">
          <small class="text-muted">PNG / JPG / JPEG / WEBP (máx 2MB)</small>
        </div>

        @if(!empty($row['signature_path']))
          <div class="mt-2">
            <small class="text-muted">Firma actual:</small><br>
            <img src="{{ asset('storage/'.$row['signature_path']) }}"
                 style="height:90px; border:1px solid #ccc; padding:4px;" alt="Firma actual">
          </div>
        @endif
      </div>
    </div>

    {{-- Estado --}}
    <div class="card mb-3">
      <div class="card-body">
        @php $activeOld = old('active', (int)($row['active'] ?? 1)); @endphp
        <div class="form-check">
          <input type="checkbox" name="active" class="form-check-input" id="active" value="1"
                 {{ (int)$activeOld === 1 ? 'checked' : '' }}>
          <label class="form-check-label" for="active">Firmante activo</label>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-success">
        {{ $mode === 'create' ? 'Guardar firmante' : 'Actualizar firmante' }}
      </button>

      <a href="{{ route('gdf.subdirection.authorization_signers.index', [
              'module' => old('module', $row['module'] ?? $module),
              'area_key' => old('area_key', $row['area_key'] ?? 'all') ?: 'all'
          ]) }}"
         class="btn btn-secondary">
        Cancelar
      </a>
    </div>

  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const roleSel    = document.getElementById('role_key');
  const areaKeySel = document.getElementById('area_key');

  const areaSel    = document.getElementById('area_id');
  const areaQ      = document.getElementById('area_q');
  const pickArea   = document.getElementById('person_pick_area');

  const globalQ    = document.getElementById('global_q');
  const btnGlobal  = document.getElementById('btn_global_search');
  const pickGlobal = document.getElementById('person_pick_global');

  const personId   = document.getElementById('person_id');
  const userId     = document.getElementById('user_id');

  const personName  = document.getElementById('person_name');
  const personDoc   = document.getElementById('person_document');
  const personEmail = document.getElementById('person_email');
  const personPhone = document.getElementById('person_phone');

  const areasBox = document.getElementById('person_areas_box');

  const epByArea = @json(route('gdf.subdirection.authorization_signers.people_by_area'));
  const epSearch = @json(route('gdf.subdirection.authorization_signers.people_search'));

  /* ====== UI helpers ====== */
  const resetSelect = (sel, msg) => {
    sel.innerHTML = '';
    const op = document.createElement('option');
    op.value = '';
    op.textContent = msg || 'Seleccione...';
    sel.appendChild(op);
  };

  const renderAreas = (areas, areaText) => {
    areasBox.innerHTML = '';
    if (Array.isArray(areas) && areas.length) {
      areas.forEach(a => {
        const b = document.createElement('span');
        b.className = 'badge bg-info text-dark me-1 mb-1';
        b.textContent = a.name;
        areasBox.appendChild(b);
      });
    } else {
      const b = document.createElement('span');
      b.className = 'badge bg-dark';
      b.textContent = areaText || 'Sin áreas (Global)';
      areasBox.appendChild(b);
    }
  };

  const fillPerson = (d) => {
    if (!d) return;

    if (d.person_id) personId.value = d.person_id;
    if (d.user_id) userId.value = d.user_id;

    if (d.person_name) personName.value = d.person_name;
    if (typeof d.person_document !== 'undefined') personDoc.value = d.person_document || '';

    if (typeof d.person_email !== 'undefined') personEmail.value = d.person_email || '';
    if (typeof d.person_phone !== 'undefined') personPhone.value = d.person_phone || '';

    renderAreas(d.areas, d.area_text);
  };

  /* ====== Reglas: roles globales fuerzan area_key vacío ====== */
  const applyRoleRules = () => {
    const rk = roleSel.value;
    const isGlobalRole = (rk === 'subdirection' || rk === 'treasury');
    if (isGlobalRole) {
      areaKeySel.value = '';
      areaKeySel.setAttribute('disabled', 'disabled');
    } else {
      areaKeySel.removeAttribute('disabled');
    }
  };
  roleSel.addEventListener('change', applyRoleRules);
  applyRoleRules();

  /* ====== Por área (con búsqueda dentro del área) ====== */
  const loadAreaPeople = async () => {
    const areaId = areaSel.value;
    const q = (areaQ.value || '').trim();

    if (!areaId) { resetSelect(pickArea, 'Seleccione un área primero...'); return; }
    resetSelect(pickArea, 'Cargando...');

    try {
      const url = epByArea + '?area_id=' + encodeURIComponent(areaId) + '&q=' + encodeURIComponent(q);
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      const json = await res.json();
      const data = Array.isArray(json.data) ? json.data : [];

      pickArea.innerHTML = '';
      const op0 = document.createElement('option');
      op0.value = '';
      op0.textContent = data.length ? 'Seleccione...' : 'Sin resultados en esta área';
      pickArea.appendChild(op0);

      data.forEach(item => {
        const op = document.createElement('option');
        op.value = item.person_id || '';
        op.textContent = item.label || '—';
        op.dataset.payload = JSON.stringify(item);
        pickArea.appendChild(op);
      });

    } catch (e) {
      console.error(e);
      resetSelect(pickArea, 'Error cargando personas');
    }
  };

  areaSel.addEventListener('change', loadAreaPeople);
  areaQ.addEventListener('keyup', (e) => {
    const v = (areaQ.value || '').trim();
    if (v.length === 0 || v.length >= 2) loadAreaPeople();
  });

  pickArea.addEventListener('change', () => {
    const opt = pickArea.options[pickArea.selectedIndex];
    if (!opt || !opt.value) return;
    try { fillPerson(JSON.parse(opt.dataset.payload || '{}')); } catch {}
  });

  /* ====== Búsqueda global por nombre/cédula ====== */
  const doGlobalSearch = async () => {
    const q = (globalQ.value || '').trim();
    if (q.length < 2) {
      resetSelect(pickGlobal, 'Escribe 2+ caracteres...');
      return;
    }

    resetSelect(pickGlobal, 'Buscando...');
    try {
      const url = epSearch + '?q=' + encodeURIComponent(q);
      const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }});
      const json = await res.json();
      const data = Array.isArray(json.data) ? json.data : [];

      pickGlobal.innerHTML = '';
      const op0 = document.createElement('option');
      op0.value = '';
      op0.textContent = data.length ? 'Seleccione...' : 'Sin resultados';
      pickGlobal.appendChild(op0);

      data.forEach(item => {
        const op = document.createElement('option');
        op.value = item.person_id || '';
        op.textContent = item.label || '—';
        op.dataset.payload = JSON.stringify(item);
        pickGlobal.appendChild(op);
      });

    } catch (e) {
      console.error(e);
      resetSelect(pickGlobal, 'Error en búsqueda');
    }
  };

  btnGlobal.addEventListener('click', doGlobalSearch);
  globalQ.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); doGlobalSearch(); }
  });

  pickGlobal.addEventListener('change', () => {
    const opt = pickGlobal.options[pickGlobal.selectedIndex];
    if (!opt || !opt.value) return;
    try { fillPerson(JSON.parse(opt.dataset.payload || '{}')); } catch {}
  });

  // init
  resetSelect(pickArea, 'Seleccione un área primero...');
  resetSelect(pickGlobal, 'Escribe y busca...');
  renderAreas([], '');
});
</script>
@endsection
