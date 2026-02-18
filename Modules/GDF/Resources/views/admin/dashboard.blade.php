{{-- Modules/GDF/Resources/views/admin/dashboard.blade.php --}}
@extends('gdf::layouts.masteruser')

@section('title','GDF | Admin')

@section('content')
@php
    $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
    $isTreasury     = function_exists('checkRol') ? checkRol('gdf.treasury') : false;
    $isSuperAdmin   = function_exists('checkRol') ? checkRol('gdf.superadmin') : false;

    if(!$isSubdirection && !$isTreasury && !$isSuperAdmin){
        abort(403);
    }

    // Fallbacks si el controlador aún no envía datos
    $kpis = $kpis ?? [
        'total'     => null,
        'draft'     => null,
        'submitted' => null,
        'approved'  => null,
        'returned'  => null,
        'rejected'  => null,
    ];

    $latestRequests = $latestRequests ?? collect();
    $recentLogs     = $recentLogs ?? collect();

    $roleName = $isSuperAdmin ? 'Super Admin' : ($isSubdirection ? 'Subdirección' : 'Tesorería');

    // ✅ Ruta del módulo de Storage Admin (ajústala si tu nombre de ruta cambia)
    // Sugeridas: admin.storage.index o gdf.admin.storage.index
    $storageRouteName = $storageRouteName ?? 'gdf.admin.storage.index';
@endphp

