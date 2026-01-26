{{-- Modules/GDF/Resources/views/coordination/motorcycles/assign.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Asignar moto')

@section('content')
@php
  $routePrefix = ($areaKey === 'campesena') ? 'campesena' : 'academic';
  $areaLabel   = $areaKey === 'academic' ? 'COORDINACIÓN ACADÉMICA' : 'CAMPESENA';

  $quota       = (int)($quota ?? 0);
  $used        = (int)($used ?? 0);
  $available   = (int)($available ?? max($quota - $used, 0));

  $inventory   = $inventory ?? collect();
  $motorcycles = $motorcycles ?? collect();
  $activeAssignments = $activeAssignments ?? null;

  $searchRoute = $searchRoute ?? '';
  $postRoute   = $postRoute ?? '';
  $peopleRoute = $peopleRoute ?? ''; // NUEVO: lista de personas del área (endpoint peopleInArea)

  /**
   * IMPORTANTE:
   * Si quieres bloqueo UI 100% real para "1 moto por persona",
   * lo ideal es que desde el controlador mandes $activeMapAll (sin paginar).
   *
   * Este fallback arma el mapa con lo que haya (página actual),
   * por eso es posible que no detecte alguien que esté en otra página.
   */
  $activeMap = collect();
  if (isset($activeMapAll) && $activeMapAll) {
      $activeMap = $activeMapAll; // preferido (del controlador)
  } elseif ($activeAssignments) {
      $items = is_object($activeAssignments) && method_exists($activeAssignments, 'items')
          ? collect($activeAssignments->items())
          : collect($activeAssignments);

      $activeMap = $items->filter(fn($a) => $a?->person_id)
          ->mapWithKeys(function($a){
              $p = $a->person ?? null;
              $m = $a->motorcycle ?? null;
              $name = $p ? trim(($p->first_name ?? '').' '.($p->first_last_name ?? '').' '.($p->second_last_name ?? '')) : '—';
              return [(int)$a->person_id => [
                  'status' => $a->status ?? 'approved',
                  'plate'  => $m?->plate ?? '—',
                  'name'   => $name,
              ]];
          });
  }
@endphp

<div class="container py-4">

  {{-- Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / Motos / {{ $areaLabel }}</div>
      <h4 class="mb-0">Asignación y registro de motos</h4>
      <div class="text-muted small">
        Área: <strong>{{ $areaLabel }}</strong>
        <span class="mx-2">|</span>
        Año: <strong>{{ $year }}</strong>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-secondary" href="{{ route('gdf.' . $routePrefix . '.motorcycles.index') }}">
        Volver a Motos
      </a>

      <a class="btn btn-outline-secondary" href="{{ route('gdf.' . $routePrefix . '.review') }}">
        Volver a Revisión
      </a>

      <a href="{{ route('gdf.subdirection.motorcycles.index') }}" class="btn btn-outline-secondary">
        Inventario (Subdirección)
      </a>
    </div>
  </div>

  {{-- Alerts --}}
  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  @if ($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="row g-3">

    {{-- Col Izq --}}
    <div class="col-lg-4">

      {{-- Cupo --}}
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          <span>Cupo del área</span>
          <span class="badge bg-{{ $available > 0 ? 'success' : 'danger' }}">
            {{ $available }} disp.
          </span>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between"><span class="text-muted">Cupo:</span><strong>{{ $quota }}</strong></div>
          <div class="d-flex justify-content-between"><span class="text-muted">Ocupadas:</span><strong>{{ $used }}</strong></div>
          <div class="d-flex justify-content-between">
            <span class="text-muted">Disponibles:</span>
            <strong class="{{ $available > 0 ? 'text-success' : 'text-danger' }}">{{ $available }}</strong>
          </div>

          <div class="text-muted small mt-2">
            Si <strong>Disponibles = 0</strong>, no se permiten nuevas ubicaciones/asignaciones en esta área.
          </div>

          <div class="d-grid gap-2 mt-3">
            <button class="btn btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#createMotorcycleModal"
                    id="btnOpenCreateModal"
                    {{ $available <= 0 ? 'disabled' : '' }}>
              + Crear moto (y asignar)
            </button>

            <a class="btn btn-outline-warning"
               href="{{ route('gdf.' . $routePrefix . '.motorcycles.quota_status', ['area'=>$areaKey, 'year'=>$year]) }}">
              Ver diagnóstico cupo (opcional)
            </a>
          </div>
        </div>
      </div>

      {{-- Inventario del área --}}
      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-bold">Inventario en el área</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Placa</th>
                  <th>Estado</th>
                  <th class="text-end">Km</th>
                </tr>
              </thead>
              <tbody>
                @forelse($inventory as $m)
                  <tr>
                    <td class="fw-semibold">{{ $m->plate }}</td>
                    <td>
                      <span class="badge bg-{{ $m->status === 'available' ? 'success' : ($m->status === 'assigned' ? 'primary' : 'secondary') }}">
                        {{ $m->status }}
                      </span>
                    </td>
                    <td class="text-end">{{ number_format((int)($m->current_odometer ?? 0), 0, ',', '.') }}</td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">No hay motos ubicadas en esta área.</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Personas con moto --}}
      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          <span>Personas con moto</span>
          <span class="text-muted small">Activas: <code>approved</code>/<code>delivered</code></span>
        </div>

        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Persona</th>
                  <th style="width:110px">Moto</th>
                  <th style="width:95px">Estado</th>
                </tr>
              </thead>
              <tbody>
                @if($activeAssignments && count($activeAssignments))
                  @foreach($activeAssignments as $a)
                    @php
                      $p = $a->person ?? null;
                      $m = $a->motorcycle ?? null;
                      $fullName = $p ? trim(($p->first_name ?? '').' '.($p->first_last_name ?? '').' '.($p->second_last_name ?? '')) : '—';
                      $badge = ($a->status === 'delivered') ? 'success' : 'primary';
                    @endphp
                    <tr>
                      <td>
                        <div class="fw-semibold">{{ $fullName }}</div>
                        <div class="text-muted small">{{ $p?->document_number ?? '—' }}</div>
                      </td>
                      <td class="fw-semibold">{{ $m?->plate ?? '—' }}</td>
                      <td><span class="badge bg-{{ $badge }}">{{ $a->status }}</span></td>
                    </tr>
                  @endforeach
                @else
                  <tr>
                    <td colspan="3" class="text-center text-muted py-3">
                      No hay asignaciones activas en esta área.
                    </td>
                  </tr>
                @endif
              </tbody>
            </table>
          </div>
        </div>

        @if($activeAssignments && is_object($activeAssignments) && method_exists($activeAssignments, 'links'))
          <div class="card-footer bg-white">
            {{ $activeAssignments->links() }}
          </div>
        @endif
      </div>

    </div>

    {{-- Col Der --}}
    <div class="col-lg-8">

      {{-- ALERTA UI: persona ya tiene moto --}}
      <div id="uiPersonHasMotoAlert" class="alert alert-danger border-0 shadow-sm d-none">
        <div class="fw-bold mb-1">Esta persona ya tiene una moto asignada</div>
        <div id="uiPersonHasMotoText" class="small"></div>
      </div>

      {{-- ALERTA UI: falta seleccionar persona --}}
      <div id="uiNeedPersonAlert" class="alert alert-warning border-0 shadow-sm d-none">
        Debes seleccionar una persona antes de crear/asignar una moto.
      </div>

      {{-- Personas del área (lista) --}}
      <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
          <span>Personas del área</span>
          <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="peopleFilter" style="width: 180px">
              <option value="all">Todos</option>
              <option value="employee">Solo planta</option>
              <option value="contractor">Solo contratistas</option>
              <option value="ok">Contrato OK (gracia 8 días)</option>
              <option value="nok">Contrato vencido > 8 días</option>
              <option value="hasmoto">Con moto asignada</option>
              <option value="nomoto">Sin moto asignada</option>
            </select>
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnLoadPeople" {{ $peopleRoute ? '' : 'disabled' }}>
              Cargar
            </button>
          </div>
        </div>
        <div class="card-body">
          <div class="text-muted small mb-2">
            Selecciona una persona desde esta lista o desde el buscador por cédula.
          </div>
          <div id="peopleAreaList" class="small text-muted">Sin datos aún.</div>
        </div>
      </div>

      {{-- Asignar existente --}}
      <form method="POST" action="{{ $postRoute }}" id="formAssignExisting" class="mt-3">
        @csrf

        <input type="hidden" name="area" value="{{ $areaKey }}">
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="motorcycle_mode" value="existing">

        <div class="card shadow-sm">
          <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>Asignar moto existente</span>
            <span class="text-muted small">Registra asignación en <code>approved</code></span>
          </div>

          <div class="card-body">

            {{-- Buscar persona --}}
            <div class="mb-3">
              <label class="form-label">Buscar persona (cédula)</label>
              <div class="input-group">
                <input id="personSearch" type="text" class="form-control" placeholder="Escribe cédula (mínimo 3 dígitos)">
                <button type="button" class="btn btn-outline-primary" id="btnSearchPerson">Buscar</button>
              </div>
              <div class="form-text">
                Regla: <strong>1 moto por persona</strong>. Si ya tiene moto, el sistema bloqueará el envío.
              </div>
            </div>

            <input type="hidden" name="person_id" id="person_id" value="{{ old('person_id') }}">
            <input type="hidden" name="person_source" id="person_source" value="{{ old('person_source') }}">

            <div class="mb-3">
              <label class="form-label">Persona seleccionada</label>
              <input id="person_label" type="text" class="form-control" readonly
                     value="{{ old('person_label') }}"
                     placeholder="Aún no seleccionas una persona">
            </div>

            {{-- Moto --}}
            <div class="mb-3">
              <label class="form-label">Moto disponible</label>
              <select name="motorcycle_id" id="motorcycle_id" class="form-select" required {{ $available <= 0 ? 'disabled' : '' }}>
                <option value="">-- Selecciona --</option>
                @foreach($motorcycles as $m)
                  <option value="{{ $m->id }}" {{ (string)old('motorcycle_id') === (string)$m->id ? 'selected' : '' }}>
                    {{ $m->plate }}{{ $m->brand ? " - {$m->brand}" : '' }}{{ $m->model ? " ({$m->model})" : '' }}
                  </option>
                @endforeach
              </select>
              <div class="form-text">
                Solo motos <code>available</code> con área <code>null</code> o el área actual.
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Notas</label>
              <textarea name="notes" class="form-control" rows="3" placeholder="Opcional">{{ old('notes') }}</textarea>
            </div>

            <button class="btn btn-success" id="btnSubmitExisting" {{ $available <= 0 ? 'disabled' : '' }}>
              Crear asignación
            </button>

          </div>
        </div>
      </form>

      {{-- Resultados búsqueda --}}
      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white fw-bold">Resultados de búsqueda</div>
        <div class="card-body">
          <div id="results" class="small text-muted">Sin resultados aún.</div>
        </div>
      </div>

    </div>

  </div>
</div>

{{-- MODAL: crear moto y asignar --}}
<div class="modal fade" id="createMotorcycleModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" action="{{ $postRoute }}" id="formAssignNew">
        @csrf

        <input type="hidden" name="area" value="{{ $areaKey }}">
        <input type="hidden" name="year" value="{{ $year }}">
        <input type="hidden" name="motorcycle_mode" value="new">

        <div class="modal-header">
          <h5 class="modal-title">Crear moto y asignar</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>

        <div class="modal-body">

          <div class="alert alert-info mb-3">
            Este registro ubicará la moto en el área y creará la asignación en estado <code>approved</code>.
          </div>

          {{-- Persona --}}
          <div class="mb-3">
            <label class="form-label">Persona seleccionada</label>
            <div class="input-group">
              <input id="person_label_modal" type="text" class="form-control" readonly
                     placeholder="Selecciona primero la persona en la pantalla principal">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Volver</button>
            </div>
            <div class="form-text">Selecciona la persona en la pantalla principal, luego abre este modal.</div>
          </div>

          <input type="hidden" name="person_id" id="person_id_modal" value="{{ old('person_id') }}">
          <input type="hidden" name="person_source" id="person_source_modal" value="{{ old('person_source') }}">

          <hr>

          {{-- Datos moto --}}
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">Placa</label>
              <input name="plate" class="form-control" maxlength="20" value="{{ old('plate') }}" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Marca</label>
              <input name="brand" class="form-control" maxlength="60" value="{{ old('brand') }}">
            </div>
            <div class="col-md-4">
              <label class="form-label">Modelo</label>
              <input name="model" class="form-control" maxlength="60" value="{{ old('model') }}">
            </div>
          </div>

          <div class="row g-2 mt-1">
            <div class="col-md-4">
              <label class="form-label">Kilometraje actual</label>
              <input name="current_odometer" type="number" min="0" class="form-control" value="{{ old('current_odometer') }}" required>
            </div>
            <div class="col-md-8">
              <label class="form-label">Notas</label>
              <input name="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}">
            </div>
          </div>

          <div class="text-muted small mt-2">
            Si el cupo está agotado, no se permitirá crear/ubicar una moto nueva.
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button class="btn btn-primary" id="btnSubmitNew" {{ $available <= 0 ? 'disabled' : '' }}>
            Crear y asignar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
(function(){
  const area = @json($areaKey);
  const year = @json($year);
  const searchUrl = @json($searchRoute);
  const peopleRoute = @json($peopleRoute);

  // Mapa person_id => info asignación activa (puede ser parcial si viene de paginación)
  const activeMap = @json($activeMap);

  const personSearch = document.getElementById('personSearch');
  const btnSearch = document.getElementById('btnSearchPerson');
  const results = document.getElementById('results');

  const personId = document.getElementById('person_id');
  const personSource = document.getElementById('person_source');
  const personLabel = document.getElementById('person_label');

  const motorcycleId = document.getElementById('motorcycle_id');

  // Alerts UI
  const uiPersonHasMotoAlert = document.getElementById('uiPersonHasMotoAlert');
  const uiPersonHasMotoText = document.getElementById('uiPersonHasMotoText');
  const uiNeedPersonAlert = document.getElementById('uiNeedPersonAlert');

  // Buttons
  const btnSubmitExisting = document.getElementById('btnSubmitExisting');
  const btnOpenCreateModal = document.getElementById('btnOpenCreateModal');
  const btnSubmitNew = document.getElementById('btnSubmitNew');

  // Modal mirror
  const personIdModal = document.getElementById('person_id_modal');
  const personSourceModal = document.getElementById('person_source_modal');
  const personLabelModal = document.getElementById('person_label_modal');

  // People in area
  const btnLoadPeople = document.getElementById('btnLoadPeople');
  const peopleAreaList = document.getElementById('peopleAreaList');
  const peopleFilter = document.getElementById('peopleFilter');

  let cachedPeople = [];

  function hideAlerts(){
    uiPersonHasMotoAlert.classList.add('d-none');
    uiNeedPersonAlert.classList.add('d-none');
    uiPersonHasMotoText.textContent = '';
  }

  function showNeedPerson(){
    hideAlerts();
    uiNeedPersonAlert.classList.remove('d-none');
    uiNeedPersonAlert.scrollIntoView({behavior:'smooth', block:'start'});
  }

  function showHasMoto(pid){
    hideAlerts();
    const info = activeMap[String(pid)] || null;
    const plate = info && info.plate ? info.plate : '—';
    const status = info && info.status ? info.status : 'approved';
    const name = info && info.name ? info.name : 'La persona';

    uiPersonHasMotoText.textContent =
      `${name} ya tiene la moto ${plate} en estado ${status}. Debes finalizar/devolver esa asignación antes de asignar otra.`;

    uiPersonHasMotoAlert.classList.remove('d-none');
    uiPersonHasMotoAlert.scrollIntoView({behavior:'smooth', block:'start'});
  }

  function isPersonSelected(){
    return (personId.value || '').trim() !== '';
  }

  function personHasActiveMoto(pid){
    const x = (pid ?? personId.value ?? '').toString().trim();
    if(!x) return false;
    return !!activeMap[String(x)];
  }

  function selectPerson(pid, src, lbl){
    personId.value = pid;
    personSource.value = src;
    personLabel.value = lbl;

    personIdModal.value = pid;
    personSourceModal.value = src;
    personLabelModal.value = lbl;

    enforcePersonRules();
  }

  function enforcePersonRules(){
    if(!isPersonSelected()){
      btnSubmitExisting.disabled = true;
      if(btnOpenCreateModal) btnOpenCreateModal.disabled = true;
      if(btnSubmitNew) btnSubmitNew.disabled = true;
      return;
    }

    if(personHasActiveMoto()){
      btnSubmitExisting.disabled = true;
      if(btnOpenCreateModal) btnOpenCreateModal.disabled = true;
      if(btnSubmitNew) btnSubmitNew.disabled = true;
      showHasMoto(personId.value);
      return;
    }

    hideAlerts();
    btnSubmitExisting.disabled = false;
    if(btnOpenCreateModal) btnOpenCreateModal.disabled = false;
    if(btnSubmitNew) btnSubmitNew.disabled = false;
  }

  function renderPeople(list){
    if(!list || list.length === 0){
      peopleAreaList.innerHTML = '<span class="text-muted">No hay personas para mostrar.</span>';
      return;
    }

    const f = (peopleFilter?.value || 'all');

    const filtered = list.filter(it => {
      const hasMoto = personHasActiveMoto(it.person_id);
      if(f === 'all') return true;
      if(f === 'employee') return it.source === 'employee';
      if(f === 'contractor') return it.source === 'contractor';
      if(f === 'ok') return !!it.is_active_contract;
      if(f === 'nok') return !it.is_active_contract;
      if(f === 'hasmoto') return hasMoto;
      if(f === 'nomoto') return !hasMoto;
      return true;
    });

    const html = filtered.map(it => {
      const ok = it.is_active_contract ? 'text-success' : 'text-danger';
      const contract = it.source === 'contractor'
        ? `<span class="${ok}">Contrato: ${it.is_active_contract ? 'OK (gracia 8 días)' : 'vencido > 8 días'}</span>`
        : `<span class="text-success">Empleado</span>`;

      const alreadyHasMoto = personHasActiveMoto(it.person_id);
      const warn = alreadyHasMoto ? `<div class="text-danger mt-1">Ya tiene moto asignada.</div>` : '';

      const end = it.contract_end_date ? `<span class="text-muted ms-2">Fin: ${it.contract_end_date}</span>` : '';

      return `
        <div class="border rounded p-2 mb-2">
          <div class="fw-semibold">${it.full_name} - ${it.document_number}</div>
          <div class="text-muted">Fuente: ${it.source} | ${contract}${end}</div>
          ${warn}
          <button type="button" class="btn btn-sm ${alreadyHasMoto ? 'btn-outline-danger' : 'btn-outline-primary'} mt-2"
            data-person-id="${it.person_id}"
            data-person-source="${it.source}"
            data-person-label="${it.full_name} (${it.document_number})">
            Seleccionar
          </button>
        </div>
      `;
    }).join('');

    peopleAreaList.innerHTML = html || '<span class="text-muted">No hay personas para mostrar con ese filtro.</span>';

    peopleAreaList.querySelectorAll('button[data-person-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const pid = btn.getAttribute('data-person-id');
        const src = btn.getAttribute('data-person-source');
        const lbl = btn.getAttribute('data-person-label');
        selectPerson(pid, src, lbl);
      });
    });
  }

  async function loadPeopleInArea(){
    if(!peopleRoute){
      peopleAreaList.innerHTML = '<span class="text-danger">peopleRoute no configurado.</span>';
      return;
    }

    peopleAreaList.innerHTML = 'Cargando...';

    const url = new URL(peopleRoute, window.location.origin);
    url.searchParams.set('area', area);
    url.searchParams.set('year', year);

    const res = await fetch(url.toString(), { headers: { 'Accept':'application/json' }});
    const data = await res.json();

    if(!data.ok || !data.items){
      peopleAreaList.innerHTML = '<span class="text-danger">No se pudo cargar la lista de personas.</span>';
      return;
    }

    cachedPeople = data.items || [];
    renderPeople(cachedPeople);
  }

  async function doSearch(){
    hideAlerts();

    const q = (personSearch.value || '').trim();
    if(q.length < 3){
      results.innerHTML = '<span class="text-muted">Escribe al menos 3 caracteres.</span>';
      return;
    }

    results.innerHTML = 'Buscando...';

    const url = new URL(searchUrl, window.location.origin);
    url.searchParams.set('q', q);
    url.searchParams.set('area', area);
    url.searchParams.set('year', year);

    const res = await fetch(url.toString(), { headers: { 'Accept':'application/json' }});
    const data = await res.json();

    if(!data.ok || !data.items || data.items.length === 0){
      results.innerHTML = '<span class="text-muted">No se encontraron coincidencias.</span>';
      return;
    }

    const html = data.items.map(it => {
      const ok = it.is_active_contract ? 'text-success' : 'text-danger';
      const contract = it.source === 'contractor'
        ? `<span class="${ok}">Contrato: ${it.is_active_contract ? 'OK (gracia 8 días)' : 'vencido > 8 días'}</span>`
        : `<span class="text-success">Empleado</span>`;

      const alreadyHasMoto = personHasActiveMoto(it.person_id);
      const warn = alreadyHasMoto ? `<div class="text-danger mt-1">Ya tiene moto asignada (según mapa actual).</div>` : '';

      return `
        <div class="border rounded p-2 mb-2">
          <div class="fw-semibold">${it.full_name} - ${it.document_number}</div>
          <div class="text-muted">Fuente: ${it.source} | ${contract}</div>
          ${warn}
          <button type="button" class="btn btn-sm ${alreadyHasMoto ? 'btn-outline-danger' : 'btn-outline-primary'} mt-2"
            data-person-id="${it.person_id}"
            data-person-source="${it.source}"
            data-person-label="${it.full_name} (${it.document_number})">
            Seleccionar
          </button>
        </div>
      `;
    }).join('');

    results.innerHTML = html;

    results.querySelectorAll('button[data-person-id]').forEach(btn => {
      btn.addEventListener('click', () => {
        const pid = btn.getAttribute('data-person-id');
        const src = btn.getAttribute('data-person-source');
        const lbl = btn.getAttribute('data-person-label');
        selectPerson(pid, src, lbl);
      });
    });
  }

  // Guardar existente: bloquea si no hay persona o si ya tiene moto
  document.getElementById('formAssignExisting').addEventListener('submit', function(e){
    hideAlerts();

    if(!isPersonSelected()){
      e.preventDefault();
      showNeedPerson();
      return;
    }
    if(personHasActiveMoto()){
      e.preventDefault();
      showHasMoto(personId.value);
      return;
    }
  });

  // Modal open: bloquea si no hay persona o si ya tiene moto
  if(btnOpenCreateModal){
    btnOpenCreateModal.addEventListener('click', function(e){
      hideAlerts();
      if(!isPersonSelected()){
        e.preventDefault();
        e.stopPropagation();
        showNeedPerson();
        return;
      }
      if(personHasActiveMoto()){
        e.preventDefault();
        e.stopPropagation();
        showHasMoto(personId.value);
        return;
      }
      // mirror modal
      personIdModal.value = personId.value;
      personSourceModal.value = personSource.value;
      personLabelModal.value = personLabel.value;
    });
  }

  // Modal submit: misma regla
  document.getElementById('formAssignNew').addEventListener('submit', function(e){
    hideAlerts();

    const pid = (personIdModal.value || '').trim();
    if(!pid){
      e.preventDefault();
      showNeedPerson();
      return;
    }
    if(personHasActiveMoto(pid)){
      e.preventDefault();
      showHasMoto(pid);
      return;
    }
  });

  // Eventos
  btnSearch?.addEventListener('click', doSearch);
  personSearch?.addEventListener('keydown', (e) => {
    if(e.key === 'Enter'){
      e.preventDefault();
      doSearch();
    }
  });

  btnLoadPeople?.addEventListener('click', loadPeopleInArea);
  peopleFilter?.addEventListener('change', () => renderPeople(cachedPeople));

  // Init
  if((personId.value || '').trim() !== ''){
    personIdModal.value = personId.value;
    personSourceModal.value = personSource.value;
    personLabelModal.value = personLabel.value;
    enforcePersonRules();
  } else {
    enforcePersonRules();
  }

  // UX: carga automática de la lista del área si existe endpoint
  if(peopleRoute){
    loadPeopleInArea().catch(()=>{});
  }

})();
</script>
@endsection
