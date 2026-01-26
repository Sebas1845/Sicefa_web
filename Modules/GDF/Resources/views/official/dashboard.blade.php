@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Instructor / Funcionario')

@section('content')
    @php
        $areaKey = $areaKey ?? ($ctx['area'] ?? 'academic');
        $areaLabel = $areaKey === 'campesena' ? 'Campesena' : 'Académica';

        // Gate (si no lo envías desde controller, cae en valores seguros)
        $canEnter = $gate['can_enter'] ?? true;
        $canCreate = $gate['can_create'] ?? true;
        $gateMsg = $gate['message'] ?? null;

        // SITRAV toggle (opcional desde controller)
        $sitravEnabled = $sitravEnabled ?? false;
    @endphp

    <div class="container py-4">

        {{-- Alerts --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if (session('info'))
            <div class="alert alert-info">{{ session('info') }}</div>
        @endif

        {{-- Gate banner (si está restringido) --}}
        @if (!$canCreate && $gateMsg)
            <div class="alert alert-warning">
                <div class="fw-semibold mb-1">Creación de solicitudes restringida</div>
                <div>{{ $gateMsg }}</div>
            </div>
        @endif

        <div class="gdf-card p-4 mb-4" style="background:rgba(255,255,255,.03);">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="text-white-50 small mb-1">Instructor / Funcionario</div>
                    <h3 class="fw-bold mb-1">Panel de solicitudes</h3>
                    <div class="text-white-50">
                        Área activa: <span class="fw-semibold text-white">{{ $areaLabel }}</span>
                    </div>
                </div>

                {{-- Acciones --}}
                <div class="d-flex gap-2 align-items-center" style="position:relative; z-index:5;">

                    <a class="btn btn-gdf-ghost" href="{{ route('gdf.instructor.requests.index') }}">
                        <i class="bi bi-list-ul"></i> Mis solicitudes
                    </a>

                    {{-- Nueva solicitud (dropdown compatible BS4/BS5) --}}
                    <div class="dropdown">
                        <button class="btn btn-gdf-primary dropdown-toggle" type="button" data-toggle="dropdown"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                            @if (!$canEnter) disabled @endif>
                            <i class="bi bi-plus-circle"></i> Nueva solicitud
                        </button>

                        <div class="dropdown-menu dropdown-menu-end">

                            {{-- GDF --}}
                            @if ($canCreate)
                                <a class="dropdown-item" href="{{ route('gdf.instructor.requests.create') }}">
                                    <i class="bi bi-send me-2"></i> GDF - Desplazamiento / Viáticos
                                </a>
                            @else
                                <span class="dropdown-item disabled" title="{{ $gateMsg ?? 'No disponible' }}">
                                    <i class="bi bi-send me-2"></i> GDF - Desplazamiento / Viáticos
                                    <span class="badge text-bg-warning ms-2">Bloqueado</span>
                                </span>
                            @endif

                            <div class="dropdown-divider"></div>

                            {{-- SITRAV --}}
                            @if ($sitravEnabled)
                                {{-- Ajusta la ruta cuando SITRAV esté listo --}}
                                <a class="dropdown-item" href="{{ route('sitrav.instructor.requests.create') }}">
                                    <i class="bi bi-calendar-check me-2"></i> SITRAV - Programación (SIGAC)
                                </a>
                            @else
                                <span class="dropdown-item disabled" title="SITRAV aún no está habilitado.">
                                    <i class="bi bi-calendar-check me-2"></i> SITRAV - Programación (SIGAC)
                                    <span class="badge text-bg-secondary ms-2">Próximamente</span>
                                </span>
                            @endif

                        </div>

                        <a class="btn btn-gdf-primary" href="{{ route('gdf.instructor.requests.create') }}">
                            PROBAR CREAR
                        </a>

                    </div>

                </div>
            </div>
        </div>

        {{-- Stats --}}
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                    <div class="text-white-50 small">Borrador</div>
                    <div class="fs-3 fw-bold">{{ $stats['draft'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                    <div class="text-white-50 small">En proceso</div>
                    <div class="fs-3 fw-bold">{{ $stats['sent'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                    <div class="text-white-50 small">Aprobadas</div>
                    <div class="fs-3 fw-bold">{{ $stats['approved'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="gdf-card p-3" style="background:rgba(255,255,255,.03);">
                    <div class="text-white-50 small">Rechazadas</div>
                    <div class="fs-3 fw-bold">{{ $stats['rejected'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        {{-- Recent --}}
        <div class="gdf-card p-4" style="background:rgba(255,255,255,.03);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="fw-semibold">Últimas solicitudes</div>
                <a class="text-decoration-none text-white-50" href="{{ route('gdf.instructor.requests.index') }}">
                    Ver todas <i class="bi bi-chevron-right"></i>
                </a>
            </div>

            @if (($recent ?? collect())->isEmpty())
                <div class="text-white-50">Aún no tienes solicitudes en esta área.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-dark table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Origen</th>
                                <th>Destino</th>
                                <th>Estado</th>
                                <th class="text-end">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($recent as $r)
                                <tr>
                                    <td class="fw-semibold">{{ $r->id }}</td>
                                    <td>{{ $r->origin }}</td>
                                    <td>{{ $r->destination }}</td>
                                    <td><span class="badge text-bg-secondary">{{ $r->status }}</span></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-gdf-ghost"
                                            href="{{ route('gdf.instructor.requests.show', $r->id) }}">
                                            Ver <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

    </div>
@endsection
