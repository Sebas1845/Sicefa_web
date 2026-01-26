{{-- Modules/GDF/Resources/views/subdirection/motorcycles/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Inventario de motos')

@section('content')
@php
    $isSubdirection = function_exists('checkRol') ? (checkRol('gdf.subdirection') || checkRol('gdf.superadmin')) : false;
    if(!$isSubdirection){ abort(403); }

    $q = $q ?? request('q');
    $areaId = $areaId ?? request('area_id');
    $status = $status ?? request('status');
    $year = $year ?? now()->year;

    $quotaMeta = $quotaMeta ?? []; // [area_id => ['has_quota'=>bool,'quota_total'=>int,'used'=>int,'available'=>int]]
@endphp

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Inventario de motos</h4>
            <div class="text-muted small">
                Crear motos, ver inventario y transferir entre áreas. Cupos vigentes del año: <strong>{{ $year }}</strong>.
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('gdf.subdirection.dashboard') }}" class="btn btn-outline-secondary">Volver</a>

            <a href="{{ route('gdf.subdirection.motorcycles.quotas.index') }}" class="btn btn-outline-warning">
                Cupos / Distribución
            </a>

            <a href="{{ route('gdf.subdirection.motorcycles.create') }}" class="btn btn-primary">
                + Nueva moto
            </a>
        </div>
    </div>

    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    {{-- KPIs --}}
    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total</div>
                    <div class="fs-3 fw-bold">{{ $stats['total'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Disponibles</div>
                    <div class="fs-3 fw-bold text-success">{{ $stats['available'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Asignadas</div>
                    <div class="fs-3 fw-bold text-warning">{{ $stats['assigned'] ?? 0 }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Mantenimiento / Retiradas</div>
                    <div class="fs-5 fw-bold">{{ $stats['maintenance'] ?? 0 }} / {{ $stats['retired'] ?? 0 }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Placa, marca, modelo">
                </div>

                <div class="col-md-4">
                    <label class="form-label mb-1">Área</label>
                    <select name="area_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($areas as $a)
                            @php
                                $meta = $quotaMeta[$a->id] ?? null;
                                $suffix = $meta
                                    ? ($meta['has_quota'] ? " (Cupo {$meta['quota_total']}, En área {$meta['used']}, Disp {$meta['available']})" : " (SIN CUPO {$year})")
                                    : "";
                            @endphp
                            <option value="{{ $a->id }}" @selected((string)$areaId === (string)$a->id)>{{ $a->name }}{{ $suffix }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label mb-1">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        @foreach(['available'=>'available','assigned'=>'assigned','maintenance'=>'maintenance','retired'=>'retired'] as $k=>$v)
                            <option value="{{ $k }}" @selected((string)$status === (string)$k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-12 d-flex gap-2 mt-2">
                    <button class="btn btn-primary">Filtrar</button>
                    <a href="{{ route('gdf.subdirection.motorcycles.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="fw-bold">Listado</div>
            <div class="text-muted small">Transferencias solo si no está “assigned”.</div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Placa</th>
                        <th>Marca / Modelo</th>
                        <th>Área actual</th>
                        <th>Estado</th>
                        <th class="text-end">Odómetro</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($motorcycles as $m)
                    <tr>
                        <td class="fw-semibold">{{ $m->plate }}</td>
                        <td>
                            <div class="fw-semibold">{{ strtoupper($m->brand ?? '—') }}</div>
                            <div class="text-muted small">{{ $m->model ?? '—' }}</div>
                        </td>
                        <td>{{ $m->currentArea->name ?? '—' }}</td>
                        <td>
                            <span class="badge text-bg-secondary">{{ $m->status }}</span>
                        </td>
                        <td class="text-end">{{ number_format((int)($m->current_odometer ?? 0), 0, ',', '.') }}</td>
                        <td class="text-end">
                            <button class="btn btn-outline-primary btn-sm"
                                    data-bs-toggle="modal"
                                    data-bs-target="#transferModal{{ $m->id }}"
                                    {{ $m->status === 'assigned' ? 'disabled' : '' }}>
                                Transferir
                            </button>
                        </td>
                    </tr>

                    @include('gdf::subdirection.motorcycles.partials.transfer_modal', [
                        'motorcycle' => $m,
                        'areas' => $areas,
                        'year' => $year,
                        'quotaMeta' => $quotaMeta
                    ])

                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">Sin resultados.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white">
            {{ $motorcycles->links('pagination::bootstrap-4') }}
        </div>
    </div>
</div>
@endsection
