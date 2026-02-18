{{-- Modules/GDF/Resources/views/support/motorcycles/queue.blade.php --}}
@extends('gdf::layouts.masteruser')

@php
    $areaKey = $areaKey ?? 'academic';
    $routePrefix = $routePrefix ?? ($areaKey === 'campesena' ? 'gdf.support.campesena' : 'gdf.support.academic');

    $items = $items ?? collect();
    $areas = $areas ?? collect();
    $people = $people ?? collect(); // ✅ precargadas desde el controller (por área)
    $motosAvailable = $motosAvailable ?? collect();
    $motosInventory = $motosInventory ?? collect();

    $year = $year ?? (int) request('year', now()->year);
    $type = $type ?? (string) request('type', 'all');
    $areaId = $areaId ?? (int) request('area_id', 0);

    $quotaTotal = (int) ($quotaTotal ?? 0);
    $quotaUsed = (int) ($quotaUsed ?? 0);
    $quotaRemaining = (int) ($quotaRemaining ?? max(0, $quotaTotal - $quotaUsed));

    $quotaExceeded = $quotaTotal > 0 && $quotaUsed > $quotaTotal;
    $overBy = $quotaExceeded ? $quotaUsed - $quotaTotal : 0;

    $availableCount = (int) ($availableCount ?? $motosAvailable->count());
    $inventoryCount = (int) ($inventoryCount ?? (method_exists($motosInventory, 'total') ? $motosInventory->total() : $motosInventory->count()));

    $title = 'Cola de Motos · ' . strtoupper($areaKey);

    $canAssign = $quotaRemaining > 0 && !$quotaExceeded && $motosAvailable->count() > 0 && $quotaTotal > 0;

    $badgeAssign = fn($s) => match (strtolower((string) $s)) {
        'approved' => 'info',
        'delivered' => 'success',
        'returned' => 'secondary',
        'cancelled' => 'dark',
        default => 'secondary',
    };

    $badgeMoto = fn($s) => match (strtolower((string) $s)) {
        'available' => 'success',
        'assigned' => 'warning',
        'delivered' => 'info',
        'maintenance' => 'secondary',
        'inactive' => 'dark',
        default => 'secondary',
    };
@endphp

@section('title', 'GDF | ' . $title)

