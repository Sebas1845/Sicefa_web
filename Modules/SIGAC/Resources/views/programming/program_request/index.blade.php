{{-- sigac::programming.program_request.index --}}
@extends('sigac::layouts.master')
@section('title', 'SIGAC | Solicitud de Programa')
<script src="{{ asset('libs/Bootstrap-5.3.0-alpha/js/bootstrap.bundle.min.js') }}"></script>

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-warning">
            <ul class="mb-0">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        use Illuminate\Support\Str;

        $canCreate = $canCreate ?? true;
        $blockReason = $blockReason ?? null;

        // --- Área resuelta por controller ---
        $resolvedAreaKey = $areaKey ?? null;

        // --- No dejes que old('area') vacío pise el área ---
        $oldArea = old('area');
        $areaKey = $errors->any() && $oldArea !== null && $oldArea !== '' ? $oldArea : $resolvedAreaKey;

        // Normalizado para comparar
        $areaKeyN = trim(mb_strtolower((string) ($areaKey ?? '')));

        $areaLabel =
            $areaKeyN === 'campesena'
                ? 'CAMPESENA'
                : ($areaKeyN === 'academic'
                    ? 'COORDINACIÓN ACADÉMICA'
                    : 'SIN ÁREA');

        $rubroLabel =
            $areaKeyN === 'campesena'
                ? 'Rubro CAMPESENA'
                : ($areaKeyN === 'academic'
                    ? 'Rubro COORDINACIÓN ACADÉMICA'
                    : 'Rubro');

        $hasManyAreas = isset($areaOptions) && count($areaOptions) > 1;

        // Si hay varias áreas y no hay selección, bloquea rubro
        $lockRubro = $hasManyAreas && empty($areaKeyN);

        // ✅ IMPORTANTE: preservar valores al recargar (por cambio de área)
        $placeTypeVal = old('place_type', request('place_type', 'municipio'));
        $placeTypeVal = in_array($placeTypeVal, ['municipio', 'vereda'], true) ? $placeTypeVal : 'municipio';

        $municipalityVal = old('municipality_id', request('municipality_id', ''));
        $villageVal = old('village_id', request('village_id', ''));
    @endphp

    <div class="container py-4">
        @if (!$canCreate)
            <div class="alert alert-danger">
                <strong>No puedes crear la solicitud.</strong><br>
                {{ $blockReason ?? 'No cuentas con permisos/condiciones para crear solicitudes.' }}
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Solicitud de Programa</h3>
            <span class="badge bg-dark" id="areaBadge">{{ $areaLabel }}</span>
        </div>

        <form id="programRequestForm" method="POST"
            action="{{ route('sigac.' . getRoleRouteName(Route::currentRouteName()) . '.programming.program_request.store') }}"
            enctype="multipart/form-data">
            @csrf

            <fieldset @disabled(!$canCreate)>

                {{-- CABECERA --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="row g-3">

                            {{-- ÁREA --}}
                            <div class="col-md-4">
                                <label class="form-label">Área de la solicitud</label>

                                <select name="area" id="areaSelect" class="form-select"
                                    {{ $hasManyAreas && $canCreate ? '' : 'disabled' }}>
                                    @if (!$canCreate)
                                        <option value="">SIN ÁREA</option>
                                    @elseif(!$hasManyAreas)
                                        {{-- Solo 1 área --}}
                                        <option value="{{ $areaKeyN }}">{{ $areaLabel }}</option>
                                    @else
                                        {{-- Varias áreas --}}
                                        <option value="">— Seleccione —</option>
                                        @foreach ($areaOptions as $opt)
                                            @php
                                                $kRaw = $opt['key'] ?? '';
                                                $kN = trim(mb_strtolower((string) $kRaw));
                                                $lbl = $kN === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA';
                                            @endphp
                                            <option value="{{ $kN }}" @selected($areaKeyN === $kN)>
                                                {{ $lbl }}</option>
                                        @endforeach
                                    @endif
                                </select>

                                {{-- Si solo hay una área, fuerza el post con hidden (porque el select está disabled) --}}
                                @if ($canCreate && !$hasManyAreas && $areaKeyN)
                                    <input type="hidden" name="area" value="{{ $areaKeyN }}">
                                @endif

                                {{-- Si tu controller define area_id, lo mandas --}}
                                @if ($canCreate && isset($areaId) && $areaId)
                                    <input type="hidden" name="area_id" value="{{ (int) $areaId }}">
                                @endif
                            </div>

                            {{-- PROGRAMA --}}
                            <div class="col-md-8">
                                <label class="form-label">Programa</label>
                                <input id="programSearch" type="text" class="form-control mb-2"
                                    placeholder="Buscar programa..." @disabled(!$canCreate)>

                                <select id="program_id" name="program_id" class="form-select" required
                                    @disabled(!$canCreate)>
                                    <option value="">— Seleccione —</option>
                                    @foreach ($programs as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- PROGRAMA ESPECIAL --}}
                            <div class="col-md-12">
                                <label class="form-label">Programa especial</label>

                                <input id="specialProgramSearch" type="text" class="form-control mb-2"
                                    placeholder="Buscar programa especial..." @disabled(!$canCreate)>

                                <select name="special_program_id" id="special_program_id"
                                    class="form-select @error('special_program_id') is-invalid @enderror" required
                                    @disabled(!$canCreate)>
                                    <option value="">— Seleccione —</option>
                                    @foreach ($specialPrograms as $sp)
                                        <option value="{{ $sp->id }}">{{ $sp->name }}</option>
                                    @endforeach
                                </select>

                                @error('special_program_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-7">

                        {{-- FORM PRINCIPAL --}}
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row g-3">

                                    {{-- RUBRO --}}
                                    <div class="col-md-6">
                                        <label class="form-label" id="rubroLabel">{{ $rubroLabel }}</label>

                                        <select id="budget_item_id" name="budget_item_id"
                                            class="form-select @error('budget_item_id') is-invalid @enderror" required
                                            {{ !$canCreate || $lockRubro ? 'disabled' : '' }}>
                                            <option value="">
                                                {{ $lockRubro ? 'Selecciona un área primero' : '— Seleccione —' }}</option>

                                            @if (!$lockRubro)
                                                @foreach ($rubros as $r)
                                                    @php $txt = trim(($r->code ? ($r->code.' - ') : '').$r->name); @endphp
                                                    <option value="{{ $r->id }}">
                                                        {{ $txt }}</option>
                                                @endforeach
                                            @endif
                                        </select>

                                        @if (!$canCreate || $lockRubro)
                                            <input type="hidden" name="budget_item_id"
                                                value="{{ old('budget_item_id') }}">
                                        @endif


                                        @error('budget_item_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Horas</label>
                                        <input name="hours" id="hours" type="number" min="1" step="1"
                                            class="form-control @error('hours') is-invalid @enderror"
                                            value="{{ old('hours') }}" required @disabled(!$canCreate)>
                                        @error('hours')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label">Cupo</label>
                                        <input name="quotas" id="quotas" type="number" min="1"
                                            class="form-control @error('quotas') is-invalid @enderror"
                                            value="{{ old('quotas') }}" required @disabled(!$canCreate)>
                                        @error('quotas')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Fecha de inicio</label>
                                        <input name="start_date" id="start_date" type="date"
                                            class="form-control @error('start_date') is-invalid @enderror"
                                            value="{{ old('start_date') }}" required @disabled(!$canCreate)>
                                        @error('start_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Fecha final (opcional)</label>
                                        <input name="end_date" id="end_date" type="date"
                                            class="form-control @error('end_date') is-invalid @enderror"
                                            value="{{ old('end_date') }}" @disabled(!$canCreate)>
                                        @error('end_date')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- MUNICIPIO --}}
                                    <div class="col-md-12">
                                        <label class="form-label">Municipio</label>
                                        <input id="municipalitySearch" type="text" class="form-control mb-2"
                                            placeholder="Buscar municipio..." @disabled(!$canCreate)>

                                        <select id="municipality_id" name="municipality_id"
                                            class="form-select @error('municipality_id') is-invalid @enderror" required
                                            @disabled(!$canCreate)>
                                            <option value="">— Seleccione —</option>
                                            @foreach ($municipalities as $m)
                                                <option value="{{ $m->id }}">
                                                    {{ $m->name }}
                                                </option>
                                            @endforeach
                                        </select>

                                        @error('municipality_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- DESTINO --}}
                                    <div class="col-md-12">
                                        <label class="form-label">Destino es</label>
                                        <select class="form-select @error('place_type') is-invalid @enderror"
                                            name="place_type" id="place_type" required @disabled(!$canCreate)>
                                            <option value="municipio" @selected($placeTypeVal === 'municipio')>Municipio</option>
                                            <option value="vereda" @selected($placeTypeVal === 'vereda')>Vereda</option>
                                        </select>
                                        @error('place_type')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- VEREDA --}}
                                    <div class="col-md-12" id="villageBlock" style="display:none;">
                                        <label class="form-label">Vereda</label>

                                        <input id="villageSearch" type="text" class="form-control mb-2"
                                            placeholder="Buscar vereda..." @disabled(!$canCreate)>

                                        <select id="village_id" name="village_id"
                                            class="form-select @error('village_id') is-invalid @enderror"
                                            @disabled(!$canCreate)>
                                            <option value="">— Seleccione —</option>
                                        </select>

                                        @error('village_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        <small class="text-muted">Se carga según el municipio seleccionado.</small>
                                    </div>

                                    {{-- EMPRESA --}}
                                    <div class="col-md-12">
                                        <label class="form-label">Empresa / Institución</label>
                                        <input id="companySearch" type="text" class="form-control mb-2"
                                            placeholder="Buscar empresa..." @disabled(!$canCreate)>

                                        <select name="company_id" id="company_id"
                                            class="form-select @error('company_id') is-invalid @enderror"
                                            @disabled(!$canCreate)>
                                            <option value="">— Seleccione (opcional) —</option>
                                            @foreach ($companySuggestions as $c)
                                                <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>
                                                    {{ $c->name }}{{ $c->nit ? ' — NIT: ' . $c->nit : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('company_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        <div class="form-text">Si no existe en lista, escribe el nombre para crearla
                                            (opcional).</div>
                                        <input name="company_name" class="form-control mt-1"
                                            value="{{ old('company_name') }}" placeholder="Nombre empresa"
                                            @disabled(!$canCreate)>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Dirección (opcional)</label>
                                        <input name="address" class="form-control" value="{{ old('address') }}"
                                            @disabled(!$canCreate)>
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Observación (opcional)</label>
                                        <textarea name="observation" class="form-control" rows="3" @disabled(!$canCreate)>{{ old('observation') }}</textarea>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="border rounded p-3">
                                            <h6 class="mb-2">Datos del solicitante (opcional)</h6>

                                            <label class="form-label">Nombre completo</label>
                                            <input name="applicant" class="form-control" list="applicantHistory"
                                                value="{{ old('applicant') }}" placeholder="Nombre del contacto"
                                                @disabled(!$canCreate)>
                                            <datalist id="applicantHistory">
                                                @foreach ($applicantSuggestions as $a)
                                                    <option value="{{ $a }}"></option>
                                                @endforeach
                                            </datalist>

                                            <div class="row g-3 mt-1">
                                                <div class="col-md-6">
                                                    <label class="form-label">Correo</label>
                                                    <input name="email" type="email" class="form-control"
                                                        value="{{ old('email') }}" @disabled(!$canCreate)>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Teléfono</label>
                                                    <input name="telephone" class="form-control"
                                                        value="{{ old('telephone') }}" @disabled(!$canCreate)>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                    </div>

                    {{-- DERECHA --}}
                    <div class="col-lg-5">

                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="mb-2">Documentos</h6>

                                <div class="mb-2">
                                    <label class="form-label">Cédula (PDF)</label>
                                    <input type="file" name="documents[]" class="form-control"
                                        accept="application/pdf" @disabled(!$canCreate)>
                                    <input type="hidden" name="document_types[]" value="cedula">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Cargue Masivo (Excel aprendices)</label>
                                    <input type="file" name="documents[]" class="form-control" accept=".xls,.xlsx"
                                        @disabled(!$canCreate)>
                                    <input type="hidden" name="document_types[]" value="cargue_masivo">
                                </div>

                                <div class="mb-2">
                                    <label class="form-label">Carta (PDF)</label>
                                    <input type="file" name="documents[]" class="form-control"
                                        accept="application/pdf" @disabled(!$canCreate)>
                                    <input type="hidden" name="document_types[]" value="carta">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="mb-2">Horario</h6>

                                <div class="alert alert-secondary py-2 mb-2">
                                    <div><strong>Horas programadas:</strong> <span id="hoursPlanned">0</span> h</div>
                                    <div><strong>Total solicitado:</strong> <span id="hoursTotal">0</span> h</div>
                                    <div><strong>Faltan:</strong> <span id="hoursMissing">0</span> h</div>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-sm align-middle" id="scheduleTable">
                                        <thead>
                                            <tr>
                                                <th style="width:40%;">Fecha</th>
                                                <th style="width:25%;">Hora inicio</th>
                                                <th style="width:25%;">Hora fin</th>
                                                <th style="width:10%;"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><input type="date" name="dates[]" class="form-control" required
                                                        @disabled(!$canCreate)></td>
                                                <td><input type="time" name="start_time[]" class="form-control"
                                                        value="07:30" required @disabled(!$canCreate)></td>
                                                <td><input type="time" name="end_time[]" class="form-control"
                                                        value="12:30" required @disabled(!$canCreate)></td>
                                                <td class="text-end">
                                                    <button type="button" class="btn btn-sm btn-primary addRow"
                                                        @disabled(!$canCreate)>+</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                            </div>
                        </div>

                    </div>
                </div>
            </fieldset>

            <div class="mt-3 d-flex gap-2">
                <button class="btn btn-success" @disabled(!$canCreate)>Guardar</button>
                <a href="{{ route('sigac.' . getRoleRouteName(Route::currentRouteName()) . '.programming.program_request.table') }}"
                    class="btn btn-secondary">Volver</a>
            </div>
        </form>
    </div>

    <script>
        (function() {
            const CAN_CREATE = @json((bool) ($canCreate ?? true));

            const form = document.getElementById('programRequestForm');

            const areaSelect = document.getElementById('areaSelect');
            const areaBadge = document.getElementById('areaBadge');

            const placeType = document.getElementById('place_type');
            const villageBlock = document.getElementById('villageBlock');
            const municipalityId = document.getElementById('municipality_id');
            const villageId = document.getElementById('village_id');

            function paintBadge() {
                if (!areaBadge || !areaSelect) return;
                if (areaSelect.value === 'campesena') areaBadge.textContent = 'CAMPESENA';
                else if (areaSelect.value === 'academic') areaBadge.textContent = 'COORDINACIÓN ACADÉMICA';
                else areaBadge.textContent = 'SIN ÁREA';
            }

            // ✅ fuerza área desde URL si por alguna razón el select “queda vacío”
            function syncAreaFromUrl() {
                if (!areaSelect) return;
                const url = new URL(window.location.href);
                const a = (url.searchParams.get('area') || '').trim().toLowerCase();
                if (!a) return;
                const ok = Array.from(areaSelect.options).some(o => (o.value || '').toLowerCase() === a);
                if (ok) areaSelect.value = a;
            }

            function filterSelect(searchInputId, selectId) {
                if (!CAN_CREATE) return;
                const s = document.getElementById(searchInputId);
                const sel = document.getElementById(selectId);
                if (!s || !sel) return;

                s.addEventListener('input', () => {
                    const term = (s.value || '').toLowerCase().trim();
                    Array.from(sel.options).forEach((o, idx) => {
                        if (idx === 0) return;
                        o.hidden = term && !o.text.toLowerCase().includes(term);
                    });
                });
            }

            async function loadVillagesByMunicipality(mid, preselectVillageId = null) {
                if (!CAN_CREATE || !villageId) return;

                if (!mid) {
                    villageId.innerHTML = '<option value="">— Seleccione —</option>';
                    return;
                }

                const url =
                    "{{ route('sigac.' . getRoleRouteName(Route::currentRouteName()) . '.programming.program_request.searchvillages') }}" +
                    "?municipality_id=" + encodeURIComponent(mid);

                try {
                    const res = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    const ct = res.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) {
                        villageId.innerHTML = '<option value="">— Seleccione —</option>';
                        return;
                    }

                    const data = await res.json();
                    let html = '<option value="">— Seleccione —</option>';
                    data.forEach(v => html += `<option value="${v.id}">${v.text}</option>`);
                    villageId.innerHTML = html;

                    if (preselectVillageId) villageId.value = String(preselectVillageId);

                } catch (e) {
                    villageId.innerHTML = '<option value="">— Seleccione —</option>';
                }
            }

            function toggleVillage() {
                if (!villageBlock || !placeType) return;

                const isVillage = (placeType.value === 'vereda');
                villageBlock.style.display = isVillage ? 'block' : 'none';

                if (villageId) {
                    villageId.required = isVillage; // ✅ clave
                    if (!isVillage) {
                        villageId.innerHTML = '<option value="">— Seleccione —</option>';
                        villageId.value = '';
                    } else {
                        // si ya hay municipio, cargar
                        if (municipalityId && municipalityId.value) {
                            const oldVillage = @json($villageVal);
                            loadVillagesByMunicipality(municipalityId.value, oldVillage || null);
                        }
                    }
                }
            }

            // ✅ Inicialización
            syncAreaFromUrl();
            paintBadge();
            toggleVillage();

            // Área: recargar para traer rubros correctos
            if (CAN_CREATE && areaSelect && !areaSelect.disabled) {
                areaSelect.addEventListener('change', () => {
                    paintBadge();

                    const url = new URL(window.location.href);
                    url.searchParams.delete('page');
                    url.searchParams.delete('state');

                    // ✅ preservar estado crítico para que NO vuelva a municipio
                    url.searchParams.set('area', areaSelect.value || '');
                    if (placeType) url.searchParams.set('place_type', placeType.value || 'municipio');
                    if (municipalityId) url.searchParams.set('municipality_id', municipalityId.value || '');
                    if (villageId) url.searchParams.set('village_id', villageId.value || '');

                    window.location.href = url.toString();
                });
            }

            // filtros
            filterSelect('programSearch', 'program_id');
            filterSelect('municipalitySearch', 'municipality_id');
            filterSelect('companySearch', 'company_id');
            filterSelect('specialProgramSearch', 'special_program_id');
            filterSelect('villageSearch', 'village_id');

            // cambios destino
            if (CAN_CREATE && placeType) {
                placeType.addEventListener('change', () => {
                    toggleVillage();
                    if (placeType.value === 'vereda' && municipalityId && municipalityId.value) {
                        loadVillagesByMunicipality(municipalityId.value, null);
                    }
                });
            }

            if (CAN_CREATE && municipalityId) {
                municipalityId.addEventListener('change', () => {
                    if (placeType && placeType.value === 'vereda') {
                        loadVillagesByMunicipality(municipalityId.value, null);
                    }
                });
            }

            // ✅ bloqueo extra: evita que se mande municipio si el usuario eligió vereda
            if (CAN_CREATE && form) {
                form.addEventListener('submit', (e) => {
                    if (!placeType) return;

                    if (placeType.value === 'vereda') {
                        if (!municipalityId || !municipalityId.value) {
                            e.preventDefault();
                            alert('Debes seleccionar un municipio para cargar las veredas.');
                            municipalityId?.focus();
                            return;
                        }
                        if (!villageId || !villageId.value) {
                            e.preventDefault();
                            alert('Si el destino es vereda, debes escoger una vereda.');
                            villageId?.focus();
                            return;
                        }
                    }
                });
            }

            // Horas contador (tu lógica intacta)
            const tableBody = document.querySelector('#scheduleTable tbody');
            const hoursInput = document.getElementById('hours');
            const hoursPlannedEl = document.getElementById('hoursPlanned');
            const hoursTotalEl = document.getElementById('hoursTotal');
            const hoursMissingEl = document.getElementById('hoursMissing');

            function minutesBetween(hhmmA, hhmmB) {
                if (!hhmmA || !hhmmB) return 0;
                const [ah, am] = hhmmA.split(':').map(Number);
                const [bh, bm] = hhmmB.split(':').map(Number);
                if ([ah, am, bh, bm].some(n => Number.isNaN(n))) return 0;
                return (bh * 60 + bm) - (ah * 60 + am);
            }

            function recalcHours() {
                if (!hoursPlannedEl || !hoursTotalEl || !hoursMissingEl || !tableBody) return;

                let minutes = 0;
                Array.from(tableBody.querySelectorAll('tr')).forEach(tr => {
                    const st = tr.querySelector('input[name="start_time[]"]')?.value;
                    const et = tr.querySelector('input[name="end_time[]"]')?.value;
                    const diff = minutesBetween(st, et);
                    if (diff > 0) minutes += diff;
                });

                const planned = Math.round((minutes / 60) * 10) / 10;
                const total = parseFloat(hoursInput?.value || '0') || 0;
                const missing = Math.round((Math.max(total - planned, 0)) * 10) / 10;

                hoursPlannedEl.textContent = planned.toString();
                hoursTotalEl.textContent = total.toString();
                hoursMissingEl.textContent = missing.toString();
            }

            if (CAN_CREATE && tableBody) {
                tableBody.addEventListener('click', (e) => {
                    if (e.target.classList.contains('addRow')) {
                        const tr = e.target.closest('tr');
                        const clone = tr.cloneNode(true);

                        const date = clone.querySelector('input[type="date"]');
                        if (date) date.value = '';

                        const btn = clone.querySelector('button');
                        if (btn) {
                            btn.textContent = '-';
                            btn.classList.remove('btn-primary', 'addRow');
                            btn.classList.add('btn-danger', 'delRow');
                        }

                        tableBody.appendChild(clone);
                        recalcHours();
                    }

                    if (e.target.classList.contains('delRow')) {
                        e.target.closest('tr').remove();
                        recalcHours();
                    }
                });

                tableBody.addEventListener('input', (e) => {
                    if (e.target.matches('input[name="start_time[]"]') || e.target.matches(
                            'input[name="end_time[]"]')) {
                        recalcHours();
                    }
                });
            }

            if (CAN_CREATE && hoursInput) hoursInput.addEventListener('input', recalcHours);
            recalcHours();
        })();
    </script>
@endsection
