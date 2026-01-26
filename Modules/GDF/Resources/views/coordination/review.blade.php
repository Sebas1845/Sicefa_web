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

    // IMPORTANT: con tus rutas
    $routePrefix = $routePrefix ?? $areaKey; // academic | campesena

    $title = $title ?? ($areaKey === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica');

    $q   = $q ?? request('q', '');
    $tab = $tab ?? request('tab', 'gdf'); // gdf|sitrav
    if(!in_array($tab, ['gdf','sitrav'], true)) $tab = 'gdf';

    $requests       = $requests ?? collect();       // GDF
    $sitravRequests = $sitravRequests ?? collect(); // SITRAV

    $gdfCount    = $gdfCount ?? null;
    $sitravCount = $sitravCount ?? null;

    $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

    $isPaginator = function($x){
        return is_object($x) && method_exists($x, 'links') && method_exists($x, 'appends');
    };

    $hasMotosIndex = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.index');
    $hasQueue      = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.queue');
    $hasAssign     = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.assign.create');
    $hasQuota      = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.quota_status');
@endphp

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / {{ $title }}</div>
            <h4 class="mb-0">{{ $title }}</h4>
            <small class="text-muted">Revisión de solicitudes y accesos rápidos a operación de motos.</small>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('gdf.'.$routePrefix.'.people.index') }}" class="btn btn-outline-secondary">Personas</a>
            <a href="{{ route('gdf.'.$routePrefix.'.people.create') }}" class="btn btn-outline-primary">+ Registrar persona</a>

            @if($hasMotosIndex)
                <a href="{{ route($routePrefix.'.motorcycles.index') }}" class="btn btn-outline-primary">Motos</a>
            @endif
            @if($hasQueue)
                <a href="{{ route($routePrefix.'.motorcycles.queue') }}" class="btn btn-outline-primary">Cola Apoyo</a>
            @endif
            @if($hasAssign)
                <a href="{{ route($routePrefix.'.motorcycles.assign.create', ['area'=>$areaKey,'year'=>now()->year]) }}" class="btn btn-primary">
                    + Asignación directa
                </a>
            @endif
            @if($hasQuota)
                <a href="{{ route($routePrefix.'.motorcycles.quota_status', ['area'=>$areaKey,'year'=>now()->year]) }}" class="btn btn-outline-warning">
                    Cupo del área
                </a>
            @endif
        </div>
    </div>

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

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $tab==='gdf' ? 'active' : '' }}"
               href="{{ route('gdf.'.$routePrefix.'.review', ['tab'=>'gdf','q'=>$q]) }}">
                GDF @if($gdfCount !== null)<span class="badge bg-secondary ms-1">{{ $gdfCount }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $tab==='sitrav' ? 'active' : '' }}"
               href="{{ route('gdf.'.$routePrefix.'.review', ['tab'=>'sitrav','q'=>$q]) }}">
                SITRAV @if($sitravCount !== null)<span class="badge bg-secondary ms-1">{{ $sitravCount }}</span>@endif
            </a>
        </li>
    </ul>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('gdf.'.$routePrefix.'.review') }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Buscar</label>
                        <input name="q" value="{{ $q }}" class="form-control"
                               placeholder="Origen, destino, ID, cédula, etc.">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary">Filtrar</button>
                    </div>
                    <div class="col-md-4 text-muted small">
                        Tab: <span class="fw-semibold">{{ strtoupper($tab) }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="fw-bold">
                @if($tab==='gdf') Solicitudes GDF
                @else Solicitudes SITRAV
                @endif
            </div>
            <div class="text-muted small">Área: {{ $areaKey }}</div>
        </div>

        <div class="table-responsive">
            @if($tab==='gdf')
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px">ID</th>
                            <th>Ruta</th>
                            <th style="width:220px">Fechas</th>
                            <th style="width:160px" class="text-end">Total</th>
                            <th style="width:360px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($requests as $r)
                        <tr>
                            <td class="fw-semibold">#{{ $r->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $r->origin ?? '—' }} → {{ $r->destination ?? '—' }}</div>
                                <small class="text-muted">
                                    {{ $r->request_type ?? '—' }}
                                    @if(isset($r->budget_item_id)) · Rubro #{{ $r->budget_item_id }} @endif
                                </small>
                            </td>
                            <td><div class="small">{{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}</div></td>
                            <td class="text-end fw-semibold">{{ $fmtMoney($r->total_amount ?? $r->amount ?? 0) }}</td>
                            <td>
                                <form method="POST" action="{{ route('gdf.'.$routePrefix.'.review.approve', $r->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-success btn-sm">Aprobar</button>
                                </form>

                                <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#ret{{ $r->id }}">
                                    Devolver
                                </button>

                                <button class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#rej{{ $r->id }}">
                                    Rechazar
                                </button>

                                <div class="collapse mt-2" id="ret{{ $r->id }}">
                                    <form method="POST" action="{{ route('gdf.'.$routePrefix.'.review.return', $r->id) }}">
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

                                <div class="collapse mt-2" id="rej{{ $r->id }}">
                                    <form method="POST" action="{{ route('gdf.'.$routePrefix.'.review.reject', $r->id) }}">
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
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay solicitudes.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @else
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px">ID</th>
                            <th>Detalle</th>
                            <th style="width:220px">Fechas</th>
                            <th style="width:160px" class="text-end">Total</th>
                            <th style="width:360px">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($sitravRequests as $r)
                        <tr>
                            <td class="fw-semibold">#{{ $r->id }}</td>
                            <td>
                                <div class="fw-semibold">{{ $r->origin ?? '—' }} → {{ $r->destination ?? '—' }}</div>
                                <small class="text-muted">
                                    {{ $r->request_type ?? 'SITRAV' }}
                                    @if(isset($r->budget_item_id)) · Rubro #{{ $r->budget_item_id }} @endif
                                </small>
                            </td>
                            <td><div class="small">{{ $r->start_date ?? '—' }} → {{ $r->end_date ?? '—' }}</div></td>
                            <td class="text-end fw-semibold">{{ $fmtMoney($r->total_amount ?? $r->amount ?? 0) }}</td>
                            <td>
                                <form method="POST" action="{{ route('gdf.'.$routePrefix.'.review.approve', $r->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-success btn-sm">Aprobar</button>
                                </form>

                                <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#rets{{ $r->id }}">
                                    Devolver
                                </button>

                                <button class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#rejs{{ $r->id }}">
                                    Rechazar
                                </button>

                                <div class="collapse mt-2" id="rets{{ $r->id }}">
                                    <form method="POST" action="{{ route('gdf.'.$routePrefix.'.review.return', $r->id) }}">
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

                                <div class="collapse mt-2" id="rejs{{ $r->id }}">
                                    <form method="POST" action="{{ route('gdf.'.$routePrefix.'.review.reject', $r->id) }}">
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
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay solicitudes.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            @endif
        </div>

        <div class="card-footer bg-white">
            @if($tab==='gdf')
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