<div class="row g-4">

    {{-- Header --}}
    <div class="col-12">
        <div class="gdf-card p-4">
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-start gap-3">
                    <div class="rounded-3 d-flex align-items-center justify-content-center"
                         style="width:46px;height:46px;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);">
                        <i class="bi bi-shield-check fs-4 text-success"></i>
                    </div>

                    <div>
                        <h3 class="fw-bold mb-1">Panel de Administración</h3>
                        <div class="text-white-50">
                            Gestión de usuarios/roles, auditoría y control operativo del módulo GDF.
                        </div>

                        <div class="mt-2 d-flex flex-wrap gap-2">
                            <span class="badge text-bg-success">
                                <i class="bi bi-person-badge"></i> {{ $roleName }}
                            </span>
                            <span class="badge text-bg-secondary">
                                <i class="bi bi-person-circle"></i> {{ auth()->user()->nickname }}
                            </span>
                            <span class="badge text-bg-dark border border-white border-opacity-10">
                                <i class="bi bi-lock"></i> Acceso restringido
                            </span>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('gdf.admin.users.index') }}" class="btn btn-gdf-primary btn-sm">
                        <i class="bi bi-people"></i> Usuarios y roles
                    </a>

                    {{-- ✅ NUEVO: Liberar espacio (solo SuperAdmin) --}}
                    @if($isSuperAdmin)
                        <a href="{{ route($storageRouteName) }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-trash3"></i> Liberar espacio
                        </a>
                    @endif

                    <a href="#" class="btn btn-gdf-ghost btn-sm">
                        <i class="bi bi-clipboard-data"></i> Auditoría
                    </a>

                    <a href="{{ route('gdf.gateway') }}" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-house"></i> Volver al inicio
                    </a>
                </div>
            </div>

            <hr class="border-white border-opacity-10 my-4">

            {{-- KPIs --}}
            <div class="row g-3">
                @php
                    $kpiCards = [
                        ['key'=>'total',     'title'=>'Solicitudes',        'icon'=>'bi-inboxes',           'hint'=>'Total registradas'],
                        ['key'=>'submitted', 'title'=>'En trámite',         'icon'=>'bi-hourglass-split',   'hint'=>'Pendientes por revisar'],
                        ['key'=>'approved',  'title'=>'Aprobadas',          'icon'=>'bi-check2-circle',     'hint'=>'Listas / aprobadas'],
                        ['key'=>'returned',  'title'=>'Devueltas',          'icon'=>'bi-arrow-return-left', 'hint'=>'Requieren ajuste'],
                        ['key'=>'rejected',  'title'=>'Rechazadas',         'icon'=>'bi-x-circle',          'hint'=>'No aprobadas'],
                        ['key'=>'draft',     'title'=>'Borradores',         'icon'=>'bi-pencil-square',     'hint'=>'Sin enviar'],
                    ];
                @endphp

                @foreach($kpiCards as $c)
                    @php
                        $val = data_get($kpis, $c['key']);
                        $isEmpty = is_null($val);
                    @endphp
                    <div class="col-6 col-lg-2">
                        <div class="gdf-card p-3 h-100" style="background:rgba(255,255,255,.03);">
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="text-white-50 small">{{ $c['title'] }}</div>
                                <i class="bi {{ $c['icon'] }} text-white-50"></i>
                            </div>
                            <div class="mt-1 fw-bold fs-4">
                                {{ $isEmpty ? '—' : $val }}
                            </div>
                            <div class="text-white-50 small">
                                {{ $c['hint'] }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Acciones rápidas --}}
            <div class="row g-3 mt-2">
                <div class="col-md-6">
                    <div class="gdf-card p-4 h-100" style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.20);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-lightning-charge text-success fs-4"></i>
                            <h5 class="mb-0 fw-semibold">Acciones rápidas</h5>
                        </div>
                        <div class="text-white-50 mb-3">
                            Atajos para tareas frecuentes del panel.
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('gdf.admin.users.index') }}" class="btn btn-success btn-sm">
                                <i class="bi bi-person-plus"></i> Asignar roles
                            </a>

                            <a href="{{ route('gdf.gateway') }}" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-arrow-repeat"></i> Cambiar contexto (op.)
                            </a>

                            {{-- ✅ NUEVO: Storage Admin --}}
                            @if($isSuperAdmin)
                                <a href="{{ route($storageRouteName) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-hdd-stack"></i> Storage / Liberar espacio
                                </a>
                            @endif

                            <a href="#" class="btn btn-outline-light btn-sm">
                                <i class="bi bi-download"></i> Exportar reporte
                            </a>
                        </div>

                        <div class="text-white-50 small mt-3">
                            Nota: la opción de “cambiar contexto” es útil solo si estás probando flujos operativos.
                        </div>

                        @if($isSuperAdmin)
                            <div class="text-white-50 small mt-2">
                                <i class="bi bi-exclamation-triangle"></i>
                                Storage Admin elimina archivos físicos en <code class="text-white-50">storage/app/public</code>. Úsalo con cuidado.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="gdf-card p-4 h-100" style="background:rgba(255,255,255,.03);">
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <i class="bi bi-info-circle text-white-50 fs-4"></i>
                            <h5 class="mb-0 fw-semibold">Estado del módulo</h5>
                        </div>

                        <ul class="list-unstyled mb-0 text-white-50 small">
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="bi bi-dot mt-1"></i>
                                <span>Panel preparado para métricas reales (solicitudes, montos, presupuesto).</span>
                            </li>
                            <li class="d-flex align-items-start gap-2 mb-2">
                                <i class="bi bi-dot mt-1"></i>
                                <span>Auditoría lista para consumir <code class="text-white-50">travel_logs</code>.</span>
                            </li>
                            <li class="d-flex align-items-start gap-2">
                                <i class="bi bi-dot mt-1"></i>
                                <span>Gestión de usuarios usa roles del módulo <code class="text-white-50">gdf.*</code>.</span>
                            </li>
                        </ul>

                        {{-- ✅ MINI-CARD opcional: Acceso directo a Storage --}}
                        @if($isSuperAdmin)
                            <hr class="border-white border-opacity-10 my-3">
                            <div class="gdf-card p-3" style="background:rgba(255,193,7,.08);border:1px solid rgba(255,193,7,.18);">
                                <div class="d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold">
                                            <i class="bi bi-trash3"></i> Liberar espacio (Storage)
                                        </div>
                                        <div class="text-white-50 small">
                                            Revisa carpetas y elimina archivos para liberar almacenamiento.
                                        </div>
                                    </div>
                                    <a href="{{ route($storageRouteName) }}" class="btn btn-warning btn-sm">
                                        Ir
                                    </a>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Últimas solicitudes --}}
    <div class="col-lg-7">
        <div class="gdf-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 fw-semibold">Últimas solicitudes</h5>
                    <div class="text-white-50 small">Vista rápida de los últimos registros</div>
                </div>
                <a href="#" class="btn btn-gdf-ghost btn-sm">
                    <i class="bi bi-list-check"></i> Ver todo
                </a>
            </div>

            <hr class="border-white border-opacity-10 my-3">

            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Área</th>
                            <th>Destino</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($latestRequests as $r)
                            @php
                                $status = $r->status ?? '—';
                                $badge = match($status) {
                                    'approved'  => 'text-bg-success',
                                    'submitted' => 'text-bg-warning',
                                    'returned'  => 'text-bg-info',
                                    'rejected'  => 'text-bg-danger',
                                    'draft'     => 'text-bg-secondary',
                                    default     => 'text-bg-dark',
                                };
                            @endphp
                            <tr>
                                <td class="fw-semibold">#{{ $r->id }}</td>
                                <td class="text-white-50">{{ optional($r->area)->name ?? '—' }}</td>
                                <td>{{ $r->destination ?? '—' }}</td>
                                <td class="text-white-50">
                                    {{ optional($r->start_date)->format('Y-m-d') ?? '—' }}
                                    →
                                    {{ optional($r->end_date)->format('Y-m-d') ?? '—' }}
                                </td>
                                <td><span class="badge {{ $badge }}">{{ $status }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-white-50 py-4">
                                    Aún no hay datos para mostrar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    {{-- Auditoría reciente --}}
    <div class="col-lg-5">
        <div class="gdf-card p-4 h-100">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1 fw-semibold">Auditoría reciente</h5>
                    <div class="text-white-50 small">Últimas acciones registradas</div>
                </div>
                <a href="#" class="btn btn-gdf-ghost btn-sm">
                    <i class="bi bi-clipboard-data"></i> Ver auditoría
                </a>
            </div>

            <hr class="border-white border-opacity-10 my-3">

            <div class="vstack gap-2">
                @forelse($recentLogs as $log)
                    <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div>
                                <div class="fw-semibold">
                                    {{ $log->action ?? 'Acción' }}
                                </div>
                                <div class="text-white-50 small">
                                    {{ $log->description ?? '—' }}
                                </div>
                                <div class="text-white-50 small mt-1">
                                    {{ optional($log->created_at)->format('Y-m-d H:i') ?? '—' }}
                                </div>
                            </div>
                            <span class="badge text-bg-dark border border-white border-opacity-10">
                                #{{ $log->travel_request_id ?? '—' }}
                            </span>
                        </div>
                    </div>
                @empty
                    <div class="text-white-50 small">
                        Aún no hay logs. Cuando empieces a registrar acciones en <code class="text-white-50">travel_logs</code>,
                        aquí aparecerán automáticamente.
                    </div>
                @endforelse
            </div>

        </div>
    </div>

</div>
@endsection
