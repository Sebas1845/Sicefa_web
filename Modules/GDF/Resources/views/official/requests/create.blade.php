{{-- Modules/GDF/Resources/views/official/requests/create.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Nueva solicitud')

@section('content')
    @php
        $areaKey = $areaKey ?? ($ctx['area'] ?? 'academic');
        $areaLabel = $areaKey === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA';

        $countryId = $countryId ?? (int) config('gdf.country_id', 25);
        $hasMoto = (bool) ($hasMoto ?? ($motoLock['has_moto'] ?? false));
        $HUILA_ID = (int) ($HUILA_ID ?? 421);

        $vanAllowed = false;
        $isPlant = (bool) ($isPlant ?? false);
    @endphp

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        #gdfMap {
            height: 380px;
            border-radius: 12px
        }

        .mapbox {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 12px;
            padding: 12px
        }

        .map-hint {
            font-size: 12px;
            color: #6c757d;
            margin-top: 6px
        }

        .pill {
            display: inline-block;
            padding: .25rem .5rem;
            border-radius: 999px;
            border: 1px solid rgba(0, 0, 0, .1);
            background: #f8f9fa;
            font-size: .85rem
        }

        .softbox {
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 12px;
            padding: 12px;
            background: #fff
        }

        .muted {
            color: #6c757d
        }
    </style>

    <div class="container py-4">
        <h2 class="mb-1">Nueva solicitud de desplazamiento GDF</h2>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($budgetRule['message'] ?? null)
            <div class="alert alert-info">{{ $budgetRule['message'] }}</div>
        @endif

        <form method="POST" action="{{ route('gdf.instructor.requests.store') }}" id="gdfReqForm"
            enctype="multipart/form-data">
            @csrf

            <input type="hidden" name="area_id" value="{{ (int) $defaultAreaId }}">
            <input type="hidden" name="country_id" value="{{ (int) $countryId }}">
            <input type="hidden" name="origin_lat" id="origin_lat" value="">
            <input type="hidden" name="origin_lng" id="origin_lng" value="">
            <input type="hidden" name="destination_lat" id="destination_lat" value="">
            <input type="hidden" name="destination_lng" id="destination_lng" value="">

            {{-- transport_mode real --}}
            <input type="hidden" name="transport_mode" id="transport_mode_hidden"
                value="{{ old('transport_mode', 'bus') }}">

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
                                @if (($budgetRule['required'] ?? false) === true)
                                    <span class="text-danger">*</span>
                                @else
                                    <span class="text-muted">(opcional)</span>
                                @endif
                            </label>

                            <select class="form-select @error('budget_item_id') is-invalid @enderror" name="budget_item_id"
                                id="budget_item_id" @if (($budgetRule['required'] ?? false) === true) required @endif>
                                <option value="">— Selecciona —</option>

                                {{-- ✅ Fallback server-side: si ya vienen en $budgetItems, se pintan aquí --}}
                                @foreach ($budgetItems ?? [] as $it)
                                    @php
                                        // $it puede venir como objeto de DB::table() (stdClass)
                                        $id = is_array($it) ? $it['id'] ?? null : $it->id ?? null;
                                        $code = is_array($it) ? $it['code'] ?? '' : $it->code ?? '';
                                        $name = is_array($it) ? $it['name'] ?? '' : $it->name ?? '';
                                    @endphp

                                    @if ($id)
                                        <option value="{{ $id }}">
                                            {{ ($code ? $code . ' - ' : '') . $name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>

                            @error('budget_item_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <div class="form-text">
                                Se cargan los rubros asignados a tu usuario o, en académica, los del área si no tienes
                                asignación.
                            </div>
                        </div>


                        <div class="col-md-4">
                            <label class="form-label">Tipo</label>
                            <select class="form-select" disabled>
                                <option selected>Desplazamiento</option>
                            </select>
                        </div>

                        {{-- ORIGEN --}}
                        <div class="col-md-6">
                            <label class="form-label">Origen (Centro de formación)</label>
                            <select class="form-select @error('origin') is-invalid @enderror" name="origin" id="origin"
                                required>
                                @foreach ($origins as $o)
                                    <option value="{{ $o['key'] }}" data-lat="{{ $o['lat'] }}"
                                        data-lng="{{ $o['lng'] }}"
                                        {{ old('origin', 'center_default') === $o['key'] ? 'selected' : '' }}>
                                        {{ $o['label'] }}
                                    </option>
                                @endforeach
                            </select>
                            @error('origin')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">El mapa usa estas coordenadas como origen.</div>
                        </div>

                        {{-- TRANSPORTE (visual) --}}
                        <div class="col-md-6">
                            <label class="form-label">Medio de transporte</label>
                            <select class="form-select @error('transport_mode') is-invalid @enderror" id="transport_mode"
                                required>
                                <option value="bus" {{ old('transport_mode', 'bus') === 'bus' ? 'selected' : '' }}>Bus
                                </option>
                                <option value="van" {{ old('transport_mode') === 'van' ? 'selected' : '' }}>Camioneta
                                </option>
                                <option value="motorcycle" {{ old('transport_mode') === 'motorcycle' ? 'selected' : '' }}>
                                    Moto</option>
                                <option value="air" {{ old('transport_mode') === 'air' ? 'selected' : '' }}>Aéreo
                                </option>
                            </select>

                            @error('transport_mode')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text" id="transportHelp"></div>
                        </div>

                        {{-- DESTINO --}}
                        <div class="col-md-3">
                            <label class="form-label">Departamento destino</label>
                            <select class="form-select @error('department_id') is-invalid @enderror" name="department_id"
                                id="department_id" required>
                                <option value="">— Selecciona —</option>
                                @foreach ($departments as $d)
                                    @php
                                        $selectedDept = old('department_id')
                                            ? (int) old('department_id')
                                            : (int) $defaultDepartmentId;
                                    @endphp
                                    <option value="{{ $d->id }}"
                                        {{ $selectedDept === (int) $d->id ? 'selected' : '' }}>
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
                            <select class="form-select @error('place_type') is-invalid @enderror" name="place_type"
                                id="place_type" required>
                                <option value="municipio"
                                    {{ old('place_type', 'municipio') === 'municipio' ? 'selected' : '' }}>Municipio
                                </option>
                                <option value="vereda" {{ old('place_type') === 'vereda' ? 'selected' : '' }}>Vereda
                                </option>
                            </select>
                            @error('place_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text small text-muted" id="placeTypeHelp"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Municipio</label>
                            <select class="form-select @error('municipality_id') is-invalid @enderror"
                                name="municipality_id" id="municipality_id" required>
                                <option value="">— Selecciona —</option>
                            </select>
                            @error('municipality_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6" id="veredaWrap" style="display:none;">
                            <label class="form-label">Vereda</label>
                            <select class="form-select @error('village_id') is-invalid @enderror" name="village_id"
                                id="village_id">
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
                                    Primero elige Municipio/Vereda para destino. Si aún no, puedes poner destino con
                                    clic/arrastre del pin.
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

                        {{-- TRAYECTO --}}
                        <div class="col-md-3">
                            <label class="form-label">Trayecto</label>
                            <select class="form-select @error('trip_type') is-invalid @enderror" name="trip_type"
                                id="trip_type" required>
                                <option value="oneway" {{ old('trip_type', 'oneway') === 'oneway' ? 'selected' : '' }}>Ida
                                </option>
                                <option value="return" {{ old('trip_type') === 'return' ? 'selected' : '' }}>Vuelta
                                </option>
                                <option value="roundtrip" {{ old('trip_type') === 'roundtrip' ? 'selected' : '' }}>Ida y
                                    vuelta</option>
                            </select>
                            @error('trip_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted small" id="tripHelp"></div>
                        </div>

                        {{-- Transporte money fields: se ocultan cuando es moto --}}
                        <div class="col-md-3" id="transportBaseWrap">
                            <label class="form-label">Valor por trayecto (base)</label>
                            <input type="number" class="form-control @error('transport_value') is-invalid @enderror"
                                name="transport_value" id="transport_value" value="{{ old('transport_value') }}"
                                min="0" step="1">
                            @error('transport_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted small" id="rateHelp"></div>
                        </div>

                        <div class="col-md-3" id="transportTotalWrap">
                            <label class="form-label">Total transporte</label>
                            <input type="number" class="form-control" id="transport_total" value="" readonly>
                            <div class="form-text text-muted small" id="transportTotalHelp"></div>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Estimado</label>
                            <input type="number" class="form-control @error('estimated_value') is-invalid @enderror"
                                name="estimated_value" id="estimated_value" value="{{ old('estimated_value') }}"
                                readonly>
                            @error('estimated_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text text-muted small">
                                Si es <b>Moto</b>, el estimado será <b>viáticos</b>. Si no, será <b>transporte</b>.
                            </div>
                        </div>

                        {{-- VIÁTICOS (PLANTA o MOTO) --}}
                        <div class="col-12" id="allowancesWrap" style="display:none;">
                            <div class="softbox">
                                {{-- ✅ Header con botones "Crear viático" (según contexto) --}}
                                <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                    <div class="fw-semibold">Viáticos</div>

                                    <div class="d-flex gap-2 align-items-center flex-wrap">
                                        {{-- Moto --}}
                                        <button type="button" class="btn btn-sm btn-outline-primary"
                                            id="btnCreateGasolina" style="display:none;">
                                            Crear viático (Gasolina)
                                        </button>

                                        {{-- Planta --}}
                                        <button type="button" class="btn btn-sm btn-outline-primary plant-only"
                                            id="btnCreateHospedaje" style="display:none;">
                                            Crear viático (Hospedaje)
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary plant-only"
                                            id="btnCreateAlimentacion" style="display:none;">
                                            Crear viático (Alimentación)
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-primary plant-only"
                                            id="btnCreateOtros" style="display:none;">
                                            Crear viático (Otros)
                                        </button>

                                        <div class="text-muted small">Se guardan (si existe tabla) como <code>draft</code>.
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-2 mt-2">

                                    {{-- GASOLINA (MOTO) --}}
                                    <div class="col-12 moto-only">
                                        <div class="border rounded p-2">
                                            <div class="form-check">
                                                <input class="form-check-input allowance-check" type="checkbox"
                                                    name="allowances[fuel][include]" id="al_fuel" value="1"
                                                    {{ old('allowances.fuel.include') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="al_fuel"><b>Gasolina
                                                        (moto)</b></label>
                                            </div>

                                            <div class="row g-2 mt-2 allowance-fields" data-type="fuel"
                                                style="display:none;">
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Valor unitario (por día)</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[fuel][unit_amount]" id="fuel_unit"
                                                        value="{{ old('allowances.fuel.unit_amount') }}" min="0"
                                                        step="1">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-1">Unidades (días)</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[fuel][units]" id="fuel_units"
                                                        value="{{ old('allowances.fuel.units', 1) }}" min="1"
                                                        step="1" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label mb-1">Descripción</label>
                                                    <input type="text" class="form-control"
                                                        name="allowances[fuel][description]" id="fuel_desc"
                                                        value="{{ old('allowances.fuel.description', 'Gasolina (moto)') }}"
                                                        maxlength="255">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Total</label>
                                                    <input type="number" class="form-control" id="fuel_total"
                                                        value="" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- PLANTA: HOSPEDAJE --}}
                                    <div class="col-12 plant-only">
                                        <div class="border rounded p-2">
                                            <div class="form-check">
                                                <input class="form-check-input allowance-check" type="checkbox"
                                                    name="allowances[lodging][include]" id="al_lodging" value="1"
                                                    {{ old('allowances.lodging.include') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="al_lodging"><b>Hospedaje</b></label>
                                            </div>

                                            <div class="row g-2 mt-2 allowance-fields" data-type="lodging"
                                                style="display:none;">
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Valor unitario</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[lodging][unit_amount]" id="lodging_unit"
                                                        value="{{ old('allowances.lodging.unit_amount') }}"
                                                        min="0" step="1">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-1">Unidades (noches)</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[lodging][units]" id="lodging_units"
                                                        value="{{ old('allowances.lodging.units', 1) }}" min="1"
                                                        step="1" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label mb-1">Descripción</label>
                                                    <input type="text" class="form-control"
                                                        name="allowances[lodging][description]" id="lodging_desc"
                                                        value="{{ old('allowances.lodging.description', 'Hospedaje') }}"
                                                        maxlength="255">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Total</label>
                                                    <input type="number" class="form-control" id="lodging_total"
                                                        value="" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- PLANTA: ALIMENTACIÓN --}}
                                    <div class="col-12 plant-only">
                                        <div class="border rounded p-2">
                                            <div class="form-check">
                                                <input class="form-check-input allowance-check" type="checkbox"
                                                    name="allowances[meals][include]" id="al_meals" value="1"
                                                    {{ old('allowances.meals.include') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="al_meals"><b>Alimentación</b></label>
                                            </div>

                                            <div class="row g-2 mt-2 allowance-fields" data-type="meals"
                                                style="display:none;">
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Valor unitario</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[meals][unit_amount]" id="meals_unit"
                                                        value="{{ old('allowances.meals.unit_amount') }}" min="0"
                                                        step="1">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-1">Unidades (días × 3)</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[meals][units]" id="meals_units"
                                                        value="{{ old('allowances.meals.units', 3) }}" min="1"
                                                        step="1" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label mb-1">Descripción</label>
                                                    <input type="text" class="form-control"
                                                        name="allowances[meals][description]" id="meals_desc"
                                                        value="{{ old('allowances.meals.description', 'Alimentación') }}"
                                                        maxlength="255">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Total</label>
                                                    <input type="number" class="form-control" id="meals_total"
                                                        value="" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- PLANTA: OTROS --}}
                                    <div class="col-12 plant-only">
                                        <div class="border rounded p-2">
                                            <div class="form-check">
                                                <input class="form-check-input allowance-check" type="checkbox"
                                                    name="allowances[per_diem][include]" id="al_perdiem" value="1"
                                                    {{ old('allowances.per_diem.include') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="al_perdiem"><b>Otros</b></label>
                                            </div>

                                            <div class="row g-2 mt-2 allowance-fields" data-type="per_diem"
                                                style="display:none;">
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Valor unitario</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[per_diem][unit_amount]" id="perdiem_unit"
                                                        value="{{ old('allowances.per_diem.unit_amount') }}"
                                                        min="0" step="1">
                                                </div>
                                                <div class="col-md-2">
                                                    <label class="form-label mb-1">Unidades (días)</label>
                                                    <input type="number" class="form-control"
                                                        name="allowances[per_diem][units]" id="perdiem_units"
                                                        value="{{ old('allowances.per_diem.units', 1) }}" min="1"
                                                        step="1" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label mb-1">Descripción</label>
                                                    <input type="text" class="form-control"
                                                        name="allowances[per_diem][description]" id="perdiem_desc"
                                                        value="{{ old('allowances.per_diem.description', 'Otros') }}"
                                                        maxlength="255">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label mb-1">Total</label>
                                                    <input type="number" class="form-control" id="perdiem_total"
                                                        value="" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- RESUMEN --}}
                                    <div class="col-12">
                                        <div class="border rounded p-2 bg-light">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-6">
                                                    <div class="small text-muted">Total viáticos (informativo)</div>
                                                    <div class="fw-semibold"><span id="allowances_sum_label">—</span>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="small text-muted">Total general (informativo)</div>
                                                    <div class="fw-semibold"><span id="grand_total_label">—</span></div>
                                                </div>
                                            </div>
                                            <div class="small text-muted mt-1">
                                                Moto: <b>Estimado = viáticos</b>. No moto: <b>Estimado = transporte</b>.
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        {{-- OBJETO --}}
                        <div class="col-12">
                            <label class="form-label">Objeto</label>
                            <textarea class="form-control @error('objeto') is-invalid @enderror" name="objeto" rows="4"
                                placeholder="Describe el objeto de la solicitud (qué se hará, para qué y dónde)." required>{{ old('objeto') }}</textarea>
                            @error('objeto')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- DOCUMENTOS --}}
                        <div class="col-12">
                            <label class="form-label">Documentos soporte</label>
                            <input type="file"
                                class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror"
                                name="documents[]" id="documents" multiple accept=".pdf,.jpg,.jpeg,.png,.webp">

                            @error('documents')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror
                            @error('documents.*')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
                            @enderror

                            <div class="form-text">
                                Adjunta soportes (PDF o imagen). Recomendado máx: 5 archivos (hasta 5MB c/u).
                            </div>

                            <div class="mt-2 small text-muted" id="docsPreview" style="display:none;">
                                <div class="fw-semibold mb-1">Archivos seleccionados:</div>
                                <ul class="mb-0" id="docsList"></ul>
                            </div>
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

    {{-- MODAL: Crear Viático (Gasolina) --}}
    <div class="modal fade" id="modalGasolina" tabindex="-1" aria-labelledby="modalGasolinaLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalGasolinaLabel">Crear Viático - Gasolina (Moto)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_fuel_unit" class="form-label">Valor unitario (por día)</label>
                        <input type="number" class="form-control" id="modal_fuel_unit" min="0" step="1"
                            placeholder="Ej: 25000">
                        <div class="form-text">Ingresa el valor diario estimado de gasolina.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_fuel_units" class="form-label">Unidades (días)</label>
                        <input type="number" class="form-control" id="modal_fuel_units" min="1" step="1"
                            value="1" readonly>
                        <div class="form-text">Se calcula automáticamente según las fechas del viaje.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_fuel_desc" class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="modal_fuel_desc" value="Gasolina (moto)"
                            maxlength="255">
                    </div>
                    <div class="alert alert-info mb-0">
                        <strong>Total:</strong> <span id="modal_fuel_total_display">$ 0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmGasolina">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: Crear Viático (Hospedaje) --}}
    <div class="modal fade" id="modalHospedaje" tabindex="-1" aria-labelledby="modalHospedajeLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalHospedajeLabel">Crear Viático - Hospedaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_lodging_unit" class="form-label">Valor unitario (por noche)</label>
                        <input type="number" class="form-control" id="modal_lodging_unit" min="0"
                            step="1" placeholder="Ej: 80000">
                    </div>
                    <div class="mb-3">
                        <label for="modal_lodging_units" class="form-label">Unidades (noches)</label>
                        <input type="number" class="form-control" id="modal_lodging_units" min="1"
                            step="1" value="1" readonly>
                        <div class="form-text">Se calcula automáticamente (días - 1).</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_lodging_desc" class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="modal_lodging_desc" value="Hospedaje"
                            maxlength="255">
                    </div>
                    <div class="alert alert-info mb-0">
                        <strong>Total:</strong> <span id="modal_lodging_total_display">$ 0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmHospedaje">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: Crear Viático (Alimentación) --}}
    <div class="modal fade" id="modalAlimentacion" tabindex="-1" aria-labelledby="modalAlimentacionLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAlimentacionLabel">Crear Viático - Alimentación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_meals_unit" class="form-label">Valor unitario (por comida)</label>
                        <input type="number" class="form-control" id="modal_meals_unit" min="0" step="1"
                            placeholder="Ej: 15000">
                    </div>
                    <div class="mb-3">
                        <label for="modal_meals_units" class="form-label">Unidades (días × 3)</label>
                        <input type="number" class="form-control" id="modal_meals_units" min="3" step="1"
                            value="3" readonly>
                        <div class="form-text">Se calcula automáticamente (días × 3 comidas).</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_meals_desc" class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="modal_meals_desc" value="Alimentación"
                            maxlength="255">
                    </div>
                    <div class="alert alert-info mb-0">
                        <strong>Total:</strong> <span id="modal_meals_total_display">$ 0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmAlimentacion">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL: Crear Viático (Otros) --}}
    <div class="modal fade" id="modalOtros" tabindex="-1" aria-labelledby="modalOtrosLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalOtrosLabel">Crear Viático - Otros</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_perdiem_unit" class="form-label">Valor unitario (por día)</label>
                        <input type="number" class="form-control" id="modal_perdiem_unit" min="0"
                            step="1" placeholder="Ej: 10000">
                    </div>
                    <div class="mb-3">
                        <label for="modal_perdiem_units" class="form-label">Unidades (días)</label>
                        <input type="number" class="form-control" id="modal_perdiem_units" min="1"
                            step="1" value="1" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="modal_perdiem_desc" class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="modal_perdiem_desc" value="Otros"
                            maxlength="255">
                    </div>
                    <div class="alert alert-info mb-0">
                        <strong>Total:</strong> <span id="modal_perdiem_total_display">$ 0</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmOtros">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function() {
            const areaId = @json((int) $defaultAreaId);

            const budgetSelect = document.getElementById('budget_item_id');

            const departmentSelect = document.getElementById('department_id');
            const placeTypeSelect = document.getElementById('place_type');
            const placeTypeHelp = document.getElementById('placeTypeHelp');
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
            const transportHidden = document.getElementById('transport_mode_hidden');

            const transportBaseWrap = document.getElementById('transportBaseWrap');
            const transportTotalWrap = document.getElementById('transportTotalWrap');

            const allowancesWrap = document.getElementById('allowancesWrap');

            const btnCreateGasolina = document.getElementById('btnCreateGasolina');
            const btnCreateHospedaje = document.getElementById('btnCreateHospedaje');
            const btnCreateAlimentacion = document.getElementById('btnCreateAlimentacion');
            const btnCreateOtros = document.getElementById('btnCreateOtros');

            function syncTransportHidden() {
                if (transportHidden) transportHidden.value = transportSelect.value || '';
            }

            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const dateHelp1 = document.getElementById('dateHelp1');
            const dateHelp2 = document.getElementById('dateHelp2');

            const tripSelect = document.getElementById('trip_type');
            const tripHelp = document.getElementById('tripHelp');
            const transportValueInput = document.getElementById('transport_value');
            const transportTotalInput = document.getElementById('transport_total');
            const estimatedInput = document.getElementById('estimated_value');
            const transportTotalHelp = document.getElementById('transportTotalHelp');
            const rateHelp = document.getElementById('rateHelp');

            const IS_PLANT = @json((bool) $isPlant);

            const HUILA_ID = @json((int) $HUILA_ID);
            const HAS_MOTO = @json((bool) $hasMoto);

            const oldMunicipality = @json(old('municipality_id'));
            const oldVillage = @json(old('village_id'));
            const oldPlaceType = @json(old('place_type', 'municipio'));
            const oldBudget = @json(old('budget_item_id'));

            let map, destMarker, originMarker, routeLayer = null;
            const OSRM_BASE = 'https://router.project-osrm.org/route/v1';

            // Bootstrap modals
            let modalGasolina, modalHospedaje, modalAlimentacion, modalOtros;

            // Rubros
            fetch(@json(route('gdf.instructor.catalog.budgetItems')) + '?area_id=' + areaId, {
                    headers: {
                        'Accept': 'application/json'
                    }
                })
                .then(r => r.ok ? r.json() : Promise.reject(r))
                .then(items => {
                    (items || []).forEach(it => {
                        const opt = document.createElement('option');
                        opt.value = it.id;
                        opt.textContent = (it.code ? (it.code + ' - ') : '') + it.name;
                        if (oldBudget && String(oldBudget) === String(it.id)) opt.selected = true;
                        budgetSelect.appendChild(opt);
                    });
                })
                .catch(() => {});

            function isHuilaSelected() {
                const dep = parseInt(departmentSelect.value || '0', 10);
                return dep === HUILA_ID;
            }

            function isMotoSelected() {
                return (transportSelect.value || '') === 'motorcycle';
            }

            // ✅ mostrar/ocultar botones de “Crear viático”
            function refreshCreateButtons() {
                const moto = isMotoSelected();
                if (btnCreateGasolina) {
                    btnCreateGasolina.style.display = moto ? '' : 'none';
                    btnCreateGasolina.disabled = !moto;
                }
                // planta: se muestran si IS_PLANT (independiente del transporte)
                [btnCreateHospedaje, btnCreateAlimentacion, btnCreateOtros].forEach(b => {
                    if (!b) return;
                    b.style.display = IS_PLANT ? '' : 'none';
                    b.disabled = !IS_PLANT;
                });
            }

            function setPlaceUI() {
                const inHuila = isHuilaSelected();

                const optVereda = [...placeTypeSelect.options].find(o => o.value === 'vereda');
                if (optVereda) optVereda.disabled = !inHuila;

                if (!inHuila && placeTypeSelect.value === 'vereda') {
                    placeTypeSelect.value = 'municipio';
                }

                const isVereda = (placeTypeSelect.value === 'vereda') && inHuila;
                veredaWrap.style.display = isVereda ? '' : 'none';

                villageSelect.required = isVereda;
                municipalitySelect.required = true;

                if (!isVereda) {
                    villageSelect.value = '';
                    villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
                }

                if (placeTypeHelp) {
                    placeTypeHelp.textContent = inHuila ? 'En Huila puedes elegir Municipio o Vereda.' :
                        'Fuera de Huila: solo Municipio (Vereda no aplica).';
                }
            }

            function hasChosenDestination() {
                const deptOk = !!(departmentSelect.value && departmentSelect.value !== '');
                const munOk = !!(municipalitySelect.value && municipalitySelect.value !== '');
                if (!deptOk || !munOk) return false;
                if (placeTypeSelect.value === 'vereda') {
                    return !!(villageSelect.value && villageSelect.value !== '');
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

            function getSelectedText(selectEl) {
                const opt = selectEl.options[selectEl.selectedIndex];
                return opt ? opt.textContent.trim() : '';
            }

            let userTouchedTransportBase = false;

            function resetTransportBaseTouch() {
                userTouchedTransportBase = false;
            }

            transportValueInput.addEventListener('input', () => {
                userTouchedTransportBase = true;
                recalcTotals();
            });

            async function fetchTransportRate() {
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
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!res.ok) return null;
                    return await res.json();
                } catch (e) {
                    return null;
                }
            }

            async function refreshSuggestedTransportBase() {
                if (isMotoSelected()) {
                    rateHelp.textContent = 'Moto: el transporte se calcula en 0.';
                    transportValueInput.value = '';
                    recalcTotals();
                    return;
                }

                if (!hasChosenDestination()) {
                    rateHelp.textContent = 'Selecciona el destino para sugerir la tarifa.';
                    recalcTotals();
                    return;
                }

                const data = await fetchTransportRate();
                if (!data) {
                    rateHelp.textContent = 'No se pudo consultar la tarifa. Puedes digitar el valor.';
                    recalcTotals();
                    return;
                }

                const amount = Number(data.suggested_amount ?? data.base_amount ?? 0);

                if (userTouchedTransportBase) {
                    rateHelp.textContent = (amount > 0) ?
                        'Hay tarifa sugerida, pero no se sobreescribe porque ya editaste el valor.' :
                        'No hay tarifa registrada. Manteniendo tu valor manual.';
                    recalcTotals();
                    return;
                }

                if (amount > 0) {
                    transportValueInput.value = String(Math.round(amount));
                    rateHelp.textContent = `Sugerido según tarifas (${data.source || 'rates'}).`;
                } else {
                    transportValueInput.value = '';
                    rateHelp.textContent = 'No hay tarifa registrada. Puedes digitar el valor.';
                }

                recalcTotals();
            }

            function applyTransportRules() {
                const inHuila = isHuilaSelected();
                ['bus', 'van', 'motorcycle', 'air'].forEach(v => setTransportOptionEnabled(v, false));

                if (inHuila) {
                    setTransportOptionEnabled('bus', true);
                    setTransportOptionEnabled('motorcycle', !!HAS_MOTO);
                    setTransportOptionEnabled('van', false);
                    setTransportOptionEnabled('air', false);

                    if (HAS_MOTO) {
                        transportSelect.value = 'motorcycle';
                        transportSelect.disabled = true;
                        transportHelp.textContent = 'Tienes moto asignada: dentro de Huila el sistema fuerza "Moto".';
                    } else {
                        transportSelect.disabled = false;
                        ensureValidTransportSelection('bus');
                        transportHelp.textContent = 'Dentro de Huila: Bus. Camioneta solo la asigna Apoyo.';
                    }
                } else {
                    setTransportOptionEnabled('bus', true);
                    setTransportOptionEnabled('air', true);
                    setTransportOptionEnabled('motorcycle', false);
                    setTransportOptionEnabled('van', false);

                    transportSelect.disabled = false;
                    ensureValidTransportSelection('bus');
                    transportHelp.textContent = 'Fuera de Huila: Bus/Aéreo. Camioneta solo la asigna Apoyo.';
                }

                syncTransportHidden();
                refreshSuggestedTransportBase();
                setAllowancesVisibility();
                toggleTransportMoneyFields();
                refreshCreateButtons();
            }

            async function geocode(query) {
                const url =
                    `https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(query)}`;
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json'
                    }
                });
                if (!res.ok) return null;
                const data = await res.json();
                if (!data || !data.length) return null;
                return {
                    lat: parseFloat(data[0].lat),
                    lng: parseFloat(data[0].lon),
                    name: data[0].display_name
                };
            }

            function setOrigin(lat, lng, label) {
                originLat.value = String(lat);
                originLng.value = String(lng);
                originLabel.textContent = label || 'Origen';

                const ll = L.latLng(lat, lng);
                if (!originMarker) originMarker = L.marker(ll, {
                    draggable: false
                }).addTo(map);
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
                    destMarker = L.marker(ll, {
                        draggable: true
                    }).addTo(map);
                    destMarker.on('dragend', () => {
                        const pos = destMarker.getLatLng();
                        if (hasChosenDestination()) return;
                        setDestination(pos.lat, pos.lng, 'Destino (pin)', 'map');
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

                if (destMarker) {
                    destMarker.remove();
                    destMarker = null;
                }
                if (routeLayer) {
                    routeLayer.remove();
                    routeLayer = null;
                }
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

                if ([oLat, oLng].some(v => Number.isNaN(v)) || [dLat, dLng].some(v => Number.isNaN(v))) {
                    routeLabel.textContent = 'Selecciona destino';
                    routeKm.textContent = '—';
                    routeMin.textContent = '—';
                    if (routeLayer) {
                        routeLayer.remove();
                        routeLayer = null;
                    }
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
                    const res = await fetch(url, {
                        signal: routeAbort.signal
                    });
                    if (!res.ok) throw new Error('OSRM error');
                    const data = await res.json();
                    if (!data.routes || !data.routes.length) throw new Error('Sin ruta');

                    const r0 = data.routes[0];
                    routeLabel.textContent = 'Ruta por carretera';
                    routeKm.textContent = fmtKm(r0.distance);
                    routeMin.textContent = fmtMin(r0.duration);

                    if (routeLayer) routeLayer.remove();
                    routeLayer = L.geoJSON(r0.geometry, {
                        style: {
                            weight: 5,
                            opacity: 0.9
                        }
                    }).addTo(map);

                    const group = L.featureGroup([originMarker, destMarker, routeLayer].filter(Boolean));
                    map.fitBounds(group.getBounds().pad(0.2));
                } catch (e) {
                    if (e.name === 'AbortError') return;
                    routeLabel.textContent = 'No se pudo calcular ruta';
                    routeKm.textContent = '—';
                    routeMin.textContent = '—';
                    if (routeLayer) {
                        routeLayer.remove();
                        routeLayer = null;
                    }
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

                const query = (isVereda && verName) ?
                    `${verName}, ${munName}, ${deptName}, Colombia` :
                    `${munName}, ${deptName}, Colombia`;

                const r = await geocode(query);
                if (r) setDestination(r.lat, r.lng, r.name, 'selects');
            }

            function loadMunicipalities(departmentId, selectedId) {
                municipalitySelect.innerHTML = '<option value="">— Selecciona —</option>';
                villageSelect.innerHTML = '<option value="">— Selecciona —</option>';

                if (!departmentId) {
                    clearDestinationAndRoute();
                    return;
                }

                const url = @json(route('gdf.instructor.catalog.municipalities')) + '?department_id=' + encodeURIComponent(departmentId);

                fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.ok ? r.json() : Promise.reject(r))
                    .then(rows => {
                        (rows || []).forEach(row => {
                            const opt = document.createElement('option');
                            opt.value = row.id;
                            opt.textContent = row.name;
                            if (selectedId && String(selectedId) === String(row.id)) opt.selected = true;
                            municipalitySelect.appendChild(opt);
                        });

                        clearDestinationAndRoute();
                        if (selectedId) {
                            if (placeTypeSelect.value === 'vereda') loadVillages(selectedId, oldVillage);
                            else syncDestinationFromSelectsIfReady();
                        }
                        refreshSuggestedTransportBase();
                    })
                    .catch(() => {
                        clearDestinationAndRoute();
                    });
            }

            function loadVillages(municipalityId, selectedId) {
                villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
                if (!municipalityId) {
                    clearDestinationAndRoute();
                    return;
                }

                const url = @json(route('gdf.instructor.catalog.villages')) + '?municipality_id=' + encodeURIComponent(municipalityId);

                fetch(url, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    })
                    .then(r => r.ok ? r.json() : Promise.reject(r))
                    .then(rows => {
                        (rows || []).forEach(row => {
                            const opt = document.createElement('option');
                            opt.value = row.id;
                            opt.textContent = row.name;
                            if (selectedId && String(selectedId) === String(row.id)) opt.selected = true;
                            villageSelect.appendChild(opt);
                        });

                        clearDestinationAndRoute();
                        if (selectedId) syncDestinationFromSelectsIfReady();
                        refreshSuggestedTransportBase();
                    })
                    .catch(() => {
                        clearDestinationAndRoute();
                    });
            }

            function isSunday(dateStr) {
                if (!dateStr) return false;
                const d = new Date(dateStr + 'T00:00:00');
                return d.getDay() === 0;
            }

            function validateWeekdays() {
                let msg1 = '',
                    msg2 = '';
                if (isSunday(startDate.value)) msg1 = 'No se permite seleccionar domingo.';
                if (isSunday(endDate.value)) msg2 = 'No se permite seleccionar domingo.';
                dateHelp1.textContent = msg1;
                dateHelp2.textContent = msg2;
                if (isSunday(startDate.value)) startDate.value = '';
                if (isSunday(endDate.value)) endDate.value = '';
                recalcTotals();
            }

            function tripMultiplier() {
                return (tripSelect.value === 'roundtrip') ? 2 : 1;
            }

            function calcTransportTotal() {
                if (isMotoSelected()) return 0;
                const base = Number(transportValueInput.value || 0);
                return Math.max(0, Math.round(base * tripMultiplier()));
            }

            function calcDays() {
                if (!startDate.value || !endDate.value) return 0;
                const s = new Date(startDate.value + 'T00:00:00');
                const e = new Date(endDate.value + 'T00:00:00');
                const diff = Math.floor((e - s) / 86400000);
                if (Number.isNaN(diff) || diff < 0) return 0;
                return diff + 1;
            }

            function calcNights(days) {
                if (days <= 1) return 1;
                return Math.max(1, days - 1);
            }

            function setAllowanceFieldsVisibility() {
                document.querySelectorAll('.allowance-check').forEach(chk => {
                    const id = chk.id;
                    const t =
                        (id === 'al_perdiem') ? 'per_diem' :
                        (id === 'al_fuel') ? 'fuel' :
                        id.replace('al_', '');
                    const box = document.querySelector(`.allowance-fields[data-type="${t}"]`);
                    if (!box) return;
                    box.style.display = chk.checked ? '' : 'none';
                });
            }

            function recalcAllowances() {
                const days = calcDays();
                const nights = calcNights(days);

                const fuelOn = isMotoSelected() && document.getElementById('al_fuel')?.checked;
                if (fuelOn) {
                    const unitsEl = document.getElementById('fuel_units');
                    if (unitsEl) unitsEl.value = String(Math.max(1, days || 1));
                    const unit = Number(document.getElementById('fuel_unit')?.value || 0);
                    const total = Math.max(0, Math.round(unit * Math.max(1, days || 1)));
                    const out = document.getElementById('fuel_total');
                    if (out) out.value = String(total);
                } else {
                    const out = document.getElementById('fuel_total');
                    if (out) out.value = '';
                }

                const lodgingOn = document.getElementById('al_lodging')?.checked;
                if (lodgingOn) {
                    const unitsEl = document.getElementById('lodging_units');
                    if (unitsEl) unitsEl.value = String(nights);
                    const unit = Number(document.getElementById('lodging_unit')?.value || 0);
                    const total = Math.max(0, Math.round(unit * nights));
                    const out = document.getElementById('lodging_total');
                    if (out) out.value = String(total);
                } else {
                    const out = document.getElementById('lodging_total');
                    if (out) out.value = '';
                }

                const mealsOn = document.getElementById('al_meals')?.checked;
                if (mealsOn) {
                    const units = Math.max(1, (days || 1) * 3);
                    const unitsEl = document.getElementById('meals_units');
                    if (unitsEl) unitsEl.value = String(units);
                    const unit = Number(document.getElementById('meals_unit')?.value || 0);
                    const total = Math.max(0, Math.round(unit * units));
                    const out = document.getElementById('meals_total');
                    if (out) out.value = String(total);
                } else {
                    const out = document.getElementById('meals_total');
                    if (out) out.value = '';
                }

                const perdiemOn = document.getElementById('al_perdiem')?.checked;
                if (perdiemOn) {
                    const units = Math.max(1, (days || 1));
                    const unitsEl = document.getElementById('perdiem_units');
                    if (unitsEl) unitsEl.value = String(units);
                    const unit = Number(document.getElementById('perdiem_unit')?.value || 0);
                    const total = Math.max(0, Math.round(unit * units));
                    const out = document.getElementById('perdiem_total');
                    if (out) out.value = String(total);
                } else {
                    const out = document.getElementById('perdiem_total');
                    if (out) out.value = '';
                }

                const sum =
                    Number(document.getElementById('fuel_total')?.value || 0) +
                    Number(document.getElementById('lodging_total')?.value || 0) +
                    Number(document.getElementById('meals_total')?.value || 0) +
                    Number(document.getElementById('perdiem_total')?.value || 0);

                const sumLabel = document.getElementById('allowances_sum_label');
                if (sumLabel) sumLabel.textContent = sum ? ('$ ' + sum.toLocaleString('es-CO')) : '—';

                return sum;
            }

            function setAllowancesVisibility() {
                const moto = isMotoSelected();
                const show = IS_PLANT || moto;
                allowancesWrap.style.display = show ? '' : 'none';

                document.querySelectorAll('.plant-only').forEach(el => {
                    el.style.display = IS_PLANT ? '' : 'none';
                });

                document.querySelectorAll('.moto-only').forEach(el => {
                    el.style.display = moto ? '' : 'none';
                });

                if (!moto) {
                    const chk = document.getElementById('al_fuel');
                    if (chk) chk.checked = false;
                    setAllowanceFieldsVisibility();
                }
            }

            function toggleTransportMoneyFields() {
                const moto = isMotoSelected();
                transportBaseWrap.style.display = moto ? 'none' : '';
                transportTotalWrap.style.display = moto ? 'none' : '';
            }

            function recalcTotals() {
                const totalTransport = calcTransportTotal();
                transportTotalInput.value = (totalTransport || totalTransport === 0) ? String(totalTransport) : '';

                const mult = tripMultiplier();
                tripHelp.textContent = (mult === 2) ?
                    'Ida y vuelta: el total se calcula como base × 2.' :
                    'Ida o vuelta: el total se calcula como base × 1.';

                transportTotalHelp.textContent = (totalTransport || totalTransport === 0) ?
                    ('$ ' + Number(totalTransport).toLocaleString('es-CO')) :
                    '—';

                const allowancesSum = (IS_PLANT || isMotoSelected()) ? recalcAllowances() : 0;

                const estimated = isMotoSelected() ? Number(allowancesSum) : Number(totalTransport);
                estimatedInput.value = String(estimated);

                const grand = Number(totalTransport) + Number(allowancesSum);
                const grandLabel = document.getElementById('grand_total_label');
                if (grandLabel) grandLabel.textContent = grand ? ('$ ' + grand.toLocaleString('es-CO')) : '—';
            }

            function initMap() {
                map = L.map('gdfMap', {
                    scrollWheelZoom: false
                });
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                map.on('click', (e) => {
                    if (hasChosenDestination()) return;
                    setDestination(e.latlng.lat, e.latlng.lng, 'Destino (pin)', 'map');
                    resetTransportBaseTouch();
                    refreshSuggestedTransportBase();
                });

                syncOriginFromSelect();
            }

            // ===========================
            // ✅ MODALES: abrir / totales / confirmar
            // ===========================
            function moneyLabel(n) {
                return '$ ' + Number(n || 0).toLocaleString('es-CO');
            }

            function safeDays() {
                return Math.max(1, calcDays() || 1);
            }

            function safeNights() {
                return Math.max(1, calcNights(safeDays()));
            }

            function openModalGasolina() {
                allowancesWrap.style.display = '';
                if (!modalGasolina) modalGasolina = new bootstrap.Modal(document.getElementById('modalGasolina'));
                document.getElementById('modal_fuel_units').value = String(safeDays());
                document.getElementById('modal_fuel_unit').value = '';
                document.getElementById('modal_fuel_desc').value = 'Gasolina (moto)';
                document.getElementById('modal_fuel_total_display').textContent = moneyLabel(0);
                modalGasolina.show();
            }

            function openModalHospedaje() {
                allowancesWrap.style.display = '';
                if (!modalHospedaje) modalHospedaje = new bootstrap.Modal(document.getElementById('modalHospedaje'));
                document.getElementById('modal_lodging_units').value = String(safeNights());
                document.getElementById('modal_lodging_unit').value = '';
                document.getElementById('modal_lodging_desc').value = 'Hospedaje';
                document.getElementById('modal_lodging_total_display').textContent = moneyLabel(0);
                modalHospedaje.show();
            }

            function openModalAlimentacion() {
                allowancesWrap.style.display = '';
                if (!modalAlimentacion) modalAlimentacion = new bootstrap.Modal(document.getElementById(
                    'modalAlimentacion'));
                document.getElementById('modal_meals_units').value = String(safeDays() * 3);
                document.getElementById('modal_meals_unit').value = '';
                document.getElementById('modal_meals_desc').value = 'Alimentación';
                document.getElementById('modal_meals_total_display').textContent = moneyLabel(0);
                modalAlimentacion.show();
            }

            function openModalOtros() {
                allowancesWrap.style.display = '';
                if (!modalOtros) modalOtros = new bootstrap.Modal(document.getElementById('modalOtros'));
                document.getElementById('modal_perdiem_units').value = String(safeDays());
                document.getElementById('modal_perdiem_unit').value = '';
                document.getElementById('modal_perdiem_desc').value = 'Otros';
                document.getElementById('modal_perdiem_total_display').textContent = moneyLabel(0);
                modalOtros.show();
            }

            // botones abrir
            btnCreateGasolina?.addEventListener('click', () => {
                const el = document.getElementById('modalGasolina');
                if (!el) return;

                if (window.bootstrap && bootstrap.Modal) {
                    openModalGasolina();
                    return;
                }

                // fallback (por si bootstrap no cargó): abre por atributos
                el.classList.add('show');
                el.style.display = 'block';
                el.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
            });

            btnCreateHospedaje?.addEventListener('click', () => openModalHospedaje());
            btnCreateAlimentacion?.addEventListener('click', () => openModalAlimentacion());
            btnCreateOtros?.addEventListener('click', () => openModalOtros());

            // actualizar totales en modales (input)
            document.getElementById('modal_fuel_unit')?.addEventListener('input', () => {
                const unit = Number(document.getElementById('modal_fuel_unit').value || 0);
                const units = Number(document.getElementById('modal_fuel_units').value || 1);
                document.getElementById('modal_fuel_total_display').textContent = moneyLabel(Math.max(0, Math
                    .round(unit * units)));
            });
            document.getElementById('modal_lodging_unit')?.addEventListener('input', () => {
                const unit = Number(document.getElementById('modal_lodging_unit').value || 0);
                const units = Number(document.getElementById('modal_lodging_units').value || 1);
                document.getElementById('modal_lodging_total_display').textContent = moneyLabel(Math.max(0, Math
                    .round(unit * units)));
            });
            document.getElementById('modal_meals_unit')?.addEventListener('input', () => {
                const unit = Number(document.getElementById('modal_meals_unit').value || 0);
                const units = Number(document.getElementById('modal_meals_units').value || 3);
                document.getElementById('modal_meals_total_display').textContent = moneyLabel(Math.max(0, Math
                    .round(unit * units)));
            });
            document.getElementById('modal_perdiem_unit')?.addEventListener('input', () => {
                const unit = Number(document.getElementById('modal_perdiem_unit').value || 0);
                const units = Number(document.getElementById('modal_perdiem_units').value || 1);
                document.getElementById('modal_perdiem_total_display').textContent = moneyLabel(Math.max(0, Math
                    .round(unit * units)));
            });

            // confirmar (llenar checks + inputs reales)
            document.getElementById('btnConfirmGasolina')?.addEventListener('click', () => {
                const chk = document.getElementById('al_fuel');
                if (chk) chk.checked = true;

                document.getElementById('fuel_unit').value = String(Number(document.getElementById(
                    'modal_fuel_unit').value || 0));
                document.getElementById('fuel_desc').value = document.getElementById('modal_fuel_desc').value ||
                    'Gasolina (moto)';

                setAllowancesVisibility();
                setAllowanceFieldsVisibility();
                recalcTotals();
                modalGasolina?.hide();
                document.getElementById('fuel_unit')?.focus();
            });

            document.getElementById('btnConfirmHospedaje')?.addEventListener('click', () => {
                const chk = document.getElementById('al_lodging');
                if (chk) chk.checked = true;

                document.getElementById('lodging_unit').value = String(Number(document.getElementById(
                    'modal_lodging_unit').value || 0));
                document.getElementById('lodging_desc').value = document.getElementById('modal_lodging_desc')
                    .value || 'Hospedaje';

                setAllowancesVisibility();
                setAllowanceFieldsVisibility();
                recalcTotals();
                modalHospedaje?.hide();
                document.getElementById('lodging_unit')?.focus();
            });

            document.getElementById('btnConfirmAlimentacion')?.addEventListener('click', () => {
                const chk = document.getElementById('al_meals');
                if (chk) chk.checked = true;

                document.getElementById('meals_unit').value = String(Number(document.getElementById(
                    'modal_meals_unit').value || 0));
                document.getElementById('meals_desc').value = document.getElementById('modal_meals_desc')
                    .value || 'Alimentación';

                setAllowancesVisibility();
                setAllowanceFieldsVisibility();
                recalcTotals();
                modalAlimentacion?.hide();
                document.getElementById('meals_unit')?.focus();
            });

            document.getElementById('btnConfirmOtros')?.addEventListener('click', () => {
                const chk = document.getElementById('al_perdiem');
                if (chk) chk.checked = true;

                document.getElementById('perdiem_unit').value = String(Number(document.getElementById(
                    'modal_perdiem_unit').value || 0));
                document.getElementById('perdiem_desc').value = document.getElementById('modal_perdiem_desc')
                    .value || 'Otros';

                setAllowancesVisibility();
                setAllowanceFieldsVisibility();
                recalcTotals();
                modalOtros?.hide();
                document.getElementById('perdiem_unit')?.focus();
            });

            // ===========================
            // Listeners principales
            // ===========================
            originSelect.addEventListener('change', () => {
                syncOriginFromSelect();
            });

            departmentSelect.addEventListener('change', () => {
                setPlaceUI();
                applyTransportRules();
                loadMunicipalities(departmentSelect.value, null);
                resetTransportBaseTouch();
                rateHelp.textContent = '';
                recalcTotals();
            });

            placeTypeSelect.addEventListener('change', () => {
                setPlaceUI();
                clearDestinationAndRoute();
                resetTransportBaseTouch();
                rateHelp.textContent = '';

                const munId = municipalitySelect.value;
                if (placeTypeSelect.value === 'vereda' && munId) {
                    loadVillages(munId, null);
                } else {
                    refreshSuggestedTransportBase();
                }
                recalcTotals();
            });

            municipalitySelect.addEventListener('change', () => {
                clearDestinationAndRoute();
                resetTransportBaseTouch();
                rateHelp.textContent = '';

                if (placeTypeSelect.value === 'vereda') {
                    loadVillages(municipalitySelect.value, null);
                } else {
                    syncDestinationFromSelectsIfReady();
                    refreshSuggestedTransportBase();
                }
            });

            villageSelect.addEventListener('change', () => {
                clearDestinationAndRoute();
                resetTransportBaseTouch();
                rateHelp.textContent = '';
                syncDestinationFromSelectsIfReady();
                refreshSuggestedTransportBase();
            });

            transportSelect.addEventListener('change', () => {
                syncTransportHidden();
                resetTransportBaseTouch();
                rateHelp.textContent = '';
                refreshSuggestedTransportBase();
                setAllowancesVisibility();
                toggleTransportMoneyFields();
                refreshCreateButtons();
                recalcTotals();
            });

            startDate.addEventListener('change', () => {
                validateWeekdays();
                recalcTotals();
            });
            endDate.addEventListener('change', () => {
                validateWeekdays();
                recalcTotals();
            });

            tripSelect.addEventListener('change', () => {
                recalcTotals();
            });

            document.querySelectorAll('.allowance-check').forEach(chk => {
                chk.addEventListener('change', () => {
                    setAllowanceFieldsVisibility();
                    recalcTotals();
                });
            });

            ['fuel_unit', 'lodging_unit', 'meals_unit', 'perdiem_unit'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.addEventListener('input', recalcTotals);
            });

            const docsInput = document.getElementById('documents');
            const docsPreview = document.getElementById('docsPreview');
            const docsList = document.getElementById('docsList');
            if (docsInput && docsPreview && docsList) {
                docsInput.addEventListener('change', () => {
                    docsList.innerHTML = '';
                    const files = [...(docsInput.files || [])];
                    if (!files.length) {
                        docsPreview.style.display = 'none';
                        return;
                    }
                    docsPreview.style.display = '';
                    files.forEach(f => {
                        const li = document.createElement('li');
                        li.textContent = `${f.name} (${Math.round(f.size/1024)} KB)`;
                        docsList.appendChild(li);
                    });
                });
            }

            document.getElementById('gdfReqForm').addEventListener('submit', (e) => {
                if (isSunday(startDate.value) || isSunday(endDate.value)) {
                    e.preventDefault();
                    alert('No se permite seleccionar domingo. Corrige las fechas.');
                    return;
                }

                syncTransportHidden();

                if (!isHuilaSelected() && placeTypeSelect.value === 'vereda') {
                    e.preventDefault();
                    alert('Fuera de Huila el destino debe ser Municipio (Vereda no aplica).');
                    return;
                }

                if (placeTypeSelect.value === 'vereda' && !villageSelect.value) {
                    e.preventDefault();
                    alert('Para destino "Vereda" debes seleccionar una vereda.');
                    return;
                }

                if (isMotoSelected()) {
                    const chk = document.getElementById('al_fuel');
                    if (chk && chk.checked) {
                        const unit = Number(document.getElementById('fuel_unit')?.value || 0);
                        if (unit <= 0) {
                            e.preventDefault();
                            alert('Debes digitar el valor unitario de gasolina.');
                            return;
                        }
                    }
                }
            });

            // init
            initMap();

            placeTypeSelect.value = oldPlaceType || 'municipio';
            setPlaceUI();

            applyTransportRules();
            const deptInit = departmentSelect.value;
            loadMunicipalities(deptInit, oldMunicipality || null);

            syncTransportHidden();
            refreshSuggestedTransportBase();

            setAllowancesVisibility();
            toggleTransportMoneyFields();
            setAllowanceFieldsVisibility();
            refreshCreateButtons();
            recalcTotals();
        })();
    </script>
@endsection
