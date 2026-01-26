@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Nueva solicitud')

@section('content')
@php
    $areaKey   = $areaKey ?? ($ctx['area'] ?? 'academic');
    $areaLabel = $areaKey === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA';

    $countryId = $countryId ?? (int) config('gdf.country_id', 25);
    $hasMoto   = (bool) ($hasMoto ?? ($motoLock['has_moto'] ?? false));

    $HUILA_ID = (int) ($HUILA_ID ?? 421);
    $vanAllowed = (bool) ($vanAllowed ?? false); // instructor false por defecto; Apoyo lo habilita
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<style>
  #gdfMap { height: 380px; border-radius: 12px; }
  .mapbox { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 12px; }
  .map-hint { font-size: 12px; color: #6c757d; margin-top: 6px; }
  .pill { display:inline-block; padding:.25rem .5rem; border-radius:999px; border:1px solid rgba(0,0,0,.1); background:#f8f9fa; font-size:.85rem; }
</style>

<div class="container py-4">
    <h2 class="mb-1">Nueva solicitud de desplazamiento</h2>
    <p class="text-muted mb-3">
        Origen = lo seleccionado en “Origen (Centro de formación)”. Destino = Municipio/Vereda (o pin si aún no eliges).
    </p>

    @if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    @if ($budgetRule['message'] ?? null)
        <div class="alert alert-info">{{ $budgetRule['message'] }}</div>
    @endif

    @if($hasMoto)
        <div class="alert alert-info">
            {{ $motoLock['message'] ?? 'Tienes una moto asignada.' }}
        </div>
    @endif

    <form method="POST" action="{{ route('gdf.instructor.requests.store') }}" id="gdfReqForm">
        @csrf

        <input type="hidden" name="area_id" value="{{ (int) $defaultAreaId }}">
        <input type="hidden" name="country_id" value="{{ (int) $countryId }}">

        {{-- ORIGEN: siempre se setea desde el select --}}
        <input type="hidden" name="origin_lat" id="origin_lat" value="">
        <input type="hidden" name="origin_lng" id="origin_lng" value="">

        {{-- DESTINO: vacío al cargar para evitar “Neiva restaurado” --}}
        <input type="hidden" name="destination_lat" id="destination_lat" value="">
        <input type="hidden" name="destination_lng" id="destination_lng" value="">

        <div class="card">
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Área</label>
                        <input class="form-control" value="{{ $areaLabel }}" disabled>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">
                            Rubro
                            @if(($budgetRule['required'] ?? false) === true)
                                <span class="text-danger">*</span>
                            @else
                                <span class="text-muted">(opcional)</span>
                            @endif
                        </label>

                        <select class="form-select @error('budget_item_id') is-invalid @enderror"
                                name="budget_item_id" id="budget_item_id"
                                @if(($budgetRule['required'] ?? false) === true) required @endif>
                            <option value="">— Selecciona —</option>
                        </select>

                        @error('budget_item_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <div class="form-text">
                            Se cargan los rubros asignados a tu usuario o, en académica, los del área si no tienes asignación.
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <select class="form-select" name="type" disabled>
                            <option value="desplazamiento" selected>Desplazamiento</option>
                        </select>
                    </div>

                    {{-- ORIGEN --}}
                    <div class="col-md-6">
                        <label class="form-label">Origen (Centro de formación)</label>
                        <select class="form-select @error('origin') is-invalid @enderror"
                                name="origin" id="origin" required>
                            @foreach ($origins as $o)
                                <option value="{{ $o['key'] }}"
                                        data-lat="{{ $o['lat'] }}"
                                        data-lng="{{ $o['lng'] }}"
                                        {{ old('origin','center_default') === $o['key'] ? 'selected' : '' }}>
                                    {{ $o['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('origin')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">El mapa usa estas coordenadas como origen.</div>
                    </div>

                    {{-- TRANSPORTE (nuevo) --}}
                    <div class="col-md-6">
                        <label class="form-label">Medio de transporte</label>
                        <select class="form-select @error('transport_mode') is-invalid @enderror"
                                name="transport_mode" id="transport_mode" required>
                            <option value="bus" {{ old('transport_mode','bus') === 'bus' ? 'selected' : '' }}>Bus</option>
                            <option value="van" {{ old('transport_mode') === 'van' ? 'selected' : '' }}>Camioneta</option>
                            <option value="motorcycle" {{ old('transport_mode') === 'motorcycle' ? 'selected' : '' }}>Moto</option>
                            <option value="air" {{ old('transport_mode') === 'air' ? 'selected' : '' }}>Aéreo</option>
                        </select>

                        @error('transport_mode')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror

                        <div class="form-text" id="transportHelp"></div>
                    </div>

                    {{-- DESTINO (catálogo) --}}
                    <div class="col-md-3">
                        <label class="form-label">Departamento destino</label>
                        <select class="form-select @error('department_id') is-invalid @enderror"
                                name="department_id" id="department_id" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($departments as $d)
                                @php
                                    $selectedDept = old('department_id')
                                        ? (int) old('department_id')
                                        : (int) $defaultDepartmentId;
                                @endphp
                                <option value="{{ $d->id }}" {{ $selectedDept === (int)$d->id ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Destino es</label>
                        <select class="form-select @error('place_type') is-invalid @enderror"
                                name="place_type" id="place_type" required>
                            <option value="municipio" {{ old('place_type','municipio') === 'municipio' ? 'selected' : '' }}>Municipio</option>
                            <option value="vereda" {{ old('place_type') === 'vereda' ? 'selected' : '' }}>Vereda</option>
                        </select>
                        @error('place_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Municipio</label>
                        <select class="form-select @error('municipality_id') is-invalid @enderror"
                                name="municipality_id" id="municipality_id">
                            <option value="">— Selecciona —</option>
                        </select>
                        @error('municipality_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6" id="veredaWrap" style="display:none;">
                        <label class="form-label">Vereda</label>
                        <select class="form-select @error('village_id') is-invalid @enderror"
                                name="village_id" id="village_id">
                            <option value="">— Selecciona —</option>
                        </select>
                        @error('village_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- MAPA --}}
                    <div class="col-12">
                        <div class="mapbox">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="fw-semibold">Mapa (ruta automática)</div>
                                <div class="text-muted small">
                                    Origen: <span class="pill" id="originLabel">—</span> |
                                    Destino: <span class="pill" id="destLabel">Pendiente</span>
                                </div>
                            </div>

                            <div class="map-hint">
                                Primero elige Municipio/Vereda para destino. Si aún no, puedes poner destino con clic/arrastre del pin.
                                (No se usa Neiva por defecto.)
                            </div>

                            <div class="mt-2" id="gdfMap"></div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-6">
                                    <div class="small text-muted">Destino (coords)</div>
                                    <div class="fw-semibold"><span id="destCoordsLabel">—</span></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-muted">Referencia</div>
                                    <div class="fw-semibold"><span id="destNameLabel">—</span></div>
                                </div>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-md-4">
                                    <div class="small text-muted">Ruta</div>
                                    <div class="fw-semibold"><span id="routeLabel">Selecciona destino</span></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">Distancia</div>
                                    <div class="fw-semibold"><span id="routeKm">—</span></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">Duración</div>
                                    <div class="fw-semibold"><span id="routeMin">—</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- FECHAS --}}
                    <div class="col-md-3">
                        <label class="form-label">Fecha inicio</label>
                        <input type="date" class="form-control @error('start_date') is-invalid @enderror"
                               name="start_date" id="start_date" value="{{ old('start_date') }}" required>
                        @error('start_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small" id="dateHelp1"></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha fin</label>
                        <input type="date" class="form-control @error('end_date') is-invalid @enderror"
                               name="end_date" id="end_date" value="{{ old('end_date') }}" required>
                        @error('end_date')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small" id="dateHelp2"></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Valor estimado (opcional)</label>
                        <input type="number" class="form-control @error('estimated_value') is-invalid @enderror"
                               name="estimated_value" id="estimated_value" value="{{ old('estimated_value') }}" min="0">
                        @error('estimated_value')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text text-muted small" id="rateHelp"></div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror"
                                  name="notes" rows="4">{{ old('notes') }}</textarea>
                        @error('notes')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary" type="submit">Enviar</button>
                    <a class="btn btn-outline-secondary" href="{{ route('gdf.instructor.dashboard') }}">Volver</a>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
(function() {
    const areaId = @json($defaultAreaId);

    const budgetSelect = document.getElementById('budget_item_id');

    const departmentSelect = document.getElementById('department_id');
    const placeTypeSelect = document.getElementById('place_type');
    const municipalitySelect = document.getElementById('municipality_id');
    const villageSelect = document.getElementById('village_id');
    const veredaWrap = document.getElementById('veredaWrap');

    const originSelect = document.getElementById('origin');

    const originLat = document.getElementById('origin_lat');
    const originLng = document.getElementById('origin_lng');
    const destLat = document.getElementById('destination_lat');
    const destLng = document.getElementById('destination_lng');

    const originLabel = document.getElementById('originLabel');
    const destLabel = document.getElementById('destLabel');

    const destCoordsLabel = document.getElementById('destCoordsLabel');
    const destNameLabel = document.getElementById('destNameLabel');

    const routeLabel = document.getElementById('routeLabel');
    const routeKm = document.getElementById('routeKm');
    const routeMin = document.getElementById('routeMin');

    const transportSelect = document.getElementById('transport_mode');
    const transportHelp = document.getElementById('transportHelp');

    const estimatedInput = document.getElementById('estimated_value');
    const rateHelp = document.getElementById('rateHelp');

    const dateHelp1 = document.getElementById('dateHelp1');
    const dateHelp2 = document.getElementById('dateHelp2');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    const HUILA_ID = @json((int)$HUILA_ID);
    const HAS_MOTO = @json((bool)$hasMoto);
    const VAN_ALLOWED = @json((bool)$vanAllowed);

    const oldMunicipality = @json(old('municipality_id'));
    const oldVillage = @json(old('village_id'));
    const oldPlaceType = @json(old('place_type', 'municipio'));
    const oldBudget = @json(old('budget_item_id'));

    let map, destMarker, originMarker, routeLayer = null;
    const OSRM_BASE = 'https://router.project-osrm.org/route/v1';

    // Rubros
    fetch(@json(route('gdf.instructor.catalog.budgetItems')) + '?area_id=' + areaId)
        .then(r => r.json())
        .then(items => {
            items.forEach(it => {
                const opt = document.createElement('option');
                opt.value = it.id;
                opt.textContent = (it.code ? (it.code + ' - ') : '') + it.name;
                if (oldBudget && String(oldBudget) === String(it.id)) opt.selected = true;
                budgetSelect.appendChild(opt);
            });
        });

    function setPlaceUI() {
        const isVereda = placeTypeSelect.value === 'vereda';
        veredaWrap.style.display = isVereda ? '' : 'none';
        if (!isVereda) villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
    }

    function isHuilaSelected() {
        const dep = parseInt(departmentSelect.value || '0', 10);
        return dep === HUILA_ID;
    }

    function hasChosenDestination() {
        const deptOk = departmentSelect.value && departmentSelect.value !== '';
        const munOk  = municipalitySelect.value && municipalitySelect.value !== '';
        if (!deptOk || !munOk) return false;

        if (placeTypeSelect.value === 'vereda') {
            return villageSelect.value && villageSelect.value !== '';
        }
        return true;
    }

    function setTransportOptionEnabled(value, enabled) {
        const opt = [...transportSelect.options].find(o => o.value === value);
        if (!opt) return;
        opt.disabled = !enabled;
    }

    function ensureValidTransportSelection(fallback) {
        const current = transportSelect.value;
        const opt = [...transportSelect.options].find(o => o.value === current);
        if (!opt || opt.disabled) transportSelect.value = fallback;
    }

    function applyTransportRules() {
        const inHuila = isHuilaSelected();

        ['bus','van','motorcycle','air'].forEach(v => setTransportOptionEnabled(v, false));

        if (inHuila) {
            setTransportOptionEnabled('bus', true);
            setTransportOptionEnabled('motorcycle', true);
            setTransportOptionEnabled('van', !!VAN_ALLOWED);
            setTransportOptionEnabled('air', false);

            if (HAS_MOTO) {
                transportSelect.value = 'motorcycle';
                transportSelect.disabled = true;
                transportHelp.textContent = 'Tienes moto asignada: dentro de Huila el sistema fuerza “Moto”.';
            } else {
                transportSelect.disabled = false;
                ensureValidTransportSelection('bus');
                transportHelp.textContent = VAN_ALLOWED
                    ? 'Dentro de Huila: Bus o Moto. Camioneta habilitada por Apoyo.'
                    : 'Dentro de Huila: Bus o Moto. Camioneta solo la asigna Apoyo.';
            }

        } else {
            setTransportOptionEnabled('bus', true);
            setTransportOptionEnabled('van', true);
            setTransportOptionEnabled('air', true);
            setTransportOptionEnabled('motorcycle', false);

            transportSelect.disabled = false;
            ensureValidTransportSelection('bus');

            transportHelp.textContent = HAS_MOTO
                ? 'Fuera de Huila: Bus/Camioneta/Aéreo. Moto no aplica aunque tengas moto asignada.'
                : 'Fuera de Huila: Bus/Camioneta/Aéreo. Moto no permitida.';
        }
    }

    function getSelectedText(selectEl) {
        const opt = selectEl.options[selectEl.selectedIndex];
        return opt ? opt.textContent.trim() : '';
    }

    async function geocode(query) {
        const url = `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`;
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) return null;
        const data = await res.json();
        if (!data || !data.length) return null;
        return { lat: parseFloat(data[0].lat), lng: parseFloat(data[0].lon), name: data[0].display_name };
    }

    function setOrigin(lat, lng, label) {
        originLat.value = String(lat);
        originLng.value = String(lng);
        originLabel.textContent = label || 'Origen';

        const ll = L.latLng(lat, lng);
        if (!originMarker) originMarker = L.marker(ll, { draggable: false }).addTo(map);
        else originMarker.setLatLng(ll);

        if (!destLat.value || !destLng.value) map.setView(ll, 11);

        updateRoute();
    }

    function setDestination(lat, lng, label, sourceTag) {
        destLat.value = String(lat);
        destLng.value = String(lng);

        destCoordsLabel.textContent = `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
        destNameLabel.textContent = label || 'Destino';
        destLabel.textContent = (sourceTag === 'selects') ? 'Por selección' : 'Por mapa';

        const ll = L.latLng(lat, lng);
        if (!destMarker) {
            destMarker = L.marker(ll, { draggable: true }).addTo(map);
            destMarker.on('dragend', () => {
                const pos = destMarker.getLatLng();
                if (hasChosenDestination()) return;
                setDestination(pos.lat, pos.lng, 'Destino (pin)', 'map');
                refreshSuggestedRate(); // rate por pin (solo si no hay selección)
            });
        } else destMarker.setLatLng(ll);

        updateRoute();
    }

    function clearDestinationAndRoute() {
        destLat.value = '';
        destLng.value = '';
        destCoordsLabel.textContent = '—';
        destNameLabel.textContent = '—';
        destLabel.textContent = 'Pendiente';

        routeLabel.textContent = 'Selecciona destino';
        routeKm.textContent = '—';
        routeMin.textContent = '—';

        if (destMarker) { destMarker.remove(); destMarker = null; }
        if (routeLayer) { routeLayer.remove(); routeLayer = null; }

        rateHelp.textContent = '';
    }

    function fmtKm(m) {
        const km = m / 1000;
        return `${km.toFixed(km >= 10 ? 1 : 2)} km`;
    }
    function fmtMin(s) {
        const min = Math.round(s / 60);
        if (min < 60) return `${min} min`;
        const h = Math.floor(min / 60);
        const m = min % 60;
        return `${h} h ${m} min`;
    }

    let routeAbort = null;

    async function updateRoute() {
        const oLat = parseFloat(originLat.value || '');
        const oLng = parseFloat(originLng.value || '');
        const dLat = parseFloat(destLat.value || '');
        const dLng = parseFloat(destLng.value || '');

        if ([oLat,oLng].some(v => Number.isNaN(v)) || [dLat,dLng].some(v => Number.isNaN(v))) {
            routeLabel.textContent = 'Selecciona destino';
            routeKm.textContent = '—';
            routeMin.textContent = '—';
            if (routeLayer) { routeLayer.remove(); routeLayer = null; }
            return;
        }

        if (routeAbort) routeAbort.abort();
        routeAbort = new AbortController();

        routeLabel.textContent = 'Calculando...';
        routeKm.textContent = '...';
        routeMin.textContent = '...';

        const coords = `${oLng},${oLat};${dLng},${dLat}`;
        const url = `${OSRM_BASE}/driving/${coords}?overview=full&geometries=geojson`;

        try {
            const res = await fetch(url, { signal: routeAbort.signal });
            if (!res.ok) throw new Error('OSRM error');
            const data = await res.json();
            if (!data.routes || !data.routes.length) throw new Error('Sin ruta');

            const r0 = data.routes[0];
            routeLabel.textContent = 'Ruta por carretera';
            routeKm.textContent = fmtKm(r0.distance);
            routeMin.textContent = fmtMin(r0.duration);

            if (routeLayer) routeLayer.remove();
            routeLayer = L.geoJSON(r0.geometry, { style: { weight: 5, opacity: 0.9 } }).addTo(map);

            const group = L.featureGroup([originMarker, destMarker, routeLayer]);
            map.fitBounds(group.getBounds().pad(0.2));
        } catch (e) {
            if (e.name === 'AbortError') return;
            routeLabel.textContent = 'No se pudo calcular ruta';
            routeKm.textContent = '—';
            routeMin.textContent = '—';
            if (routeLayer) { routeLayer.remove(); routeLayer = null; }
        }
    }

    function syncOriginFromSelect() {
        const opt = originSelect.options[originSelect.selectedIndex];
        const oLat = parseFloat(opt?.dataset?.lat || '');
        const oLng = parseFloat(opt?.dataset?.lng || '');
        if (!Number.isNaN(oLat) && !Number.isNaN(oLng)) {
            setOrigin(oLat, oLng, opt.textContent.trim());
        }
    }

    async function syncDestinationFromSelectsIfReady() {
        if (!hasChosenDestination()) return;

        const deptName = getSelectedText(departmentSelect);
        const munName = getSelectedText(municipalitySelect);
        const isVereda = (placeTypeSelect.value === 'vereda');
        const verName = isVereda ? getSelectedText(villageSelect) : '';

        const query = (isVereda && verName)
            ? `${verName}, ${munName}, ${deptName}, Colombia`
            : `${munName}, ${deptName}, Colombia`;

        const r = await geocode(query);
        if (r) setDestination(r.lat, r.lng, r.name, 'selects');
    }

    function loadMunicipalities(departmentId, selectedId) {
        municipalitySelect.innerHTML = '<option value="">— Selecciona —</option>';
        villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
        if (!departmentId) return;

        fetch(@json(route('gdf.instructor.catalog.municipalities')) + '?department_id=' + departmentId)
            .then(r => r.json())
            .then(rows => {
                rows.forEach(row => {
                    const opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = row.name;
                    if (selectedId && String(selectedId) === String(row.id)) opt.selected = true;
                    municipalitySelect.appendChild(opt);
                });

                if (selectedId) {
                    if (placeTypeSelect.value === 'vereda') loadVillages(selectedId, oldVillage);
                    else {
                        clearDestinationAndRoute();
                        syncDestinationFromSelectsIfReady();
                    }
                } else {
                    clearDestinationAndRoute();
                }
            });
    }

    function loadVillages(municipalityId, selectedId) {
        villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
        if (!municipalityId) return;

        fetch(@json(route('gdf.instructor.catalog.villages')) + '?municipality_id=' + municipalityId)
            .then(r => r.json())
            .then(rows => {
                rows.forEach(row => {
                    const opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = row.name;
                    if (selectedId && String(selectedId) === String(row.id)) opt.selected = true;
                    villageSelect.appendChild(opt);
                });

                if (selectedId) {
                    clearDestinationAndRoute();
                    syncDestinationFromSelectsIfReady();
                } else {
                    clearDestinationAndRoute();
                }
            });
    }

    // === NUEVO: consulta del valor sugerido desde rates ===
    async function fetchTransportRate() {
        // Requiere destino por selects (municipio/vereda). Si no hay, no consultamos tabla.
        if (!hasChosenDestination()) return null;

        const payload = new URLSearchParams({
            place_type: placeTypeSelect.value,
            transport_mode: transportSelect.value,
            department_id: departmentSelect.value || '',
            municipality_id: municipalitySelect.value || '',
            village_id: villageSelect.value || '',
        });

        const url = @json(route('gdf.instructor.catalog.transportRate')) + '?' + payload.toString();

        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return null;
            return await res.json(); // {amount: number, source?: string}
        } catch (e) {
            return null;
        }
    }

    // Solo auto-rellenar si el usuario no lo ha editado a mano
    let userTouchedEstimated = false;
    estimatedInput.addEventListener('input', () => {
        userTouchedEstimated = true;
    });

    async function refreshSuggestedRate() {
        rateHelp.textContent = '';
        const data = await fetchTransportRate();
        if (!data || typeof data.amount === 'undefined') return;

        const amount = Number(data.amount || 0);

        if (!userTouchedEstimated) {
            if (amount > 0) {
                estimatedInput.value = String(Math.round(amount));
                rateHelp.textContent = 'Sugerido automáticamente según tarifas del destino y transporte (solo ida).';
            } else {
                // Si no hay tarifa, no forzamos 0; dejamos al usuario decidir
                rateHelp.textContent = 'No hay tarifa registrada para este destino/transporte. Puedes digitar el valor.';
            }
        } else {
            if (amount > 0) {
                rateHelp.textContent = 'Tarifa sugerida disponible, pero no se sobreescribe porque ya editaste el valor.';
            }
        }
    }

    // === NUEVO: bloquear domingos en fechas (frontend) ===
    function isSunday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr + 'T00:00:00');
        return d.getDay() === 0;
    }

    function validateWeekdays() {
        let msg1 = '';
        let msg2 = '';

        if (isSunday(startDate.value)) msg1 = 'No se permite seleccionar domingo.';
        if (isSunday(endDate.value)) msg2 = 'No se permite seleccionar domingo.';

        dateHelp1.textContent = msg1;
        dateHelp2.textContent = msg2;

        // Si el usuario selecciona domingo, limpiamos el campo para forzarlo a corregir
        if (isSunday(startDate.value)) startDate.value = '';
        if (isSunday(endDate.value)) endDate.value = '';
    }

    function initMap() {
        map = L.map('gdfMap');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        syncOriginFromSelect();
        clearDestinationAndRoute();

        map.on('click', (e) => {
            // Solo permitir pin si aún NO hay destino por selección
            if (hasChosenDestination()) return;
            setDestination(e.latlng.lat, e.latlng.lng, 'Destino (mapa)', 'map');
        });
    }

    document.getElementById('gdfReqForm').addEventListener('submit', (e) => {
        if (!originLat.value || !originLng.value) {
            e.preventDefault();
            alert('No se pudo determinar el origen (coordenadas del select).');
            return;
        }
        if (!destLat.value || !destLng.value) {
            e.preventDefault();
            alert('Selecciona un destino (municipio/vereda o clic en mapa).');
            return;
        }

        // Validación domingo (frontend)
        if (isSunday(startDate.value) || isSunday(endDate.value)) {
            e.preventDefault();
            alert('No se permite seleccionar domingos. Ajusta las fechas.');
            return;
        }
    });

    // Eventos
    originSelect.addEventListener('change', () => { syncOriginFromSelect(); });

    transportSelect.addEventListener('change', () => {
        refreshSuggestedRate();
    });

    startDate.addEventListener('change', validateWeekdays);
    endDate.addEventListener('change', validateWeekdays);

    placeTypeSelect.addEventListener('change', () => {
        userTouchedEstimated = false;
        setPlaceUI();
        clearDestinationAndRoute();
        applyTransportRules();
        refreshSuggestedRate();
        syncDestinationFromSelectsIfReady();
    });

    departmentSelect.addEventListener('change', () => {
        userTouchedEstimated = false;
        applyTransportRules();
        loadMunicipalities(departmentSelect.value, null);
        refreshSuggestedRate();
    });

    municipalitySelect.addEventListener('change', () => {
        userTouchedEstimated = false;
        applyTransportRules();
        if (placeTypeSelect.value === 'vereda') loadVillages(municipalitySelect.value, null);
        else {
            clearDestinationAndRoute();
            syncDestinationFromSelectsIfReady();
            refreshSuggestedRate();
        }
    });

    villageSelect.addEventListener('change', () => {
        userTouchedEstimated = false;
        applyTransportRules();
        clearDestinationAndRoute();
        syncDestinationFromSelectsIfReady();
        refreshSuggestedRate();
    });

    // Init
    placeTypeSelect.value = oldPlaceType;
    setPlaceUI();

    loadMunicipalities(departmentSelect.value, oldMunicipality);

    setTimeout(() => {
        if (oldMunicipality) {
            if (placeTypeSelect.value === 'vereda' && oldVillage) {
                syncDestinationFromSelectsIfReady();
            } else if (placeTypeSelect.value !== 'vereda') {
                syncDestinationFromSelectsIfReady();
            }
        }
    }, 600);

    initMap();
    applyTransportRules();
    validateWeekdays();

    // Si hay destino restaurado por old() y transporte, intenta sugerir
    setTimeout(() => refreshSuggestedRate(), 800);
})();
</script>
@endsection
