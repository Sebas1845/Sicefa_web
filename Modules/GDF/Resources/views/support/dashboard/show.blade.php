{{-- Modules/GDF/Resources/views/support/dashboard/show.blade.php --}}
@extends('gdf::layouts.masteruser')

@php
    use Carbon\Carbon;

    // ✅ Estados centralizados
    use Modules\GDF\Status\TravelStatus;
    use Modules\GDF\Status\AllowanceStatus;

    $isSitrav = ($tr->module ?? '') === 'sitrav';
    $hasSigac = !is_null($pr);

    // ✅ NUEVO: detectar GDF manual (module=gdf y source=manual)
    $isGdfManual = ($tr->module ?? '') === 'gdf' && ($tr->source ?? '') === 'manual';

    // ✅ NUEVO: permitir documentos también en SITRAV cuando viene de SIGAC
    $canShowDocs = $isGdfManual || $isSitrav;

    $areaKey = $areaKey ?? (str_contains(request()->path(), 'campesena') ? 'campesena' : 'academic');
    $routePrefix = $routePrefix ?? ($areaKey === 'campesena' ? 'gdf.support.campesena' : 'gdf.support.academic');

    $titleId = $hasSigac ? $pr->id ?? $tr->id : $tr->id;

    $sigacState = $hasSigac ? ((string) ($pr->state ?? 'N/A')) : 'N/A';
    $supportStatus = $hasSigac
        ? ((string) (optional($pr)->support_status ?? 'Pendiente'))
        : ((string) ($tr->status ?? 'submitted') ?:
        'Pendiente');
    $locked = $hasSigac ? !is_null(optional($pr)->support_locked_at) : false;

    $placeType = (string) ($hasSigac ? $pr->place_type ?? 'municipio' : 'municipio');

    $money = fn($v) => '$ ' . number_format((float) $v, 0, ',', '.');

    $segments = $segments ?? collect();
    $allowances = $allowances ?? collect();
    $costs = $costs ?? collect();

    // ✅ NUEVO: logs
    $logs = $logs ?? collect();

    // El controller debe enviar $segmentMoto por segmento con:
    // assigned_id, assigned_plate, options, can_use_moto, has_available, options_count
    $segmentMoto = $segmentMoto ?? [];

    $segmentDestination = function ($seg) {
        if (($seg->destination_type ?? null) === 'village') {
            return 'Vereda #' . ($seg->village_id ?? 'N/D');
        }
        if (($seg->destination_type ?? null) === 'municipality') {
            return 'Municipio #' . ($seg->municipality_id ?? 'N/D');
        }
        return $seg->destination_place ?? 'Destino N/D';
    };

    // ✅ OJO: tus valores reales en BD son: terrestre|camioneta|aereo|moto
    $transportLabel = fn($tt) => match ((string) $tt) {
        'terrestre' => 'Bus',
        'camioneta' => 'Camioneta',
        'aereo' => 'Aéreo',
        'moto' => 'Moto',
        default => 'N/D',
    };

    $tripLabel = fn($t) => (string) $t === 'one_way' ? 'Solo ida' : 'Ida y vuelta';

    $trStatus = (string) ($tr->status ?? '');
    $canValidate = $trStatus === 'submitted';
    $canSendTreasury = in_array($trStatus, ['approved'], true);

    $motoFuelTotal = (float) $segments->where('is_cancelled', 0)->where('transport_type', 'moto')->sum('per_diem_cost');
    $motoHasAny = (bool) $segments->where('is_cancelled', 0)->where('transport_type', 'moto')->count();

    $safeDT = function ($dt, $fmt = 'Y-m-d H:i') {
        if (empty($dt)) {
            return 'N/D';
        }
        try {
            return Carbon::parse($dt)->format($fmt);
        } catch (\Throwable $e) {
            return 'N/D';
        }
    };

    $activeAllow = $allowances->whereIn('status', ['draft', 'liquidated', 'approved']);

    $perDiem = $allowances->firstWhere('allowance_type', 'per_diem');
    $perDiemActive = $perDiem && in_array((string) ($perDiem->status ?? ''), ['draft', 'liquidated', 'approved'], true);

    $fuelAllowance = $fuelAllowance ?? $allowances->firstWhere('allowance_type', 'fuel');
    $fuelActive =
        $fuelAllowance && in_array((string) ($fuelAllowance->status ?? ''), ['draft', 'liquidated', 'approved'], true);

    $startForUnits = $hasSigac ? $pr->start_date ?? $tr->start_date : $tr->start_date ?? null;
    $endForUnits = $hasSigac ? $pr->end_date ?? $tr->end_date : $tr->end_date ?? null;

    $suggestUnits = 1;
    try {
        if ($startForUnits) {
            $s = Carbon::parse($startForUnits)->startOfDay();
            $e = $endForUnits ? Carbon::parse($endForUnits)->startOfDay() : $s;
            $suggestUnits = max(1, $s->diffInDays($e) + 1);
        }
    } catch (\Throwable $e) {
        $suggestUnits = 1;
    }

    $blockedByStatus = in_array($trStatus, ['executed', 'confirmed'], true);

    $rateSuggested = $rateSuggested ?? 0;
    $rateLabel = $rateLabel ?? '';
@endphp

@section('title', ($isSitrav ? 'SITRAV' : 'GDF') . ' | Apoyo · Solicitud #' . $titleId)

