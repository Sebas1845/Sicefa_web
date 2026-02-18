{{-- Modules/GDF/Resources/views/official/requests/edit.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Editar solicitud')

@section('content')
@php
    use Carbon\Carbon;

    /** @var \Modules\GDF\Entities\TravelRequest $gdfRequest */
    $areaKey   = $areaKey ?? ($ctx['area'] ?? 'academic');
    $areaLabel = $areaKey === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA';

    $status = (string)($gdfRequest->status ?? '');
    $isReturned = $status === 'returned';

    // Datos existentes para precargar
    $seg0 = ($gdfRequest->segments ?? collect())->sortBy('id')->first();

    $transportCost = ($gdfRequest->costs ?? collect())->firstWhere('cost_type', 'transport');
    $transportMeta = [];
    if ($transportCost && is_string($transportCost->description)) {
        $tmp = json_decode($transportCost->description, true);
        if (is_array($tmp)) $transportMeta = $tmp;
    }

    // transport_mode visual (no está en travel_requests)
    $transportMode = old('transport_mode', $transportMeta['transport'] ?? 'bus');

    $placeType = old('place_type',
        $seg0?->destination_type === 'village' ? 'vereda' : 'municipio'
    );

    $departmentId   = old('department_id', (int)($seg0->department_id ?? 0));
    $municipalityId = old('municipality_id', (int)($seg0->municipality_id ?? 0));
    $villageId      = old('village_id', (int)($seg0->village_id ?? 0));

    $startDate = old('start_date', optional($gdfRequest->start_date)->format('Y-m-d'));
    $endDate   = old('end_date', optional($gdfRequest->end_date)->format('Y-m-d'));

    $tripType = old('trip_type',
        ($seg0?->trip_type === 'round_trip') ? 'roundtrip' : 'oneway'
    );

    // Si tienes origin/destination en travel_requests, úsalo; si no, usa del segmento
    $originKey = old('origin', (string)($gdfRequest->origin ?? $seg0?->origin_place ?? 'center_default'));

    $objeto = old('notes', (string)($gdfRequest->notes ?? ''));

    // allow types seleccionados para mostrar lista "Viáticos seleccionados"
    $allowancesByType = ($gdfRequest->allowances ?? collect())->groupBy('allowance_type');
    $selectedAllowanceTypes = $allowancesByType->keys()->values()->all();

    // labels viáticos
    $allowanceLabels = [
        'fuel'     => 'Gasolina',
        'lodging'  => 'Hospedaje',
        'meals'    => 'Alimentación',
        'per_diem' => 'Otros',
    ];

    // precarga unit amounts si existen allowances
    $aFuel    = $allowancesByType->get('fuel')?->first();
    $aLodging = $allowancesByType->get('lodging')?->first();
    $aMeals   = $allowancesByType->get('meals')?->first();
    $aPerdiem = $allowancesByType->get('per_diem')?->first();

    $isPlant = (bool)($isPlant ?? false);

    $HUILA_ID = (int)($HUILA_ID ?? 421);
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
  integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
  integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<style>
    #gdfMap { height: 380px; border-radius: 12px }
    .mapbox { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 12px }
    .map-hint { font-size: 12px; color: #6c757d; margin-top: 6px }
    .pill { display: inline-block; padding: .25rem .5rem; border-radius: 999px; border: 1px solid rgba(0,0,0,.1); background: #f8f9fa; font-size: .85rem }
    .softbox { border: 1px solid rgba(0,0,0,.08); border-radius: 12px; padding: 12px; background: #fff }
    .muted { color: #6c757d }
    .plant-only { display: none; }
    .moto-only  { display: none; }
</style>

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h2 class="mb-1">Editar solicitud GDF</h2>
            <div class="text-muted small">
                Área: <span class="pill">{{ $areaLabel }}</span>
                <span class="ms-1 pill">ID: {{ $gdfRequest->id }}</span>
                <span class="ms-1 pill">Estado: {{ \Modules\GDF\Entities\TravelRequest::statusLabel($status) }}</span>
            </div>
        </div>

        <a class="btn btn-outline-secondary" href="{{ route('gdf.instructor.requests.show', $gdfRequest->id) }}">Volver</a>
    </div>

    @if (!$isReturned)
        <div class="alert alert-warning mt-3">
            Esta solicitud no está en estado <b>Devuelta</b>. Por regla de negocio, solo se edita cuando está devuelta.
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger mt-3">{{ session('error') }}</div>
    @endif
    @if (session('success'))
        <div class="alert alert-success mt-3">{{ session('success') }}</div>
    @endif

    {{-- Aquí puedes mostrar última nota de revisión si la guardas en logs/reviews --}}
    {{-- <div class="alert alert-info mt-3">Motivo devolución: ...</div> --}}

    <form method="POST"
          action="{{ route('gdf.instructor.requests.update', $gdfRequest->id) }}"
          id="gdfEditForm"
          enctype="multipart/form-data"
          class="mt-3">
        @csrf
        @method('PUT')

        {{-- Bloquea submit si no está devuelta --}}
        <input type="hidden" id="isReturned" value="{{ $isReturned ? 1 : 0 }}">

        {{-- Hidden coords --}}
        <input type="hidden" name="origin_lat" id="origin_lat" value="{{ old('origin_lat', (string)($seg0->origin_lat ?? '')) }}">
        <input type="hidden" name="origin_lng" id="origin_lng" value="{{ old('origin_lng', (string)($seg0->origin_lng ?? '')) }}">
        <input type="hidden" name="destination_lat" id="destination_lat" value="{{ old('destination_lat', (string)($seg0->destination_lat ?? '')) }}">
        <input type="hidden" name="destination_lng" id="destination_lng" value="{{ old('destination_lng', (string)($seg0->destination_lng ?? '')) }}">

        {{-- transport_mode real (se guarda en meta/segments, no en travel_requests) --}}
        <input type="hidden" name="transport_mode" id="transport_mode_hidden" value="{{ $transportMode }}">

        <div class="card">
            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-4">
                        <label class="form-label">Área</label>
                        <input class="form-control" value="{{ $areaLabel }}" disabled>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Rubro</label>
                        <div class="form-control" style="background:#f8f9fa">
                            {{ optional($gdfRequest->budgetItem)->name ?? ('#'.$gdfRequest->budget_item_id) }}
                        </div>
                        <div class="form-text">En edición por devolución usualmente NO se cambia rubro.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Tipo</label>
                        <input class="form-control" value="Desplazamiento" disabled>
                    </div>

                    {{-- ORIGEN --}}
                    <div class="col-md-6">
                        <label class="form-label">Origen (Centro de formación)</label>
                        <select class="form-select @error('origin') is-invalid @enderror"
                                name="origin" id="origin" required>
                            @foreach ($origins as $o)
                                <option value="{{ $o['key'] }}"
                                    data-lat="{{ $o['lat'] }}" data-lng="{{ $o['lng'] }}"
                                    {{ $originKey === $o['key'] ? 'selected' : '' }}>
                                    {{ $o['label'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('origin') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">El mapa usa estas coordenadas como origen.</div>
                    </div>

                    {{-- TRANSPORTE --}}
                    <div class="col-md-6">
                        <label class="form-label">Medio de transporte</label>
                        <select class="form-select @error('transport_mode') is-invalid @enderror"
                                id="transport_mode" required>
                            <option value="bus" {{ $transportMode === 'bus' ? 'selected' : '' }}>Bus</option>
                            <option value="air" {{ $transportMode === 'air' ? 'selected' : '' }}>Aéreo</option>
                            <option value="motorcycle" {{ $transportMode === 'motorcycle' ? 'selected' : '' }}>Moto</option>
                            <option value="van" {{ $transportMode === 'van' ? 'selected' : '' }}>Camioneta</option>
                        </select>
                        @error('transport_mode') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text" id="transportHelp"></div>
                    </div>

                    {{-- DESTINO --}}
                    <div class="col-md-3">
                        <label class="form-label">Departamento destino</label>
                        <select class="form-select @error('department_id') is-invalid @enderror"
                                name="department_id" id="department_id" required>
                            <option value="">— Selecciona —</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" {{ (int)$departmentId === (int)$d->id ? 'selected' : '' }}>
                                    {{ $d->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('department_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Destino es</label>
                        <select class="form-select @error('place_type') is-invalid @enderror"
                                name="place_type" id="place_type" required>
                            <option value="municipio" {{ $placeType === 'municipio' ? 'selected' : '' }}>Municipio</option>
                            <option value="vereda" {{ $placeType === 'vereda' ? 'selected' : '' }}>Vereda</option>
                        </select>
                        @error('place_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text small text-muted" id="placeTypeHelp"></div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Municipio</label>
                        <select class="form-select @error('municipality_id') is-invalid @enderror"
                                name="municipality_id" id="municipality_id" required>
                            <option value="">— Selecciona —</option>
                        </select>
                        @error('municipality_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6" id="veredaWrap" style="display:none;">
                        <label class="form-label">Vereda</label>
                        <select class="form-select @error('village_id') is-invalid @enderror"
                                name="village_id" id="village_id">
                            <option value="">— Selecciona —</option>
                        </select>
                        @error('village_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- MAPA --}}
                    <div class="col-12">
                        <div class="mapbox">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="fw-semibold">Mapa (ruta automática)</div>
                                <div class="text-muted small">
                                    Origen: <span class="pill" id="originLabel">—</span> |
                                    Destino: <span class="pill" id="destLabel">—</span>
                                </div>
                            </div>

                            <div class="map-hint">
                                Si cambias municipio/vereda se recalcula la ruta. También puedes arrastrar el pin si no está seleccionado destino.
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
                                    <div class="fw-semibold"><span id="routeLabel">—</span></div>
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
                        <input type="date"
                               class="form-control @error('start_date') is-invalid @enderror"
                               name="start_date" id="start_date"
                               value="{{ $startDate }}" required>
                        @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text text-muted small" id="dateHelp1"></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Fecha fin</label>
                        <input type="date"
                               class="form-control @error('end_date') is-invalid @enderror"
                               name="end_date" id="end_date"
                               value="{{ $endDate }}" required>
                        @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text text-muted small" id="dateHelp2"></div>
                    </div>

                    {{-- TRAYECTO --}}
                    <div class="col-md-3">
                        <label class="form-label">Trayecto</label>
                        <select class="form-select @error('trip_type') is-invalid @enderror"
                                name="trip_type" id="trip_type" required>
                            <option value="oneway" {{ $tripType === 'oneway' ? 'selected' : '' }}>Ida</option>
                            <option value="return" {{ $tripType === 'return' ? 'selected' : '' }}>Vuelta</option>
                            <option value="roundtrip" {{ $tripType === 'roundtrip' ? 'selected' : '' }}>Ida y vuelta</option>
                        </select>
                        @error('trip_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text text-muted small" id="tripHelp"></div>
                    </div>

                    {{-- Transporte money fields --}}
                    <div class="col-md-3" id="transportBaseWrap">
                        <label class="form-label">Valor por trayecto (base)</label>
                        <input type="number"
                               class="form-control @error('transport_value') is-invalid @enderror"
                               name="transport_value" id="transport_value"
                               value="{{ old('transport_value', (int)($transportMeta['unit_amount'] ?? 0)) }}"
                               min="0" step="1">
                        @error('transport_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text text-muted small" id="rateHelp"></div>
                    </div>

                    <div class="col-md-3" id="transportTotalWrap">
                        <label class="form-label">Total transporte</label>
                        <input type="number" class="form-control" id="transport_total" value="" readonly>
                        <div class="form-text text-muted small" id="transportTotalHelp"></div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Estimado</label>
                        <input type="number"
                               class="form-control @error('estimated_value') is-invalid @enderror"
                               name="estimated_value" id="estimated_value"
                               value="{{ old('estimated_value', (int)($gdfRequest->total_amount ?? 0)) }}" readonly>
                        @error('estimated_value') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text text-muted small">
                            Si es <b>Moto</b>, el estimado será <b>viáticos</b>. Si no, será <b>transporte</b>.
                        </div>
                    </div>

                    {{-- VIÁTICOS --}}
                    <div class="col-12" id="allowancesWrap" style="display:none;">
                        <div class="softbox">
                            <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center">
                                <div class="fw-semibold">Viáticos</div>
                                <div class="text-muted small">Se guardan como <code>draft</code> al reenviar.</div>
                            </div>

                            <div class="mt-2">
                                <div class="muted small">Seleccionados actualmente</div>
                                <div class="fw-semibold">
                                    @if (count($selectedAllowanceTypes))
                                        @foreach ($selectedAllowanceTypes as $t)
                                            <span class="pill me-1">{{ $allowanceLabels[$t] ?? $t }}</span>
                                        @endforeach
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </div>
                            </div>

                            <div class="row g-2 mt-3">

                                {{-- GASOLINA --}}
                                <div class="col-12 moto-only">
                                    <div class="border rounded p-2">
                                        <div class="form-check">
                                            <input class="form-check-input allowance-check" type="checkbox"
                                                   name="allowances[fuel][include]" id="al_fuel" value="1"
                                                   {{ old('allowances.fuel.include', $aFuel ? 1 : 0) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="al_fuel"><b>Gasolina (moto)</b></label>
                                        </div>

                                        <div class="row g-2 mt-2 allowance-fields" data-type="fuel" style="display:none;">
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Valor unitario (por día)</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[fuel][unit_amount]" id="fuel_unit"
                                                       value="{{ old('allowances.fuel.unit_amount', (int)($aFuel->unit_amount ?? 0)) }}"
                                                       min="0" step="1">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label mb-1">Unidades (días)</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[fuel][units]" id="fuel_units"
                                                       value="{{ old('allowances.fuel.units', 1) }}" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1">Descripción</label>
                                                <input type="text" class="form-control"
                                                       name="allowances[fuel][description]" id="fuel_desc"
                                                       value="{{ old('allowances.fuel.description', (string)($aFuel->description ?? 'Gasolina (moto)')) }}"
                                                       maxlength="255">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Total</label>
                                                <input type="number" class="form-control" id="fuel_total" value="" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- HOSPEDAJE --}}
                                <div class="col-12 plant-only">
                                    <div class="border rounded p-2">
                                        <div class="form-check">
                                            <input class="form-check-input allowance-check" type="checkbox"
                                                   name="allowances[lodging][include]" id="al_lodging" value="1"
                                                   {{ old('allowances.lodging.include', $aLodging ? 1 : 0) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="al_lodging"><b>Hospedaje</b></label>
                                        </div>

                                        <div class="row g-2 mt-2 allowance-fields" data-type="lodging" style="display:none;">
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Valor unitario</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[lodging][unit_amount]" id="lodging_unit"
                                                       value="{{ old('allowances.lodging.unit_amount', (int)($aLodging->unit_amount ?? 0)) }}"
                                                       min="0" step="1">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label mb-1">Unidades (noches)</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[lodging][units]" id="lodging_units"
                                                       value="{{ old('allowances.lodging.units', 1) }}" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1">Descripción</label>
                                                <input type="text" class="form-control"
                                                       name="allowances[lodging][description]" id="lodging_desc"
                                                       value="{{ old('allowances.lodging.description', (string)($aLodging->description ?? 'Hospedaje')) }}"
                                                       maxlength="255">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Total</label>
                                                <input type="number" class="form-control" id="lodging_total" value="" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- ALIMENTACIÓN --}}
                                <div class="col-12 plant-only">
                                    <div class="border rounded p-2">
                                        <div class="form-check">
                                            <input class="form-check-input allowance-check" type="checkbox"
                                                   name="allowances[meals][include]" id="al_meals" value="1"
                                                   {{ old('allowances.meals.include', $aMeals ? 1 : 0) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="al_meals"><b>Alimentación</b></label>
                                        </div>

                                        <div class="row g-2 mt-2 allowance-fields" data-type="meals" style="display:none;">
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Valor unitario</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[meals][unit_amount]" id="meals_unit"
                                                       value="{{ old('allowances.meals.unit_amount', (int)($aMeals->unit_amount ?? 0)) }}"
                                                       min="0" step="1">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label mb-1">Unidades (días × 3)</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[meals][units]" id="meals_units"
                                                       value="{{ old('allowances.meals.units', 3) }}" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1">Descripción</label>
                                                <input type="text" class="form-control"
                                                       name="allowances[meals][description]" id="meals_desc"
                                                       value="{{ old('allowances.meals.description', (string)($aMeals->description ?? 'Alimentación')) }}"
                                                       maxlength="255">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Total</label>
                                                <input type="number" class="form-control" id="meals_total" value="" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- OTROS --}}
                                <div class="col-12 plant-only">
                                    <div class="border rounded p-2">
                                        <div class="form-check">
                                            <input class="form-check-input allowance-check" type="checkbox"
                                                   name="allowances[per_diem][include]" id="al_perdiem" value="1"
                                                   {{ old('allowances.per_diem.include', $aPerdiem ? 1 : 0) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="al_perdiem"><b>Otros</b></label>
                                        </div>

                                        <div class="row g-2 mt-2 allowance-fields" data-type="per_diem" style="display:none;">
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Valor unitario</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[per_diem][unit_amount]" id="perdiem_unit"
                                                       value="{{ old('allowances.per_diem.unit_amount', (int)($aPerdiem->unit_amount ?? 0)) }}"
                                                       min="0" step="1">
                                            </div>
                                            <div class="col-md-2">
                                                <label class="form-label mb-1">Unidades (días)</label>
                                                <input type="number" class="form-control"
                                                       name="allowances[per_diem][units]" id="perdiem_units"
                                                       value="{{ old('allowances.per_diem.units', 1) }}" readonly>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label mb-1">Descripción</label>
                                                <input type="text" class="form-control"
                                                       name="allowances[per_diem][description]" id="perdiem_desc"
                                                       value="{{ old('allowances.per_diem.description', (string)($aPerdiem->description ?? 'Otros')) }}"
                                                       maxlength="255">
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label mb-1">Total</label>
                                                <input type="number" class="form-control" id="perdiem_total" value="" readonly>
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
                                                <div class="fw-semibold"><span id="allowances_sum_label">—</span></div>
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

                    {{-- OBJETO (NOTES) --}}
                    <div class="col-12">
                        <label class="form-label">Objeto</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror"
                                  name="notes" rows="4"
                                  placeholder="Describe el objeto de la solicitud (qué se hará, para qué y dónde)."
                                  required>{{ $objeto }}</textarea>
                        @error('notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- DOCUMENTOS EXISTENTES --}}
                    <div class="col-12">
                        <label class="form-label">Documentos soporte (existentes)</label>

                        @if (($documents ?? collect())->count())
                            <div class="table-responsive">
                                <table class="table table-sm align-middle">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Tipo</th>
                                            <th>Título</th>
                                            <th>Archivo</th>
                                            <th>Estado</th>
                                            <th>Notas</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($documents as $d)
                                            <tr>
                                                <td>{{ $d->id }}</td>
                                                <td>{{ $d->document_type }}</td>
                                                <td>{{ $d->title ?? '—' }}</td>
                                                <td class="text-truncate" style="max-width:360px">
                                                    {{ $d->original_name ?? basename($d->path) }}
                                                    <div class="small text-muted">{{ $d->mime_type ?? '' }}</div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ strtoupper($d->status ?? 'submitted') }}</span>
                                                </td>
                                                <td class="text-muted small">{{ $d->notes ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted">— No hay documentos registrados.</div>
                        @endif

                        <div class="form-text">Si necesitas reemplazar/agregar soportes, adjunta nuevos abajo.</div>
                    </div>

                    {{-- DOCUMENTOS NUEVOS --}}
                    <div class="col-12">
                        <label class="form-label">Adjuntar nuevos soportes</label>
                        <input type="file"
                               class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror"
                               name="documents[]" id="documents"
                               multiple accept=".pdf,.jpg,.jpeg,.png,.webp">

                        @error('documents') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        @error('documents.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror

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
                    <button class="btn btn-primary" type="submit" id="btnSubmitEdit">
                        Reenviar solicitud
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('gdf.instructor.requests.show', $gdfRequest->id) }}">Cancelar</a>
                </div>

            </div>
        </div>
    </form>
</div>

<script>
(function () {
    const isReturned = (document.getElementById('isReturned')?.value === '1');
    const btnSubmit = document.getElementById('btnSubmitEdit');
    if (!isReturned && btnSubmit) btnSubmit.disabled = true;

    const departmentSelect = document.getElementById('department_id');
    const placeTypeSelect = document.getElementById('place_type');
    const placeTypeHelp   = document.getElementById('placeTypeHelp');
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
    const transportHidden = document.getElementById('transport_mode_hidden');
    const transportHelp = document.getElementById('transportHelp');

    const transportBaseWrap = document.getElementById('transportBaseWrap');
    const transportTotalWrap = document.getElementById('transportTotalWrap');

    const allowancesWrap = document.getElementById('allowancesWrap');

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
    const HAS_MOTO = @json((bool) $hasMoto);
    const HUILA_ID = @json((int) $HUILA_ID);

    const oldMunicipality = @json($municipalityId ?: null);
    const oldVillage = @json($villageId ?: null);
    const oldPlaceType = @json($placeType ?: 'municipio');

    let map, destMarker, originMarker, routeLayer = null;
    const OSRM_BASE = 'https://router.project-osrm.org/route/v1';

    function syncTransportHidden() {
        if (transportHidden) transportHidden.value = transportSelect.value || '';
    }

    function isHuilaSelected() {
        const dep = parseInt(departmentSelect.value || '0', 10);
        return dep === HUILA_ID;
    }

    function isMotoSelected() {
        return (transportSelect.value || '') === 'motorcycle';
    }

    function hasChosenDestination() {
        const deptOk = !!(departmentSelect.value && departmentSelect.value !== '');
        const munOk  = !!(municipalitySelect.value && municipalitySelect.value !== '');
        if (!deptOk || !munOk) return false;
        if (placeTypeSelect.value === 'vereda') return !!(villageSelect.value && villageSelect.value !== '');
        return true;
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

        if (!isVereda) {
            villageSelect.value = '';
            villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
        }

        if (placeTypeHelp) {
            placeTypeHelp.textContent = inHuila
                ? 'En Huila puedes elegir Municipio o Vereda.'
                : 'Fuera de Huila: solo Municipio (Vereda no aplica).';
        }
    }

    function setTransportOptionEnabled(value, enabled) {
        const opt = [...transportSelect.options].find(o => o.value === value);
        if (opt) opt.disabled = !enabled;
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
            setTransportOptionEnabled('motorcycle', !!HAS_MOTO);
            setTransportOptionEnabled('air', false);
            setTransportOptionEnabled('van', false);

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
        setAllowancesVisibility();
        toggleTransportMoneyFields();
    }

    function toggleTransportMoneyFields() {
        const moto = isMotoSelected();
        transportBaseWrap.style.display = moto ? 'none' : '';
        transportTotalWrap.style.display = moto ? 'none' : '';
    }

    function isSunday(dateStr) {
        if (!dateStr) return false;
        const d = new Date(dateStr + 'T00:00:00');
        return d.getDay() === 0;
    }

    function validateWeekdays() {
        let msg1 = '', msg2 = '';
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
            if (box) box.style.display = chk.checked ? '' : 'none';
        });
    }

    function recalcAllowances() {
        const days = calcDays();
        const nights = calcNights(days);

        const fuelOn = isMotoSelected() && document.getElementById('al_fuel')?.checked;
        if (fuelOn) {
            document.getElementById('fuel_units').value = String(Math.max(1, days || 1));
            const unit = Number(document.getElementById('fuel_unit')?.value || 0);
            document.getElementById('fuel_total').value = String(Math.max(0, Math.round(unit * Math.max(1, days || 1))));
        } else {
            const out = document.getElementById('fuel_total');
            if (out) out.value = '';
        }

        const lodgingOn = document.getElementById('al_lodging')?.checked;
        if (lodgingOn) {
            document.getElementById('lodging_units').value = String(nights);
            const unit = Number(document.getElementById('lodging_unit')?.value || 0);
            document.getElementById('lodging_total').value = String(Math.max(0, Math.round(unit * nights)));
        } else {
            const out = document.getElementById('lodging_total');
            if (out) out.value = '';
        }

        const mealsOn = document.getElementById('al_meals')?.checked;
        if (mealsOn) {
            const units = Math.max(1, (days || 1) * 3);
            document.getElementById('meals_units').value = String(units);
            const unit = Number(document.getElementById('meals_unit')?.value || 0);
            document.getElementById('meals_total').value = String(Math.max(0, Math.round(unit * units)));
        } else {
            const out = document.getElementById('meals_total');
            if (out) out.value = '';
        }

        const perdiemOn = document.getElementById('al_perdiem')?.checked;
        if (perdiemOn) {
            const units = Math.max(1, (days || 1));
            document.getElementById('perdiem_units').value = String(units);
            const unit = Number(document.getElementById('perdiem_unit')?.value || 0);
            document.getElementById('perdiem_total').value = String(Math.max(0, Math.round(unit * units)));
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

        document.querySelectorAll('.plant-only').forEach(el => el.style.display = IS_PLANT ? '' : 'none');
        document.querySelectorAll('.moto-only').forEach(el => el.style.display = moto ? '' : 'none');

        if (!moto) {
            const chk = document.getElementById('al_fuel');
            if (chk) chk.checked = false;
            setAllowanceFieldsVisibility();
        }
    }

    function recalcTotals() {
        const totalTransport = calcTransportTotal();
        if (transportTotalInput) transportTotalInput.value = String(totalTransport);

        const mult = tripMultiplier();
        if (tripHelp) tripHelp.textContent = (mult === 2)
            ? 'Ida y vuelta: el total se calcula como base × 2.'
            : 'Ida o vuelta: el total se calcula como base × 1.';

        if (transportTotalHelp) transportTotalHelp.textContent = '$ ' + Number(totalTransport).toLocaleString('es-CO');

        const allowancesSum = (IS_PLANT || isMotoSelected()) ? recalcAllowances() : 0;

        const estimated = isMotoSelected() ? Number(allowancesSum) : Number(totalTransport);
        if (estimatedInput) estimatedInput.value = String(estimated);

        const grand = Number(totalTransport) + Number(allowancesSum);
        const grandLabel = document.getElementById('grand_total_label');
        if (grandLabel) grandLabel.textContent = grand ? ('$ ' + grand.toLocaleString('es-CO')) : '—';
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
        if (originLabel) originLabel.textContent = label || 'Origen';

        const ll = L.latLng(lat, lng);
        if (!originMarker) originMarker = L.marker(ll, { draggable: false }).addTo(map);
        else originMarker.setLatLng(ll);
        updateRoute();
    }

    function setDestination(lat, lng, label, sourceTag) {
        destLat.value = String(lat);
        destLng.value = String(lng);

        if (destCoordsLabel) destCoordsLabel.textContent = `${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
        if (destNameLabel) destNameLabel.textContent = label || 'Destino';
        if (destLabel) destLabel.textContent = (sourceTag === 'selects') ? 'Por selección' : 'Por mapa';

        const ll = L.latLng(lat, lng);
        if (!destMarker) {
            destMarker = L.marker(ll, { draggable: true }).addTo(map);
        } else destMarker.setLatLng(ll);

        updateRoute();
    }

    function fmtKm(m) { const km = m / 1000; return `${km.toFixed(km >= 10 ? 1 : 2)} km`; }
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

        if ([oLat,oLng,dLat,dLng].some(v => Number.isNaN(v))) {
            if (routeLabel) routeLabel.textContent = '—';
            if (routeKm) routeKm.textContent = '—';
            if (routeMin) routeMin.textContent = '—';
            if (routeLayer) { routeLayer.remove(); routeLayer = null; }
            return;
        }

        if (routeAbort) routeAbort.abort();
        routeAbort = new AbortController();

        if (routeLabel) routeLabel.textContent = 'Calculando...';
        if (routeKm) routeKm.textContent = '...';
        if (routeMin) routeMin.textContent = '...';

        const coords = `${oLng},${oLat};${dLng},${dLat}`;
        const url = `${OSRM_BASE}/driving/${coords}?overview=full&geometries=geojson`;

        try {
            const res = await fetch(url, { signal: routeAbort.signal });
            if (!res.ok) throw new Error('OSRM error');
            const data = await res.json();
            if (!data.routes || !data.routes.length) throw new Error('Sin ruta');

            const r0 = data.routes[0];
            if (routeLabel) routeLabel.textContent = 'Ruta por carretera';
            if (routeKm) routeKm.textContent = fmtKm(r0.distance);
            if (routeMin) routeMin.textContent = fmtMin(r0.duration);

            if (routeLayer) routeLayer.remove();
            routeLayer = L.geoJSON(r0.geometry, { style: { weight: 5, opacity: 0.9 } }).addTo(map);

            const group = L.featureGroup([originMarker, destMarker, routeLayer].filter(Boolean));
            map.fitBounds(group.getBounds().pad(0.2));
        } catch (e) {
            if (e.name === 'AbortError') return;
            if (routeLabel) routeLabel.textContent = 'No se pudo calcular ruta';
            if (routeKm) routeKm.textContent = '—';
            if (routeMin) routeMin.textContent = '—';
            if (routeLayer) { routeLayer.remove(); routeLayer = null; }
        }
    }

    function syncOriginFromSelect() {
        const opt = originSelect.options[originSelect.selectedIndex];
        const oLat = parseFloat(opt?.dataset?.lat || '');
        const oLng = parseFloat(opt?.dataset?.lng || '');
        if (!Number.isNaN(oLat) && !Number.isNaN(oLng)) setOrigin(oLat, oLng, opt.textContent.trim());
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

        const url = @json(route('gdf.instructor.catalog.municipalities')) + '?department_id=' + encodeURIComponent(departmentId);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(rows => {
                (rows || []).forEach(row => {
                    const opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = row.name;
                    if (selectedId && String(selectedId) === String(row.id)) opt.selected = true;
                    municipalitySelect.appendChild(opt);
                });

                if (selectedId) {
                    if (placeTypeSelect.value === 'vereda') loadVillages(selectedId, oldVillage);
                    else syncDestinationFromSelectsIfReady();
                }
                recalcTotals();
            })
            .catch(() => {});
    }

    function loadVillages(municipalityId, selectedId) {
        villageSelect.innerHTML = '<option value="">— Selecciona —</option>';
        if (!municipalityId) return;

        const url = @json(route('gdf.instructor.catalog.villages')) + '?municipality_id=' + encodeURIComponent(municipalityId);
        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(rows => {
                (rows || []).forEach(row => {
                    const opt = document.createElement('option');
                    opt.value = row.id;
                    opt.textContent = row.name;
                    if (selectedId && String(selectedId) === String(row.id)) opt.selected = true;
                    villageSelect.appendChild(opt);
                });

                if (selectedId) syncDestinationFromSelectsIfReady();
                recalcTotals();
            })
            .catch(() => {});
    }

    // listeners
    originSelect?.addEventListener('change', syncOriginFromSelect);

    departmentSelect?.addEventListener('change', () => {
        setPlaceUI();
        applyTransportRules();
        loadMunicipalities(departmentSelect.value, null);
        recalcTotals();
    });

    placeTypeSelect?.addEventListener('change', () => {
        setPlaceUI();
        if (placeTypeSelect.value === 'vereda' && municipalitySelect.value) loadVillages(municipalitySelect.value, null);
        recalcTotals();
    });

    municipalitySelect?.addEventListener('change', () => {
        if (placeTypeSelect.value === 'vereda') loadVillages(municipalitySelect.value, null);
        else syncDestinationFromSelectsIfReady();
        recalcTotals();
    });

    villageSelect?.addEventListener('change', () => {
        syncDestinationFromSelectsIfReady();
        recalcTotals();
    });

    transportSelect?.addEventListener('change', () => {
        syncTransportHidden();
        applyTransportRules();
        setAllowanceFieldsVisibility();
        recalcTotals();
    });

    startDate?.addEventListener('change', validateWeekdays);
    endDate?.addEventListener('change', validateWeekdays);

    tripSelect?.addEventListener('change', recalcTotals);

    document.querySelectorAll('.allowance-check').forEach(chk => {
        chk.addEventListener('change', () => {
            setAllowanceFieldsVisibility();
            recalcTotals();
        });
    });

    ['fuel_unit','lodging_unit','meals_unit','perdiem_unit'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', recalcTotals);
    });

    // docs preview
    const docsInput = document.getElementById('documents');
    const docsPreview = document.getElementById('docsPreview');
    const docsList = document.getElementById('docsList');
    if (docsInput && docsPreview && docsList) {
        docsInput.addEventListener('change', () => {
            docsList.innerHTML = '';
            const files = [...(docsInput.files || [])];
            if (!files.length) { docsPreview.style.display = 'none'; return; }
            docsPreview.style.display = '';
            files.forEach(f => {
                const li = document.createElement('li');
                li.textContent = `${f.name} (${Math.round(f.size/1024)} KB)`;
                docsList.appendChild(li);
            });
        });
    }

    // submit validation
    document.getElementById('gdfEditForm')?.addEventListener('submit', (e) => {
        if (!isReturned) {
            e.preventDefault();
            alert('Solo puedes editar cuando la solicitud está DEVUELTA.');
            return;
        }
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

    // init map
    map = L.map('gdfMap', { scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    syncOriginFromSelect();

    // set initial place type
    placeTypeSelect.value = oldPlaceType || 'municipio';
    setPlaceUI();

    // load municipalities with selected
    const deptInit = departmentSelect.value;
    loadMunicipalities(deptInit, @json($municipalityId ?: null));

    // set destination marker if coords exist
    const dLatInit = parseFloat(destLat.value || '');
    const dLngInit = parseFloat(destLng.value || '');
    if (!Number.isNaN(dLatInit) && !Number.isNaN(dLngInit)) {
        setDestination(dLatInit, dLngInit, 'Destino (precargado)', 'selects');
    }

    applyTransportRules();
    setAllowanceFieldsVisibility();
    recalcTotals();
})();
</script>
@endsection
