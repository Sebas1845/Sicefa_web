@extends('gdf::layouts.masteruser')
@section('title', 'GDF | ' . ($title ?? 'Coordinación') . ' - Revisión')

@section('content')
@php
    $areaKey = $areaKey ?? (str_contains(request()->path(), 'gdf/campesena') ? 'campesena' : 'academic');

    $isOk = function_exists('checkRol')
        ? ($areaKey === 'campesena'
            ? (checkRol('gdf.campesena_coordinator') || checkRol('gdf.campesena_support'))
            : (checkRol('gdf.academic_coordinator') || checkRol('gdf.academic_support')))
        : false;
    if(!$isOk){ abort(403); }

    $routePrefix = $routePrefix ?? $areaKey; // academic|campesena
    $title = $title ?? ($areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica');

    $q   = $q ?? request('q', '');
    $tab = $tab ?? request('tab', 'gdf');
    if(!in_array($tab, ['gdf','sitrav'], true)) $tab = 'gdf';

    $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

    $gdfCount    = $gdfCount ?? null;
    $sitravCount = $sitravCount ?? null;

    $requests       = $requests ?? collect();
    $sitravRequests = $sitravRequests ?? collect();

    $isPaginator = fn($x) => is_object($x) && method_exists($x, 'links') && method_exists($x, 'appends');

    $hasAssign = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.assign.create');
    $hasQuota  = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.quota_status');

    // Dataset activo (unificamos tabla)
    $rows = $tab === 'gdf' ? $requests : $sitravRequests;
@endphp

<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <div class="text-muted small">GDF / {{ $title }}</div>
            <h4 class="mb-1">{{ $title }}</h4>
            <div class="text-muted small">
                Área: <strong>{{ $areaKey }}</strong> · Revisión de solicitudes (GDF/SITRAV) y accesos operativos.
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route($routePrefix.'.people.index') }}" class="btn btn-outline-secondary">
                Personas asignadas
            </a>
            <a href="{{ route($routePrefix.'.people.create') }}" class="btn btn-outline-primary">
                + Registrar persona
            </a>

            @if($hasAssign)
                <a href="{{ route($routePrefix.'.motorcycles.assign.create', ['area'=>$areaKey, 'year'=>now()->year]) }}"
                   class="btn btn-primary">
                    Gestión de motos
                </a>
            @endif
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

    {{-- Accesos rápidos --}}
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-bold mb-1">Personas</div>
                    <div class="text-muted small mb-3">Administración de personas vinculadas al área.</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.people.index') }}">Ver personas</a>
                        <a class="btn btn-outline-primary" href="{{ route($routePrefix.'.people.create') }}">Registrar</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <div class="fw-bold mb-1">Motos</div>
                    <div class="text-muted small mb-3">Crear/asignar (coord/apoyo) y ver diagnóstico de cupo.</div>
                    <div class="d-flex gap-2 flex-wrap">
                        @if($hasAssign)
                            <a class="btn btn-primary"
                               href="{{ route($routePrefix.'.motorcycles.assign.create', ['area'=>$areaKey, 'year'=>now()->year]) }}">
                                Abrir gestión
                            </a>
                        @else
                            <button class="btn btn-primary" disabled>Ruta no disponible</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tabs + filtro (misma fila) --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3">

                <ul class="nav nav-pills">
                    <li class="nav-item">
                        <a class="nav-link {{ $tab==='gdf' ? 'active' : '' }}"
                           href="{{ route($routePrefix.'.review', ['tab'=>'gdf','q'=>$q]) }}">
                            GDF
                            @if($gdfCount !== null)
                                <span class="badge bg-light text-dark ms-1">{{ $gdfCount }}</span>
                            @endif
                        </a>
                    </li>
                    <li class="nav-item ms-2">
                        <a class="nav-link {{ $tab==='sitrav' ? 'active' : '' }}"
                           href="{{ route($routePrefix.'.review', ['tab'=>'sitrav','q'=>$q]) }}">
                            SITRAV
                            @if($sitravCount !== null)
                                <span class="badge bg-light text-dark ms-1">{{ $sitravCount }}</span>
                            @endif
                        </a>
                    </li>
                </ul>

                <form method="GET" action="{{ route($routePrefix.'.review') }}" class="ms-auto" style="min-width:320px;">
                    <input type="hidden" name="tab" value="{{ $tab }}">
                    <label class="form-label small mb-1">Buscar</label>
                    <div class="input-group">
                        <input name="q" value="{{ $q }}" class="form-control"
                               placeholder="Origen, destino, ID, cédula">
                        <button class="btn btn-primary">Filtrar</button>
                    </div>
                    <div class="form-text">
                        Vista: <strong>{{ strtoupper($tab) }}</strong>
                    </div>
                </form>

            </div>
        </div>
    </div>

    {{-- Tabla unificada --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="fw-bold">
                {{ $tab === 'gdf'
                    ? 'Solicitudes GDF pendientes de Coordinación'
                    : 'Solicitudes SITRAV pendientes de Coordinación' }}
            </div>
            <div class="text-muted small">{{ $title }} · {{ $areaKey }}</div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:90px">ID</th>
                        <th>{{ $tab === 'gdf' ? 'Ruta' : 'Detalle' }}</th>
                        <th style="width:210px">Fechas</th>
                        <th style="width:160px" class="text-end">Total</th>
                        <th style="width:380px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($rows as $r)
                    @php
                        $uid = ($tab === 'gdf' ? 'gdf' : 'sitr') . $r->id;
                    @endphp

                    <tr>
                        <td class="fw-semibold">#{{ $r->id }}</td>

                        <td>
                            <div class="fw-semibold">{{ $r->origin ?? '—' }} → {{ $r->destination ?? '—' }}</div>
                            <small class="text-muted">
                                {{ $r->request_type ?? ($tab === 'sitrav' ? 'SITRAV' : '—') }}
                                @if(isset($r->budget_item_id)) · Rubro #{{ $r->budget_item_id }} @endif
                            </small>
                        </td>

                        <td>
                            <div class="small">{{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}</div>
                        </td>

                        <td class="text-end fw-semibold">
                            {{ $fmtMoney($r->total_amount ?? $r->amount ?? 0) }}
                        </td>

                        <td>
                            <form method="POST" action="{{ route($routePrefix.'.review.approve', $r->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-success btn-sm">Aprobar</button>
                            </form>

                            <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#ret{{ $uid }}">
                                Devolver
                            </button>

                            <button class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#rej{{ $uid }}">
                                Rechazar
                            </button>

                            <div class="collapse mt-2" id="ret{{ $uid }}">
                                <form method="POST" action="{{ route($routePrefix.'.review.return', $r->id) }}">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <select name="target" class="form-select" required>
                                            <option value="support">Apoyo</option>
                                            <option value="applicant">Solicitante</option>
                                        </select>
                                        <input name="comment" class="form-control" placeholder="Motivo" required maxlength="2000">
                                        <button class="btn btn-outline-dark">Enviar</button>
                                    </div>
                                </form>
                            </div>

                            <div class="collapse mt-2" id="rej{{ $uid }}">
                                <form method="POST" action="{{ route($routePrefix.'.review.reject', $r->id) }}">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <input name="comment" class="form-control" placeholder="Motivo" required maxlength="2000">
                                        <button class="btn btn-outline-dark">Rechazar</button>
                                    </div>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            No hay solicitudes {{ strtoupper($tab) }} pendientes.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginación --}}
        <div class="card-footer bg-white">
            @if($tab === 'gdf')
                @if($isPaginator($requests))
                    {{ $requests->appends(request()->query())->links() }}
                @else
                    <span class="text-muted small">Sin paginación.</span>
                @endif
            @else
                @if($isPaginator($sitravRequests))
                    {{ $sitravRequests->appends(request()->query())->links() }}
                @else
                    <span class="text-muted small">Sin paginación.</span>
                @endif
            @endif
        </div>
    </div>

</div>
@endsection