@section('content')
    <style>
        .soft-card {
            border-radius: 14px;
        }

        .soft-card .card-header {
            border-top-left-radius: 14px;
            border-top-right-radius: 14px;
        }

        .kpi-label {
            font-size: .75rem;
            opacity: .8;
        }

        .kpi-value {
            font-size: 1.05rem;
            font-weight: 800;
        }

        .table thead th {
            white-space: nowrap;
        }

        .table td {
            vertical-align: middle;
        }

        .table td.wrap {
            white-space: normal;
        }

        .sticky-actions {
            top: 12px;
        }

        .mini-help {
            font-size: .78rem;
            opacity: .8;
        }

        .pill {
            border-radius: 999px;
            padding: .25rem .6rem;
            font-weight: 700;
        }
    </style>

    <div class="container-fluid">

        {{-- HEADER --}}
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-3">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h4 class="mb-0">Solicitud #{{ $titleId }}</h4>

                    {{-- ✅ TravelStatus --}}
                    <span class="badge bg-{{ TravelStatus::badge($tr->status ?? null) }}">
                        TR: {{ TravelStatus::label($tr->status ?? null) }}
                    </span>

                    @if ($hasSigac)
                        <span class="badge bg-info">SIGAC: {{ $sigacState }}</span>
                    @endif

                    <span class="badge bg-light text-dark border">{{ $isSitrav ? 'SITRAV' : 'GDF' }}</span>

                    {{-- ✅ NUEVO: marcador si es GDF manual --}}
                    @if ($isGdfManual)
                        <span class="badge bg-secondary">Manual</span>
                    @endif
                </div>

                <div class="small text-muted mt-1">
                    Apoyo: <b>{{ TravelStatus::label($tr->status ?? null) }}</b>
                    @if ($locked)
                        · <span class="text-danger">Bloqueada</span> desde {{ $pr->support_locked_at }}
                    @endif
                </div>

                @if ($blockedByStatus)
                    <div class="alert alert-light border mt-2 mb-0 py-2">
                        <div class="small">
                            <i class="bi bi-lock"></i>
                            Solicitud en estado <b>{{ TravelStatus::label($trStatus) }}</b>. Ediciones/eliminaciones
                            bloqueadas.
                        </div>
                    </div>
                @endif
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary"
                    href="{{ route($routePrefix . '.requests.index', request()->only(['tab', 'year', 'q', 'module'])) }}">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
                <a class="btn btn-outline-secondary" href="{{ route($routePrefix . '.rates.index') }}">
                    <i class="bi bi-receipt"></i> Tarifas
                </a>

                {{-- ✅ DOCUMENTOS: GDF manual O SITRAV (con SIGAC) --}}
                @if ($canShowDocs)
                    <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                        data-bs-target="#docsModal">
                        <i class="bi bi-file-earmark-text"></i> Documentos
                    </button>
                @endif
            </div>
        </div>

        {{-- FLASH --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-3">

            {{-- RESUMEN --}}
            <div class="col-lg-8">
                <div class="card soft-card shadow-sm h-100">
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-md-6">
                                <div class="text-muted small">Solicitante</div>
                                <div class="fw-semibold">
                                    {{ $hasSigac ? optional($pr->person)->fullname ?? 'Person #' . ($pr->person_id ?? 'N/D') : optional($tr->person)->fullname ?? 'N/D' }}
                                </div>
                                <div class="small text-muted">
                                    {{ $hasSigac ? $pr->email ?? '' : '' }}
                                    @if ($hasSigac && ($pr->email ?? null) && ($pr->telephone ?? null))
                                        ·
                                    @endif
                                    {{ $hasSigac ? $pr->telephone ?? '' : '' }}
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="text-muted small">Destino</div>
                                <div class="fw-semibold">
                                    @if ($hasSigac)
                                        @if ($placeType === 'vereda')
                                            Vereda: {{ optional($pr->village)->name ?? '#' . ($pr->village_id ?? 'N/D') }}
                                        @else
                                            Municipio:
                                            {{ optional($pr->municipality)->name ?? '#' . ($pr->municipality_id ?? 'N/D') }}
                                        @endif
                                    @else
                                        {{ $tr->origin ?? 'Origen N/D' }} → {{ $tr->destination ?? 'Destino N/D' }}
                                    @endif
                                </div>
                                <div class="small text-muted">{{ $hasSigac ? $pr->address ?? '' : '' }}</div>
                            </div>

                            <div class="col-md-3">
                                <div class="kpi-label text-muted">Fechas</div>
                                <div class="kpi-value">
                                    {{ $hasSigac ? $pr->start_date ?? $tr->start_date : $tr->start_date ?? 'N/D' }}
                                    @php $end = $hasSigac ? ($pr->end_date ?? $tr->end_date) : ($tr->end_date ?? null); @endphp
                                    @if ($end)
                                        → {{ $end }}
                                    @endif
                                </div>
                            </div>

                            <div class="col-md-3">
                                <div class="kpi-label text-muted">Transporte</div>
                                <div class="kpi-value">{{ $money($tr->total_transport ?? 0) }}</div>
                                <div class="mini-help text-muted">Viático: {{ $money($tr->total_per_diem ?? 0) }}</div>
                            </div>

                            <div class="col-md-3">
                                <div class="kpi-label text-muted">Total</div>
                                <div class="kpi-value">{{ $money($tr->total_amount ?? 0) }}</div>
                                <div class="mini-help text-muted">Otros: {{ $money($tr->total_other ?? 0) }}</div>
                            </div>

                            <div class="col-md-3">
                                <div class="kpi-label text-muted">Combustible sugerido</div>
                                <div class="kpi-value">{{ $money($rateSuggested ?? 0) }}</div>
                                <div class="mini-help text-muted">{{ $rateLabel ?? '—' }}</div>
                            </div>

                            <div class="col-12">
                                <div class="text-muted small">Objeto</div>
                                <div class="fw-semibold">{{ $hasSigac ? $pr->observation ?? '-' : $tr->notes ?? '-' }}
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            {{-- ACCIONES --}}
            <div class="col-lg-4">
                <div class="card soft-card shadow-sm position-lg-sticky sticky-actions">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-lightning-charge me-1"></i> Acciones Apoyo
                    </div>
                    <div class="card-body d-grid gap-2">

                        @if ($canValidate && !$blockedByStatus)
                            <form method="POST"
                                action="{{ route($routePrefix . '.requests.seen', ['travelRequest' => $tr->id]) }}">
                                @csrf
                                <button class="btn btn-success w-100"><i class="bi bi-check2-circle"></i> Validar (Dar
                                    visto)</button>
                                <div class="mini-help text-muted mt-1">
                                    Pasa TR a <b>{{ TravelStatus::label('approved') }}</b>.
                                </div>
                            </form>
                        @else
                            <button class="btn btn-success w-100" disabled><i class="bi bi-check2-circle"></i> Validar (Dar
                                visto)</button>
                            <div class="mini-help text-muted">
                                @if ($blockedByStatus)
                                    Bloqueado por estado: <b>{{ TravelStatus::label($trStatus ?: null) }}</b>
                                @else
                                    No aplica en estado: <b>{{ TravelStatus::label($trStatus ?: null) }}</b>
                                @endif
                            </div>
                        @endif

                        <hr class="my-2">

                        <form method="POST"
                            action="{{ route($routePrefix . '.requests.return', ['travelRequest' => $tr->id]) }}">
                            @csrf
                            <label class="form-label mb-1 small">Devolver al instructor (motivo)</label>
                            <textarea class="form-control form-control-sm" name="support_notes" rows="3" required
                                {{ $blockedByStatus ? 'disabled' : '' }}>{{ old('support_notes') }}</textarea>
                            <button class="btn btn-outline-warning mt-2 w-100" {{ $blockedByStatus ? 'disabled' : '' }}>
                                <i class="bi bi-arrow-counterclockwise"></i> Devolver
                            </button>
                        </form>

                        <hr class="my-2">

                        @if ($canSendTreasury && !$blockedByStatus)
                            <form method="POST"
                                action="{{ route($routePrefix . '.requests.sendTreasury', ['travelRequest' => $tr->id]) }}">
                                @csrf
                                <button class="btn btn-dark w-100"><i class="bi bi-send"></i> Enviar a Tesorería</button>
                                <div class="mini-help text-muted mt-1">
                                    Disponible porque TR está en <b>{{ TravelStatus::label('approved') }}</b>.
                                </div>
                            </form>
                        @else
                            <div class="mini-help text-muted">
                                @if ($blockedByStatus)
                                    Bloqueado por estado: <b>{{ TravelStatus::label($trStatus) }}</b>
                                @else
                                    "Enviar a Tesorería" aparece solo cuando TR está en
                                    <b>{{ TravelStatus::label('approved') }}</b>.
                                @endif
                            </div>
                        @endif

                    </div>
                </div>
            </div>

            {{-- SEGMENTOS --}}
            <div class="col-12">
                <div class="card soft-card shadow-sm">
                    <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div><i class="bi bi-calendar2-week me-1"></i> Segmentos del viaje</div>
                        <small class="text-muted">Regla UI: si el segmento está en <b>Moto</b> ⇒ <b>Costo transporte
                                $0</b>.</small>
                    </div>

                    <div class="card-body p-0">
                        @if ($segments->count() === 0)
                            <div class="p-4 text-center text-muted">
                                <i class="bi bi-calendar2-week" style="font-size:2.0rem;opacity:.4;"></i>
                                <div class="mt-2">No hay segmentos registrados para esta solicitud.</div>
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:170px;">Fecha</th>
                                            <th>Ruta</th>
                                            <th style="width:180px;">Modo</th>
                                            <th class="text-end" style="width:120px;">Transp.</th>
                                            <th class="text-end" style="width:120px;">Total</th>
                                            <th style="width:160px;" class="text-end">Acción</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($segments as $seg)
                                            @php
                                                $cancelled = (int) ($seg->is_cancelled ?? 0) === 1;

                                                $tripType = (string) ($seg->trip_type ?? 'round_trip');
                                                $trips = match ($tripType) {
                                                    'one_way' => 1,
                                                    'two_way' => 1,
                                                    default => 2,
                                                };

                                                $displayTransport =
                                                    (float) (($seg->transport_type ?? '') === 'moto'
                                                        ? 0
                                                        : $seg->transport_cost ?? 0);
                                                $displayPerDiem = (float) ($seg->per_diem_cost ?? 0);
                                                $displayOther = (float) ($seg->other_cost ?? 0);
                                                $segTotal = $displayTransport + $displayPerDiem + $displayOther;

                                                $oneWayShown =
                                                    $trips > 0 ? $displayTransport / $trips : $displayTransport;

                                                $m = $segmentMoto[$seg->id] ?? [
                                                    'assigned_id' => null,
                                                    'assigned_plate' => null,
                                                    'options' => collect(),
                                                    'can_use_moto' => false,
                                                    'has_available' => false,
                                                    'options_count' => 0,
                                                ];

                                                $assignedMotoId = $m['assigned_id'] ?? null;
                                                $assignedMotoPlate = $m['assigned_plate'] ?? null;
                                                $options = $m['options'] ?? collect();
                                                $canUseMoto = (bool) ($m['can_use_moto'] ?? false);
                                                $hasAvailableMoto = (bool) ($m['has_available'] ?? false);
                                                $optionsCount =
                                                    (int) ($m['options_count'] ??
                                                        (is_countable($options) ? count($options) : 0));

                                                $collapseId = 'segEdit' . $seg->id;
                                                $segMode = (string) ($seg->transport_type ?? 'terrestre');

                                                $motoInvalid =
                                                    $segMode === 'moto' && !$canUseMoto && empty($assignedMotoId);
                                            @endphp

                                            <tr @if ($cancelled) class="table-secondary" @endif>
                                                <td>
                                                    <div class="fw-semibold small">{{ $safeDT($seg->departure_at) }}</div>
                                                    <div class="text-muted" style="font-size:.78rem;">Regreso:
                                                        {{ $safeDT($seg->return_at) }}</div>
                                                    <div class="text-muted" style="font-size:.78rem;">
                                                        {{ $tripLabel($tripType) }} · Viajes: {{ $trips }}
                                                    </div>
                                                    @if ($cancelled)
                                                        <span class="badge bg-secondary mt-1">Cancelado</span>
                                                    @endif
                                                    @if ($motoInvalid)
                                                        <div class="text-danger" style="font-size:.78rem;">
                                                            <i class="bi bi-exclamation-triangle"></i>
                                                            Segmento en Moto pero no hay disponibilidad ni asignación.
                                                        </div>
                                                    @endif
                                                </td>

                                                <td class="wrap">
                                                    <div class="fw-semibold small">
                                                        {{ $seg->origin_place ?? 'Origen N/D' }} →
                                                        {{ $seg->destination_place ?? $segmentDestination($seg) }}
                                                    </div>
                                                    <div class="text-muted" style="font-size:.78rem;">Destino:
                                                        {{ $seg->destination_type ?? 'other' }}</div>
                                                    @if (!empty($seg->change_reason))
                                                        <div class="text-danger" style="font-size:.78rem;">Motivo:
                                                            {{ $seg->change_reason }}</div>
                                                    @endif
                                                </td>

                                                <td>
                                                    <span
                                                        class="badge bg-secondary">{{ $transportLabel($seg->transport_type ?? null) }}</span>
                                                    @if (($seg->transport_type ?? '') === 'moto')
                                                        <div class="text-muted" style="font-size:.78rem;">Moto:
                                                            <b>{{ $assignedMotoPlate ?: 'N/D' }}</b></div>
                                                    @endif
                                                    @if (!$hasAvailableMoto && empty($assignedMotoId))
                                                        <div class="text-muted" style="font-size:.78rem;">
                                                            <i class="bi bi-info-circle"></i> Sin motos disponibles en el
                                                            área.
                                                        </div>
                                                    @endif
                                                </td>

                                                <td class="text-end fw-semibold small">{{ $money($displayTransport) }}
                                                </td>
                                                <td class="text-end fw-semibold small">{{ $money($segTotal) }}</td>

                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                                        data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}"
                                                        aria-expanded="false" aria-controls="{{ $collapseId }}"
                                                        {{ $blockedByStatus ? 'disabled' : '' }}>
                                                        <i class="bi bi-pencil-square"></i> Modificar
                                                    </button>
                                                </td>
                                            </tr>

                                            <tr class="border-0">
                                                <td colspan="6" class="p-0 border-0">
                                                    <div class="collapse" id="{{ $collapseId }}">
                                                        <div class="p-3 border-top bg-light">

                                                            <form method="POST"
                                                                action="{{ route($routePrefix . '.requests.segments.update', ['travelRequest' => $tr->id, 'segmentId' => $seg->id]) }}"
                                                                class="row g-2 align-items-end js-seg-form-mini"
                                                                data-can-moto="{{ $canUseMoto ? 1 : 0 }}"
                                                                @if ($blockedByStatus) onsubmit="return false;" @endif>
                                                                @csrf

                                                                <div class="col-md-3">
                                                                    <label class="form-label small text-muted">Modo</label>
                                                                    <select class="form-select form-select-sm js-transport"
                                                                        name="transport_type"
                                                                        {{ $blockedByStatus ? 'disabled' : '' }}>
                                                                        <option value="terrestre"
                                                                            @selected(old('transport_type', $segMode) === 'terrestre')>Bus</option>
                                                                        <option value="camioneta"
                                                                            @selected(old('transport_type', $segMode) === 'camioneta')>Camioneta</option>
                                                                        <option value="aereo"
                                                                            @selected(old('transport_type', $segMode) === 'aereo')>Aéreo</option>

                                                                        <option value="moto" @selected(old('transport_type', $segMode) === 'moto')
                                                                            @disabled(!$canUseMoto)>
                                                                            Moto{{ !$canUseMoto ? ' (sin disponibilidad)' : '' }}
                                                                        </option>
                                                                    </select>

                                                                    <div class="mini-help text-muted">
                                                                        Trayecto fijo: {{ $tripLabel($tripType) }}
                                                                        ({{ $trips }} viajes).
                                                                    </div>

                                                                    @if (!$canUseMoto && !$blockedByStatus)
                                                                        <div class="text-danger mini-help">
                                                                            No hay motos disponibles para el área y la
                                                                            solicitud no tiene moto asignada.
                                                                        </div>
                                                                    @endif
                                                                </div>

                                                                <div class="col-md-3">
                                                                    <label class="form-label small text-muted">Costo (solo
                                                                        ida)</label>
                                                                    <input
                                                                        class="form-control form-control-sm text-end js-oneway"
                                                                        name="one_way_cost" type="number" min="0"
                                                                        step="0.01"
                                                                        value="{{ old('one_way_cost', $oneWayShown) }}"
                                                                        {{ $blockedByStatus ? 'disabled' : '' }}>
                                                                    <div class="mini-help text-muted">Se multiplica
                                                                        automáticamente por {{ $trips }}.</div>
                                                                </div>

                                                                <div class="col-md-4 js-moto-box d-none">
                                                                    <label class="form-label small text-muted">Moto</label>

                                                                    @if ($optionsCount <= 0)
                                                                        <div class="alert alert-warning py-2 mb-0">
                                                                            No hay motos disponibles para esta área.
                                                                        </div>
                                                                    @else
                                                                        <select
                                                                            class="form-select form-select-sm js-moto-select"
                                                                            name="motorcycle_id"
                                                                            {{ $blockedByStatus ? 'disabled' : '' }}>
                                                                            <option value="">Selecciona moto...
                                                                            </option>

                                                                            @if ($assignedMotoId)
                                                                                <option value="{{ $assignedMotoId }}"
                                                                                    selected>
                                                                                    {{ $assignedMotoPlate ? $assignedMotoPlate : 'Moto #' . $assignedMotoId }}
                                                                                    (asignada)
                                                                                </option>
                                                                            @endif

                                                                            @foreach ($options as $op)
                                                                                <option value="{{ $op->id }}">
                                                                                    {{ $op->plate ?? 'Moto #' . $op->id }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    @endif

                                                                    <div class="mini-help text-muted">Solo aplica si eliges
                                                                        Moto.</div>
                                                                </div>

                                                                <div class="col-md-2">
                                                                    <div class="form-check">
                                                                        <input class="form-check-input" type="checkbox"
                                                                            id="cancel{{ $seg->id }}"
                                                                            name="is_cancelled" value="1"
                                                                            {{ $blockedByStatus ? 'disabled' : '' }}>
                                                                        <label class="form-check-label small"
                                                                            for="cancel{{ $seg->id }}">Cancelar</label>
                                                                    </div>
                                                                    <input class="form-control form-control-sm mt-1"
                                                                        name="change_reason"
                                                                        value="{{ old('change_reason', $seg->change_reason) }}"
                                                                        placeholder="Motivo (si cancelas)"
                                                                        {{ $blockedByStatus ? 'disabled' : '' }}>
                                                                </div>

                                                                <div class="col-12 d-flex justify-content-end gap-2">
                                                                    <button class="btn btn-sm btn-outline-secondary"
                                                                        type="button" data-bs-toggle="collapse"
                                                                        data-bs-target="#{{ $collapseId }}">
                                                                        Cerrar
                                                                    </button>
                                                                    <button class="btn btn-sm btn-primary" type="submit"
                                                                        {{ $blockedByStatus ? 'disabled' : '' }}>
                                                                        <i class="bi bi-save"></i> Guardar
                                                                    </button>
                                                                </div>

                                                            </form>

                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- VIÁTICOS / ALLOWANCES --}}
            <div class="col-12">
                <div class="card soft-card shadow-sm">
                    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <div class="fw-semibold"><i class="bi bi-cash-coin me-1"></i> Viáticos</div>
                    </div>

                    <div class="card-body">

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal"
                                data-bs-target="#perDiemModal" {{ $blockedByStatus ? 'disabled' : '' }}>
                                <i class="bi bi-pencil-square"></i>
                                {{ $perDiemActive ? 'Editar viático (Por dias)' : 'Liquidar viático (Por dias)' }}
                            </button>

                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#mealsModal" {{ $blockedByStatus ? 'disabled' : '' }}>
                                <i class="bi bi-egg-fried"></i> Alimentacion
                            </button>

                            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#lodgingModal" {{ $blockedByStatus ? 'disabled' : '' }}>
                                <i class="bi bi-house-door"></i> Hospedaje
                            </button>

                            @if ($motoHasAny)
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                                    data-bs-target="#fuelModal" {{ $blockedByStatus ? 'disabled' : '' }}>
                                    <i class="bi bi-fuel-pump"></i> Gasolina
                                </button>
                            @endif

                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                                data-bs-target="#rejectAllowancesModal" {{ $blockedByStatus ? 'disabled' : '' }}>
                                <i class="bi bi-x-circle"></i> Rechazar viáticos
                            </button>

                            @if ($activeAllow->count())
                                <span class="pill bg-light border">
                                    Total Viaticos activos: <b>{{ $money($activeAllow->sum('calculated_amount')) }}</b>
                                </span>
                            @else
                                <span class="text-muted small">Aún no hay Viaticos activos.</span>
                            @endif
                        </div>

                        {{-- Resumen per diem --}}
                        <div class="mt-3">
                            @if ($perDiemActive)
                                <div class="pill bg-light border">
                                    Por dias actual: <b>{{ $money($perDiem->calculated_amount ?? 0) }}</b>
                                    <span class="text-muted">({{ (int) ($perDiem->units ?? 1) }} ×
                                        {{ $money($perDiem->unit_amount ?? 0) }})</span>
                                </div>

                                {{-- ✅ AllowanceStatus --}}
                                <span class="text-muted small ms-2">
                                    Estado:
                                    <span class="badge bg-{{ AllowanceStatus::badge($perDiem->status ?? null) }}">
                                        {{ AllowanceStatus::label($perDiem->status ?? null) }}
                                    </span>
                                </span>

                                @if (Route::has($routePrefix . '.requests.allowances.destroy') && !$blockedByStatus)
                                    <form class="d-inline-block ms-2" method="POST"
                                        action="{{ route($routePrefix . '.requests.allowances.destroy', ['travelRequest' => $tr->id, 'allowance' => $perDiem->id]) }}"
                                        onsubmit="return confirm('¿Eliminar el Por Dias?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Eliminar Por dia
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>

                        {{-- Resumen gasolina --}}
                        <div class="mt-2">
                            @if ($fuelActive)
                                <div class="pill bg-light border">
                                    Gasolina actual: <b>{{ $money($fuelAllowance->calculated_amount ?? 0) }}</b>
                                    <span class="text-muted">({{ (int) ($fuelAllowance->units ?? 1) }} ×
                                        {{ $money($fuelAllowance->unit_amount ?? 0) }})</span>
                                </div>

                                {{-- ✅ AllowanceStatus --}}
                                <span class="text-muted small ms-2">
                                    Estado:
                                    <span class="badge bg-{{ AllowanceStatus::badge($fuelAllowance->status ?? null) }}">
                                        {{ AllowanceStatus::label($fuelAllowance->status ?? null) }}
                                    </span>
                                </span>

                                @if (Route::has($routePrefix . '.requests.allowances.destroy') && !$blockedByStatus)
                                    <form class="d-inline-block ms-2" method="POST"
                                        action="{{ route($routePrefix . '.requests.allowances.destroy', ['travelRequest' => $tr->id, 'allowance' => $fuelAllowance->id]) }}"
                                        onsubmit="return confirm('¿Eliminar GASOLINA?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Eliminar gasolina
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>

                        <hr class="my-3">

                        {{-- Tabla allowances --}}
                        @if ($activeAllow->count())
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Tipo</th>
                                            <th>Estado</th>
                                            <th class="text-end">Valor</th>
                                            <th class="text-muted small">Descripción</th>
                                            <th class="text-end" style="width:170px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($activeAllow as $al)
                                            <tr>
                                                <td class="fw-semibold">{{ strtoupper((string) $al->allowance_type) }}</td>

                                                {{-- ✅ AllowanceStatus --}}
                                                <td>
                                                    <span
                                                        class="badge bg-{{ AllowanceStatus::badge($al->status ?? null) }}">
                                                        {{ AllowanceStatus::label($al->status ?? null) }}
                                                    </span>
                                                </td>

                                                <td class="text-end">{{ $money($al->calculated_amount ?? 0) }}</td>
                                                <td class="text-muted small">{{ $al->description ?? '' }}</td>
                                                <td class="text-end">
                                                    @if (Route::has($routePrefix . '.requests.allowances.destroy') && !$blockedByStatus)
                                                        <form class="d-inline-block" method="POST"
                                                            action="{{ route($routePrefix . '.requests.allowances.destroy', ['travelRequest' => $tr->id, 'allowance' => $al->id]) }}"
                                                            onsubmit="return confirm('¿Eliminar este allowance?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-danger">
                                                                <i class="bi bi-trash"></i> Eliminar
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button class="btn btn-sm btn-outline-secondary" disabled>
                                                            <i class="bi bi-lock"></i> Bloqueado
                                                        </button>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-muted small">No hay Viaticos activos.</div>
                        @endif

                        {{-- Info gasolina desde segmentos --}}
                        @if ($motoHasAny)
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div class="fw-semibold"><i class="bi bi-fuel-pump"></i> Gasolina moto (desde segmentos)
                                </div>
                                <span class="pill bg-light border">Total: <b>{{ $fuelActive }}</b></span>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ✅ NUEVO: BITÁCORA / LOGS (SOLO LO QUE DIJIMOS) --}}
            <div class="col-12">
                <div class="card soft-card shadow-sm">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-clock-history me-1"></i> Bitácora (eventos)
                    </div>
                    <div class="card-body">
                        @if (!$logs || $logs->count() === 0)
                            <div class="text-muted small">Sin eventos registrados.</div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width:170px;">Fecha</th>
                                            <th style="width:170px;">Acción</th>
                                            <th>Descripción</th>
                                            <th style="width:120px;">Usuario</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($logs as $lg)
                                            <tr>
                                                <td class="small text-muted">{{ $safeDT($lg->created_at) }}</td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        {{ $lg->action_label ?? ($lg->action ?? '—') }}
                                                    </span>
                                                </td>

                                                <td class="small">
                                                    {{ $lg->description_label ?? ($lg->description ?? '—') }}
                                                </td>

                                                <td class="small text-muted">#{{ $lg->user_id ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
            {{-- ✅ FIN NUEVO LOGS --}}

        </div>
    </div>

    {{-- MODAL PER DIEM --}}
    <div class="modal fade" id="perDiemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cash-coin me-1"></i> Viático (Por Dias)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form method="POST"
                    action="{{ route($routePrefix . '.requests.perdiem.liquidate', ['travelRequest' => $tr->id]) }}">
                    @csrf

                    <div class="modal-body">
                        <div class="row g-2">

                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Aplica a</label>
                                <select class="form-select" name="applies_to" required
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                                    <option value="staff">Funcionario</option>
                                </select>
                            </div>

                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Unidades (días)</label>
                                <input type="number" min="1" max="60" class="form-control js-pd-units"
                                    name="units" value="{{ old('units', (int) ($perDiem->units ?? $suggestUnits)) }}"
                                    required {{ $blockedByStatus ? 'disabled' : '' }}>
                                <small class="text-muted">Sugerido: {{ $suggestUnits }} (por fechas).</small>
                            </div>

                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Valor unitario</label>
                                <input type="number" min="0" step="0.01" class="form-control js-pd-unit"
                                    name="unit_amount"
                                    value="{{ old('unit_amount', (float) ($perDiem->unit_amount ?? 0)) }}" required
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Descripción</label>
                                <input type="text" class="form-control" name="description"
                                    value="{{ old('description', $perDiem->description ?? 'Viático') }}" maxlength="255"
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <div class="p-2 rounded border bg-light d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">Total</span>
                                    <b
                                        class="js-pd-total">{{ $money($perDiemActive ? $perDiem->calculated_amount ?? 0 : 0) }}</b>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="pdConfirm"
                                        name="confirm" required {{ $blockedByStatus ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="pdConfirm">Confirmo que el valor del viático es
                                        correcto.</label>
                                </div>
                                <small class="text-muted">Guarda el viático en estado <b>Borrador</b> y recalcula
                                    totales.</small>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" {{ $blockedByStatus ? 'disabled' : '' }}>
                            <i class="bi bi-check2-circle"></i> Guardar
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    {{-- MODAL COMIDAS --}}
    <div class="modal fade" id="mealsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-egg-fried me-1"></i> Alimentacion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form method="POST"
                    action="{{ route($routePrefix . '.requests.allowances.upsert', ['travelRequest' => $tr->id]) }}">
                    @csrf

                    <div class="modal-body">
                        <input type="hidden" name="allowance_type" value="meals">

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Unidades</label>
                                <input type="number" min="1" max="200" class="form-control" name="units"
                                    value="{{ old('units', 1) }}" required {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Valor unitario</label>
                                <input type="number" min="0" step="0.01" class="form-control"
                                    name="unit_amount" value="{{ old('unit_amount', 0) }}" required
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Descripción</label>
                                <input type="text" class="form-control" name="description"
                                    value="{{ old('description', 'Comidas') }}" maxlength="255"
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="mealsConfirm"
                                        name="confirm" required {{ $blockedByStatus ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="mealsConfirm">Confirmo que el valor de
                                        Alimentacion es correcto.</label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" {{ $blockedByStatus ? 'disabled' : '' }}>
                            <i class="bi bi-check2-circle"></i> Guardar Alimentacion
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    {{-- MODAL HOSPEDAJE --}}
    <div class="modal fade" id="lodgingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-house-door me-1"></i> Hospedaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form method="POST"
                    action="{{ route($routePrefix . '.requests.allowances.upsert', ['travelRequest' => $tr->id]) }}">
                    @csrf

                    <div class="modal-body">
                        <input type="hidden" name="allowance_type" value="lodging">

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Noches</label>
                                <input type="number" min="1" max="120" class="form-control" name="units"
                                    value="{{ old('units', 1) }}" required {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Valor por noche</label>
                                <input type="number" min="0" step="0.01" class="form-control"
                                    name="unit_amount" value="{{ old('unit_amount', 0) }}" required
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Descripción</label>
                                <input type="text" class="form-control" name="description"
                                    value="{{ old('description', 'Hospedaje') }}" maxlength="255"
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="lodgingConfirm"
                                        name="confirm" required {{ $blockedByStatus ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="lodgingConfirm">Confirmo que el valor de
                                        hospedaje es correcto.</label>
                                </div>
                            </div>

                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" {{ $blockedByStatus ? 'disabled' : '' }}>
                            <i class="bi bi-check2-circle"></i> Guardar hospedaje
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    {{-- MODAL GASOLINA --}}
    <div class="modal fade" id="fuelModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-fuel-pump me-1"></i> Gasolina (Moto)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form method="POST"
                    action="{{ route($routePrefix . '.requests.allowances.upsert', ['travelRequest' => $tr->id]) }}">
                    @csrf

                    <div class="modal-body">
                        <input type="hidden" name="allowance_type" value="fuel">

                        <div class="alert alert-light border small mb-2">
                            Habilitado porque hay al menos un segmento con <b>Moto</b>.
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Unidades</label>
                                <input type="number" min="1" max="200" class="form-control" name="units"
                                    value="{{ old('units', (int) ($fuelAllowance->units ?? 1)) }}" required
                                    {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-6">
                                <label class="form-label small text-muted mb-1">Valor unitario</label>
                                <input type="number" min="0" step="0.01" class="form-control"
                                    name="unit_amount"
                                    value="{{ old('unit_amount', (float) ($fuelAllowance->unit_amount ?? ($rateSuggested ?? 0))) }}"
                                    required {{ $blockedByStatus ? 'disabled' : '' }}>
                                <small class="text-muted">Sugerido: {{ $money($rateSuggested ?? 0) }}</small>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted mb-1">Descripción</label>
                                <input type="text" class="form-control" name="description"
                                    value="{{ old('description', $fuelAllowance->description ?? 'Gasolina moto') }}"
                                    maxlength="255" {{ $blockedByStatus ? 'disabled' : '' }}>
                            </div>

                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" value="1" id="fuelConfirm"
                                        name="confirm" required {{ $blockedByStatus ? 'disabled' : '' }}>
                                    <label class="form-check-label" for="fuelConfirm">
                                        Confirmo que el valor de gasolina es correcto.
                                    </label>
                                </div>
                                <small class="text-muted">Guarda en <b>draft</b> y recalcula totales.</small>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" {{ $blockedByStatus ? 'disabled' : '' }}>
                            <i class="bi bi-check2-circle"></i> Guardar gasolina
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    {{-- MODAL RECHAZAR --}}
    <div class="modal fade" id="rejectAllowancesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-x-circle me-1"></i> Rechazar viáticos (masivo)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>

                <form method="POST"
                    action="{{ route($routePrefix . '.requests.allowances.reject', ['travelRequest' => $tr->id]) }}">
                    @csrf

                    <div class="modal-body">
                        <div class="alert alert-light border small mb-2">
                            Esto marcará como <b>{{ AllowanceStatus::label('rejected') }}</b> los allowances activos
                            (draft/liquidated/approved) y recalculará totales.
                        </div>

                        <label class="form-label small text-muted mb-1">Motivo</label>
                        <textarea name="reason" class="form-control" rows="3" minlength="5" maxlength="500" required>{{ old('reason') }}</textarea>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="include_per_diem" value="1"
                                id="incpd">
                            <label class="form-check-label small" for="incpd">Incluir Por dias</label>
                        </div>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" id="rejConfirm" required>
                            <label class="form-check-label small" for="rejConfirm">Confirmo que quiero rechazar estos
                                viáticos.</label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> Rechazar
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>

    {{-- ✅ MODAL: DOCUMENTOS (GDF manual O SITRAV con SIGAC) --}}
    @if ($canShowDocs)
        <div class="modal fade" id="docsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-file-earmark-text me-1"></i> Documentos · Solicitud
                            #{{ $titleId }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body" id="docsModalBody">
                        <div class="text-muted small">Cargando…</div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>

                </div>
            </div>
        </div>
    @endif

    <script>
        (function() {
            // Mostrar selector de moto y bloquear costo si eligen "moto"
            document.querySelectorAll('form.js-seg-form-mini').forEach((form) => {
                const transport = form.querySelector('.js-transport');
                const motoBox = form.querySelector('.js-moto-box');
                const cost = form.querySelector('.js-oneway');
                const canMoto = String(form.dataset.canMoto || '0') === '1';

                const applyMoto = () => {
                    if (!transport) return;

                    const motoOption = transport.querySelector('option[value="moto"]');
                    const motoDisabled = motoOption ? motoOption.disabled === true : (!canMoto);

                    // ✅ FIX: si quedó en moto pero moto está deshabilitado, lo regresa a terrestre
                    if (transport.value === 'moto' && motoDisabled) {
                        transport.value = 'terrestre';
                    }

                    const isMoto = transport.value === 'moto';

                    if (motoBox) motoBox.classList.toggle('d-none', !isMoto);

                    if (cost) {
                        if (isMoto) {
                            cost.value = 0;
                            cost.setAttribute('disabled', 'disabled');
                        } else {
                            cost.removeAttribute('disabled');
                        }
                    }
                };

                transport?.addEventListener('change', applyMoto);
                applyMoto();
            });

            // Per-diem total preview
            const money = (n) => {
                n = Number(n || 0);
                return '$ ' + n.toLocaleString('es-CO', {
                    maximumFractionDigits: 0
                });
            };

            const units = document.querySelector('.js-pd-units');
            const unit = document.querySelector('.js-pd-unit');
            const out = document.querySelector('.js-pd-total');

            const calc = () => {
                if (!units || !unit || !out) return;
                out.textContent = money(Number(units.value || 0) * Number(unit.value || 0));
            };
            units?.addEventListener('input', calc);
            unit?.addEventListener('input', calc);
            calc();

            // Auto abrir modales: hacerlo por "tipo"
            try {
                @if ($errors->has('applies_to') || ($errors->has('units') && is_null(old('allowance_type'))))
                    const el = document.getElementById('perDiemModal');
                    if (el && window.bootstrap) {
                        (new bootstrap.Modal(el)).show();
                    }
                @endif
            } catch (e) {}

            try {
                @if (old('allowance_type') === 'meals' &&
                        ($errors->has('units') || $errors->has('unit_amount') || $errors->has('confirm')))
                    const el = document.getElementById('mealsModal');
                    if (el && window.bootstrap) {
                        (new bootstrap.Modal(el)).show();
                    }
                @endif
            } catch (e) {}

            try {
                @if (old('allowance_type') === 'lodging' &&
                        ($errors->has('units') || $errors->has('unit_amount') || $errors->has('confirm')))
                    const el = document.getElementById('lodgingModal');
                    if (el && window.bootstrap) {
                        (new bootstrap.Modal(el)).show();
                    }
                @endif
            } catch (e) {}

            try {
                @if (old('allowance_type') === 'fuel' &&
                        ($errors->has('units') || $errors->has('unit_amount') || $errors->has('confirm')))
                    const el = document.getElementById('fuelModal');
                    if (el && window.bootstrap) {
                        (new bootstrap.Modal(el)).show();
                    }
                @endif
            } catch (e) {}

            try {
                @if ($errors->has('reason'))
                    const el = document.getElementById('rejectAllowancesModal');
                    if (el && window.bootstrap) {
                        (new bootstrap.Modal(el)).show();
                    }
                @endif
            } catch (e) {}

            // ✅ Documentos: cargar por AJAX (si existe modal)
            const modalEl = document.getElementById('docsModal');
            const bodyEl = document.getElementById('docsModalBody');

            if (!modalEl || !bodyEl) return;

            // IMPORTANTE: esta ruta debe existir para support (campesena/academic)
            const url = @json(route($routePrefix . '.requests.documents.index', ['travelRequest' => $tr->id]));

            modalEl.addEventListener('show.bs.modal', async function() {
                bodyEl.innerHTML = `
      <div class="d-flex align-items-center gap-2 text-muted">
        <div class="spinner-border spinner-border-sm" role="status"></div>
        <span>Cargando documentos…</span>
      </div>
    `;

                try {
                    const res = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')
                                ?.content || ''
                        },
                        credentials: 'same-origin'
                    });

                    const text = await res.text();

                    if (!res.ok) {
                        bodyEl.innerHTML = `
          <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Error al cargar documentos</div>
            <div class="small">HTTP <b>${res.status}</b> - ${res.statusText}</div>
          </div>
          <details class="small mt-2">
            <summary>Ver respuesta</summary>
            <pre class="mb-0 p-2 bg-light border rounded" style="white-space:pre-wrap;max-height:300px;overflow:auto;">${escapeHtml(text).slice(0, 3000)}</pre>
          </details>
        `;
                        return;
                    }

                    bodyEl.innerHTML = text;

                } catch (e) {
                    bodyEl.innerHTML = `
        <div class="alert alert-danger">
          <div class="fw-semibold mb-1"><i class="bi bi-wifi-off"></i> Error de conexión</div>
          <div class="small"><code>${escapeHtml(String(e))}</code></div>
        </div>
      `;
                }
            });

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, "&#039;");
            }

        })();
    </script>
@endsection
