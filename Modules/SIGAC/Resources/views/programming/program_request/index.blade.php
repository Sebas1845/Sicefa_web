<!-- vista::program_request.index (COMPLETA CORREGIDA) -->
@extends('sigac::layouts.master')
@section('title', 'SIGAC | Solicitud de Programa')

@section('content')
@php
    // Flags desde controller
    $canCreate   = $canCreate ?? true;
    $blockReason = $blockReason ?? null;

    // Cuando esté bloqueado, evita “default campesena”
    $areaKey = $areaKey ?? null;
    $areaLabel = $areaKey === 'campesena'
        ? 'CAMPESENA'
        : ($areaKey === 'academic' ? 'COORDINACIÓN ACADÉMICA' : 'SIN ÁREA');
@endphp

<div class="container py-4">

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-warning">
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Bloqueo UX --}}
    @if(!$canCreate)
        <div class="alert alert-danger">
            <strong>No puedes crear la solicitud.</strong><br>
            {{ $blockReason ?? 'No cuentas con permisos/condiciones para crear solicitudes.' }}
        </div>
    @endif

    {{-- Debug (solo en debug) --}}
    @if(config('app.debug'))
        <div class="alert alert-info">
            villages en vista: {{ isset($villageIds) ? $villageIds->count() : 'NO SET' }}
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Solicitud de Programa</h3>
        <span class="badge bg-dark" id="areaBadge">{{ $areaLabel }}</span>
    </div>

    <form method="POST"
          action="{{ route('sigac.' . getRoleRouteName(Route::currentRouteName()) . '.programming.program_request.store') }}"
          enctype="multipart/form-data">
        @csrf

        <fieldset @disabled(!$canCreate)>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label">Área de la solicitud</label>

                            @php
                                $hasManyAreas = isset($areaOptions) && count($areaOptions) > 1;
                            @endphp

                            <select name="area" id="areaSelect" class="form-select"
                                    {{ ($hasManyAreas && $canCreate) ? '' : 'disabled' }}>
                                @if(!$canCreate)
                                    <option value="">SIN ÁREA</option>
                                @elseif(!$hasManyAreas)
                                    <option value="{{ $areaKey }}">{{ $areaLabel }}</option>
                                @else
                                    @foreach ($areaOptions as $opt)
                                        <option value="{{ $opt['key'] }}" @selected($opt['key'] === $areaKey)>
                                            {{ $opt['key'] === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA' }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>

                            @if($canCreate && !$hasManyAreas && $areaKey)
                                <input type="hidden" name="area" value="{{ $areaKey }}">
                            @endif

                            @if($canCreate && isset($areaId) && $areaId)
                                <input type="hidden" name="area_id" value="{{ (int) $areaId }}">
                            @endif
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Programa</label>
                            <input id="programSearch" type="text" class="form-control mb-2"
                                   placeholder="Buscar programa..." @disabled(!$canCreate)>
                            <select id="program_id" name="program_id" class="form-select" required @disabled(!$canCreate)>
                                <option value="">— Seleccione —</option>
                                @foreach ($programs as $p)
                                    <option value="{{ $p->id }}" @selected(old('program_id') == $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Programa especial</label>

                            <input id="specialProgramSearch" type="text" class="form-control mb-2"
                                   placeholder="Buscar programa especial..." @disabled(!$canCreate)>

                            <select name="special_program_id" id="special_program_id"
                                    class="form-select @error('special_program_id') is-invalid @enderror"
                                    required @disabled(!$canCreate)>
                                <option value="">— Seleccione —</option>
                                @foreach ($specialPrograms as $sp)
                                    <option value="{{ $sp->id }}" @selected(old('special_program_id') == $sp->id)>
                                        {{ $sp->name }}
                                    </option>
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

                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Rubro ({{ $areaLabel }})</label>
                                    <select name="budget_item_id"
                                            class="form-select @error('budget_item_id') is-invalid @enderror"
                                            required @disabled(!$canCreate)>
                                        <option value="">— Seleccione —</option>
                                        @foreach ($rubros as $r)
                                            @php $txt = trim(($r->code ? ($r->code.' - ') : '').$r->name); @endphp
                                            <option value="{{ $r->id }}" @selected(old('budget_item_id') == $r->id)>
                                                {{ $txt }}
                                            </option>
                                        @endforeach
                                    </select>
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

                                <div class="col-md-12">
                                    <label class="form-label">Municipio</label>
                                    <input id="municipalitySearch" type="text" class="form-control mb-2"
                                           placeholder="Buscar municipio..." @disabled(!$canCreate)>
                                    <select id="municipality_id" name="municipality_id"
                                            class="form-select @error('municipality_id') is-invalid @enderror"
                                            required @disabled(!$canCreate)>
                                        <option value="">— Seleccione —</option>
                                        @foreach ($municipalities as $m)
                                            <option value="{{ $m->id }}" @selected(old('municipality_id') == $m->id)>
                                                {{ $m->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('municipality_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Destino es</label>
                                    <select class="form-select @error('place_type') is-invalid @enderror"
                                            name="place_type" id="place_type" required @disabled(!$canCreate)>
                                        <option value="municipio" @selected(old('place_type', 'municipio') === 'municipio')>Municipio</option>
                                        <option value="vereda" @selected(old('place_type') === 'vereda')>Vereda</option>
                                    </select>
                                    @error('place_type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

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

                                    <div class="form-text">Si no existe en lista, escribe el nombre para crearla (opcional).</div>
                                    <input name="company_name" class="form-control mt-1"
                                           value="{{ old('company_name') }}" placeholder="Nombre empresa"
                                           @disabled(!$canCreate)>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Dirección (opcional)</label>
                                    <input name="address" class="form-control" value="{{ old('address') }}" @disabled(!$canCreate)>
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

                <div class="col-lg-5">

                    <div class="card mb-3">
                        <div class="card-body">
                            <h6 class="mb-2">Documentos</h6>

                            <div class="mb-2">
                                <label class="form-label">Cédula (PDF)</label>
                                <input type="file" name="cedula_pdf" class="form-control" accept="application/pdf" @disabled(!$canCreate)>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Cargue Masivo (Excel aprendices)</label>
                                <input type="file" name="bulk_excel" class="form-control" accept=".xls,.xlsx" @disabled(!$canCreate)>
                            </div>

                            <div class="mb-2">
                                <label class="form-label">Carta (PDF)</label>
                                <input type="file" name="carta_pdf" class="form-control" accept="application/pdf" @disabled(!$canCreate)>
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
                                            <td><input type="date" name="dates[]" class="form-control" required @disabled(!$canCreate)></td>
                                            <td><input type="time" name="start_time[]" class="form-control" value="07:30" required @disabled(!$canCreate)></td>
                                            <td><input type="time" name="end_time[]" class="form-control" value="12:30" required @disabled(!$canCreate)></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-primary addRow" @disabled(!$canCreate)>+</button>
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
    const CAN_CREATE = @json((bool)($canCreate ?? true));

    // Área: recargar para traer rubros correctos
    const areaSelect = document.getElementById('areaSelect');
    if (CAN_CREATE && areaSelect && !areaSelect.disabled) {
        areaSelect.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('area', areaSelect.value);
            window.location.href = url.toString();
        });
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
    filterSelect('programSearch', 'program_id');
    filterSelect('municipalitySearch', 'municipality_id');
    filterSelect('companySearch', 'company_id');
    filterSelect('specialProgramSearch', 'special_program_id');
    filterSelect('villageSearch', 'village_id');

    // Vereda block
    const placeType = document.getElementById('place_type');
    const villageBlock = document.getElementById('villageBlock');
    const municipalityId = document.getElementById('municipality_id');
    const villageId = document.getElementById('village_id');

    function toggleVillage() {
        if (!villageBlock) return;
        const isVillage = placeType && placeType.value === 'vereda';
        villageBlock.style.display = isVillage ? 'block' : 'none';
        if (!isVillage && villageId) {
            villageId.innerHTML = '<option value="">— Seleccione —</option>';
            villageId.value = '';
        }
    }

    async function loadVillagesByMunicipality(mid) {
        if (!CAN_CREATE || !villageId) return;
        if (!mid) {
            villageId.innerHTML = '<option value="">— Seleccione —</option>';
            return;
        }

        const url = "{{ route('sigac.' . getRoleRouteName(Route::currentRouteName()) . '.programming.program_request.searchvillages') }}"
            + "?municipality_id=" + encodeURIComponent(mid);

        try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                villageId.innerHTML = '<option value="">— Seleccione —</option>';
                return;
            }

            const data = await res.json();
            let html = '<option value="">— Seleccione —</option>';
            data.forEach(v => html += `<option value="${v.id}">${v.text}</option>`);
            villageId.innerHTML = html;

            const oldVillage = @json(old('village_id'));
            if (oldVillage) villageId.value = String(oldVillage);

        } catch (err) {
            villageId.innerHTML = '<option value="">— Seleccione —</option>';
        }
    }

    if (CAN_CREATE && placeType) {
        placeType.addEventListener('change', () => {
            toggleVillage();
            if (placeType.value === 'vereda' && municipalityId && municipalityId.value) {
                loadVillagesByMunicipality(municipalityId.value);
            }
        });
    }

    if (CAN_CREATE && municipalityId) {
        municipalityId.addEventListener('change', () => {
            if (placeType && placeType.value === 'vereda') loadVillagesByMunicipality(municipalityId.value);
        });
    }

    toggleVillage();
    if (CAN_CREATE && @json(old('place_type', 'municipio')) === 'vereda' && municipalityId && municipalityId.value) {
        loadVillagesByMunicipality(municipalityId.value);
    }

    // Horas contador
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
            if (e.target.matches('input[name="start_time[]"]') || e.target.matches('input[name="end_time[]"]')) {
                recalcHours();
            }
        });
    }

    if (CAN_CREATE && hoursInput) {
        hoursInput.addEventListener('input', recalcHours);
    }
    recalcHours();

})();
</script>
@endsection