@push('css')
    {{-- Select2 (si tu layout ya lo trae, igual esto no molesta) --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet"/>

    <style>
        .soft-card{ border-radius:16px }
        .soft-card .card-header{ border-radius:16px 16px 0 0 }
        .kpi{ border-radius:16px }
        .kpi .label{ font-size:.75rem; opacity:.75 }
        .kpi .value{ font-size:1.25rem; font-weight:800 }
        .wrap{ white-space:normal }
        .mono{ font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }
        .muted-help{ font-size:.8rem; opacity:.75 }

        /* ✅ Fix típico: dropdown detrás por z-index */
        .select2-container--bootstrap-5 .select2-dropdown{ z-index: 2000; }
        .select2-container{ width:100% !important; }
    </style>
@endpush

@section('content')
<div class="container-fluid">

    {{-- HEADER --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">{{ $title }}</h4>
            <div class="text-muted small">
                Control de cupos (vigencia {{ $year }}), inventario (por área) y asignaciones activas
            </div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="{{ route($routePrefix . '.dashboard') }}">
                <i class="bi bi-arrow-left"></i> Volver
            </a>

            @if (\Illuminate\Support\Facades\Route::has($routePrefix . '.motorcycles.return'))
                <a class="btn btn-outline-primary"
                   href="{{ route($routePrefix . '.motorcycles.return', ['year' => $year, 'area_id' => $areaId]) }}">
                    <i class="bi bi-arrow-repeat"></i> Devoluciones
                </a>
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
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Revisa lo siguiente:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
        </div>
    @endif

    {{-- ALERTAS DE CUPO --}}
    @if ($quotaTotal <= 0)
        <div class="alert alert-warning d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle" style="font-size:1.2rem;"></i>
            <div>
                <div class="fw-semibold">Cupo no configurado</div>
                <div class="small">
                    No hay cupo configurado para <b>{{ $year }}</b> (tabla <code>motorcycle_area_quotas</code>).
                    Las asignaciones nuevas quedarán bloqueadas.
                </div>
            </div>
        </div>
    @endif

    @if ($quotaExceeded)
        <div class="alert alert-danger d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-octagon" style="font-size:1.2rem;"></i>
            <div>
                <div class="fw-semibold">Alerta: cupo excedido</div>
                <div class="small">
                    Hay <b>{{ $quotaUsed }}</b> asignaciones activas ({{ $year }}), pero el cupo total es <b>{{ $quotaTotal }}</b>.
                    Exceso: <b>{{ $overBy }}</b>.
                </div>
                <div class="small text-muted mt-1">
                    Las asignaciones nuevas quedan bloqueadas hasta corregir (devoluciones/cierres o ajuste de cupo).
                </div>
            </div>
        </div>
    @endif

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card kpi shadow-sm soft-card">
                <div class="card-body">
                    <div class="label">Cupo total ({{ $year }})</div>
                    <div class="value">{{ $quotaTotal }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi shadow-sm soft-card">
                <div class="card-body">
                    <div class="label">Cupo usado (activas, {{ $year }})</div>
                    <div class="value">{{ $quotaUsed }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi shadow-sm soft-card">
                <div class="card-body">
                    <div class="label">Disponible</div>
                    <div class="value">{{ $quotaRemaining }}</div>
                    @if ($quotaRemaining <= 0)
                        <div class="small text-danger mt-1">
                            <i class="bi bi-exclamation-triangle"></i> Sin cupo disponible
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card kpi shadow-sm soft-card">
                <div class="card-body">
                    <div class="label">Inventario (filtrado) / Available</div>
                    <div class="value">{{ $inventoryCount }} / {{ $availableCount }}</div>
                    <div class="muted-help">Inventario por <code>current_area_id</code>. El cupo limita asignaciones.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="card soft-card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route($routePrefix . '.motorcycles.queue') }}" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Año (vigencia)</label>
                    <input class="form-control" type="number" name="year" value="{{ $year }}">
                </div>

                <div class="col-md-5">
                    <label class="form-label">Área (inventario + cupo)</label>
                    <select class="form-select" name="area_id">
                        <option value="0" @selected((int)$areaId === 0)>Todas</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a->id }}" @selected((int)$areaId === (int)$a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                    <div class="muted-help">Filtra: cupo / asignaciones / inventario (current_area_id).</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tipo (cola)</label>
                    <select class="form-select" name="type">
                        <option value="all" @selected($type === 'all')>Todas</option>
                        <option value="direct" @selected($type === 'direct')>Directas</option>
                        <option value="request" @selected($type === 'request')>Por solicitud</option>
                    </select>
                </div>

                <div class="col-12 d-flex gap-2 mt-2">
                    <button class="btn btn-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a class="btn btn-outline-secondary" href="{{ route($routePrefix . '.motorcycles.queue') }}">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    {{-- CREAR MOTO (inventario) --}}
    <div class="card soft-card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-bicycle"></i> Crear moto (inventario)
            <span class="text-muted fw-normal ms-2 small">Se liga a <code>motorcycles.current_area_id</code>.</span>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route($routePrefix . '.motorcycles.store') }}" class="row g-2">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">

                <div class="col-md-2">
                    <label class="form-label">Placa *</label>
                    <input class="form-control" name="plate" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Marca</label>
                    <input class="form-control" name="brand">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Modelo</label>
                    <input class="form-control" name="model">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Ingreso</label>
                    <input class="form-control" type="date" name="entry_date">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Área inventario *</label>
                    <select class="form-select" name="current_area_id" required>
                        <option value="">Selecciona...</option>
                        @foreach ($areas as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Kilometraje</label>
                    <input class="form-control" type="number" min="0" name="current_odometer" value="0">
                </div>

                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="status">
                        <option value="available">Disponible</option>
                        <option value="maintenance">Mantenimiento</option>
                        <option value="inactive">Inactiva</option>
                    </select>
                </div>

                <div class="col-md-9">
                    <label class="form-label">Notas</label>
                    <input class="form-control" name="notes">
                </div>

                <div class="col-12 d-grid">
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-check2-circle"></i> Guardar moto
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- INVENTARIO --}}
    <div class="card soft-card shadow-sm mb-4">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <div><i class="bi bi-list-ul"></i> Inventario de motos</div>
            <small class="text-muted">Dar de baja / activar (si NO está assigned/delivered)</small>
        </div>
        <div class="card-body p-0">
            @php
                $invEmpty =
                    (method_exists($motosInventory, 'isEmpty') && $motosInventory->isEmpty()) ||
                    (!method_exists($motosInventory, 'isEmpty') && $motosInventory->count() == 0);
            @endphp

            @if ($invEmpty)
                <div class="p-3 text-muted">No hay motos en inventario con los filtros actuales.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th style="min-width:90px;">ID</th>
                            <th style="min-width:140px;">Placa</th>
                            <th style="min-width:160px;">Marca</th>
                            <th style="min-width:180px;">Modelo</th>
                            <th style="min-width:160px;">Área inventario</th>
                            <th style="min-width:140px;">Km</th>
                            <th style="min-width:140px;">Estado</th>
                            <th style="min-width:340px;">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($motosInventory as $m)
                            @php
                                $st = strtolower((string) ($m->status ?? ''));
                                $isInactive = $st === 'inactive';
                                $isBusy = in_array($st, ['assigned', 'delivered'], true);
                                $areaName = $m->area_name ?? ($m->area->name ?? null);
                            @endphp
                            <tr>
                                <td class="mono">#{{ $m->id }}</td>
                                <td class="fw-semibold">{{ $m->plate ?? 'Moto #' . $m->id }}</td>
                                <td class="wrap">{{ $m->brand ?? '—' }}</td>
                                <td class="wrap">{{ $m->model ?? '—' }}</td>
                                <td class="wrap">{{ $areaName ?? 'Area #' . ($m->current_area_id ?? '—') }}</td>
                                <td class="mono">{{ (int) ($m->current_odometer ?? 0) }}</td>
                                <td>
                                    <span class="badge bg-{{ $badgeMoto($m->status ?? '') }}">{{ $m->status ?? 'N/D' }}</span>
                                    @if ($isBusy)
                                        <div class="small text-muted mt-1">Ocupada: no se puede cambiar estado aquí.</div>
                                    @endif
                                </td>
                                <td>
                                    <form method="POST"
                                          action="{{ route($routePrefix . '.motorcycles.updateStatus', ['motorcycle' => $m->id]) }}"
                                          class="d-flex flex-wrap gap-2 align-items-center">
                                        @csrf
                                        @method('PATCH')

                                        @if ($isInactive)
                                            <input type="hidden" name="status" value="available">
                                            <button class="btn btn-sm btn-outline-success" @disabled($isBusy)>
                                                <i class="bi bi-check2-circle"></i> Activar (Disponible)
                                            </button>
                                        @else
                                            <input type="hidden" name="status" value="inactive">
                                            <button class="btn btn-sm btn-outline-danger" @disabled($isBusy)>
                                                <i class="bi bi-x-circle"></i> Dar de baja
                                            </button>
                                        @endif

                                        <small class="text-muted">
                                            @if ($isBusy) Bloqueado (assigned/delivered). @else Cambia estado de inventario. @endif
                                        </small>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if (method_exists($motosInventory, 'links'))
                    <div class="p-3">{{ $motosInventory->links() }}</div>
                @endif
            @endif
        </div>
    </div>

    {{-- ASIGNACIÓN DIRECTA --}}
    <div class="card soft-card shadow-sm mb-4">
        <div class="card-header fw-semibold">
            <i class="bi bi-plus-circle"></i> Asignación directa
            <span class="text-muted fw-normal ms-2 small">Vigencia: <b>{{ $year }}</b></span>
        </div>
        <div class="card-body">

            @if ($quotaExceeded)
                <div class="alert alert-danger"><i class="bi bi-exclamation-octagon"></i> Cupo excedido (Exceso: <b>{{ $overBy }}</b>).</div>
            @elseif($quotaTotal <= 0)
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Cupo no configurado.</div>
            @elseif($quotaRemaining <= 0)
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No hay cupo disponible.</div>
            @elseif($motosAvailable->count() === 0)
                <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> No hay motos <b>available</b> para el filtro.</div>
            @endif

            <form method="POST" action="{{ route($routePrefix . '.motorcycles.storeDirect') }}" class="row g-2">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">

                <div class="col-md-4">
                    <label class="form-label">Persona (del área)</label>

                    {{-- ✅ precargado, sin AJAX --}}
                    <select id="person_id" name="person_id" class="form-select" required @disabled(!$canAssign)>
                        <option value="">Selecciona...</option>
                        @foreach($people as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}</option>
                        @endforeach
                    </select>

                    <div class="muted-help">
                        Lista precargada (personas activas por área). Si no ves a alguien, revisa su asignación activa.
                    </div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Área (consume cupo)</label>
                    <select id="area_id" class="form-select" name="area_id" required @disabled(!$canAssign)>
                        @foreach ($areas as $a)
                            <option value="{{ $a->id }}">{{ $a->name }}</option>
                        @endforeach
                    </select>
                    <div class="muted-help">Debe existir cupo para esta área (<code>motorcycle_area_quotas</code>) en {{ $year }}.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Moto disponible (inventario)</label>
                    <select class="form-select" name="motorcycle_id" required @disabled(!$canAssign)>
                        <option value="">Selecciona...</option>
                        @foreach ($motosAvailable as $mm)
                            <option value="{{ $mm->id }}">{{ $mm->plate ?? 'Moto #' . $mm->id }} — Área #{{ $mm->current_area_id ?? '—' }}</option>
                        @endforeach
                    </select>
                    <div class="muted-help">Filtrada por <code>current_area_id</code> + <code>status=available</code>.</div>
                </div>

                <div class="col-12 d-grid mt-2">
                    <button class="btn btn-success" @disabled(!$canAssign)>
                        <i class="bi bi-check2-circle"></i> Crear asignación
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ASIGNACIONES ACTIVAS --}}
    <div class="card soft-card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <div><i class="bi bi-list-check"></i> Asignaciones activas (vigencia {{ $year }})</div>
            <small class="text-muted">Puedes devolver directamente si está en <code>delivered</code></small>
        </div>

        <div class="card-body p-0">
            @if ($items->isEmpty())
                <div class="p-3 text-muted">No hay asignaciones con los filtros actuales.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Persona</th>
                            <th>Área</th>
                            <th>Moto</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th style="min-width:320px;">Acción</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($items as $it)
                            @php $isDelivered = strtolower((string)$it->status) === 'delivered'; @endphp
                            <tr>
                                <td>#{{ $it->id }}</td>
                                <td class="wrap">{{ optional($it->person)->fullname ?? 'Person #' . $it->person_id }}</td>
                                <td class="wrap">{{ optional($it->area)->name ?? 'Area #' . $it->area_id }}</td>
                                <td class="wrap">{{ optional($it->motorcycle)->plate ?? 'Moto #' . $it->motorcycle_id }}</td>
                                <td>
                                    @if (empty($it->travel_requestable_id))
                                        <span class="badge bg-secondary">Directa</span>
                                    @else
                                        <span class="badge bg-primary">Solicitud</span>
                                    @endif
                                </td>
                                <td><span class="badge bg-{{ $badgeAssign($it->status) }}">{{ $it->status }}</span></td>
                                <td class="text-muted small">Solo disponible cuando esté en <code>delivered</code>.</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="p-3">{{ $items->links() }}</div>
            @endif
        </div>
    </div>

</div>
@endsection

@push('js')
    {{-- jQuery + Select2 --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        $(function () {
            $('#person_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Selecciona...',
                allowClear: true,
                dropdownParent: $('body') // ✅ evita problemas de z-index dentro de cards
            });
        });
    </script>
@endpush
