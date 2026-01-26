@extends('gdf::layouts.master')

@section('title', 'GDF | Inicio')

@section('content')

    @php
        $hasCheckRol = function_exists('checkRol');

        $isInstructor = $hasCheckRol ? checkRol('gdf.instructor') : false;

        $isAcademicCoord = $hasCheckRol ? checkRol('gdf.academic_coordinator') : false;
        $isAcademicSupport = $hasCheckRol ? checkRol('gdf.academic_support') : false;

        $isCampesenaCoord = $hasCheckRol ? checkRol('gdf.campesena_coordinator') : false;
        $isCampesenaSupport = $hasCheckRol ? checkRol('gdf.campesena_support') : false;

        $isSubdirection = $hasCheckRol ? checkRol('gdf.subdirection') : false;
        $isTreasury = $hasCheckRol ? checkRol('gdf.treasury') : false;
        $isAdmin = $hasCheckRol ? checkRol('gdf.admin') : false;

        $isCoordOrSupport = $isAcademicCoord || $isAcademicSupport || $isCampesenaCoord || $isCampesenaSupport;

        $primaryAction = null;

        if ($isInstructor || $isCoordOrSupport) {
            $primaryAction = [
                'route' => route('gdf.gateway'),
                'label' => 'Elegir área / contexto',
                'icon' => 'bi-grid-1x2',
            ];
        } elseif ($isSubdirection) {
            $primaryAction = [
                'route' => route('gdf.subdirection.dashboard'),
                'label' => 'Ir a Subdirección',
                'icon' => 'bi-shield-lock',
            ];
        } elseif ($isTreasury) {
            $primaryAction = [
                'route' => route('gdf.treasury.dashboard'),
                'label' => 'Ir a Tesorería',
                'icon' => 'bi-wallet2',
            ];
        } elseif ($isAdmin) {
            $primaryAction = [
                'route' => route('gdf.admin.dashboard'),
                'label' => 'Ir a Admin',
                'icon' => 'bi-shield-check',
            ];
        }
    @endphp

    {{-- Sección: Qué es GDF --}}
    <div class="row g-4 align-items-stretch">
        <div class="col-lg-7">
            <div class="gdf-card p-4 h-100">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="gdf-chip"><i class="bi bi-compass"></i> Propósito</span>
                    <span class="gdf-chip"><i class="bi bi-shield-check"></i> Trazabilidad</span>
                </div>

                <h3 class="fw-bold mb-2">¿Qué es GDF?</h3>
                <p class="text-white-50 mb-0">
                    GDF centraliza el registro y seguimiento de traslados, validaciones por roles,
                    soportes documentales y control presupuestal por rubro/área.
                </p>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="gdf-card p-4 h-100">
                <h5 class="fw-semibold mb-3">¿Qué puedes hacer aquí?</h5>

                <div class="d-grid gap-2">
                    <div class="gdf-mini w-100"><i class="bi bi-receipt"></i> Crear y gestionar solicitudes</div>
                    <div class="gdf-mini w-100"><i class="bi bi-clipboard-check"></i> Revisar / avalar / aprobar</div>
                    <div class="gdf-mini w-100"><i class="bi bi-folder2-open"></i> Cargar soportes y evidencias</div>
                    <div class="gdf-mini w-100"><i class="bi bi-graph-up"></i> Generar reportes e historial</div>
                </div>

                <div class="mt-4">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-gdf-primary w-100">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                        </a>
                    @else
                        @if ($primaryAction)
                            <a href="{{ $primaryAction['route'] }}" class="btn btn-gdf-primary w-100">
                                <i class="bi {{ $primaryAction['icon'] }}"></i> {{ $primaryAction['label'] }}
                            </a>
                        @else
                            <div class="alert alert-warning mb-0">
                                Tu usuario no tiene roles GDF asignados aún. Solicita asignación de rol.
                            </div>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </div>

    {{-- Sección: Roles (tarjetas) --}}
    <div class="mt-4">
        <div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
            <div>
                <h4 class="fw-bold mb-1">Roles del proceso</h4>
                <div class="text-white-50">Cada rol ve solo lo necesario según el flujo.</div>
            </div>
            <a href="#info" class="btn btn-gdf-ghost btn-sm">
                <i class="bi bi-info-circle"></i> Ver detalles
            </a>
        </div>

        <div class="row g-3">
            <div class="col-md-6 col-xl-3">
                <div class="gdf-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-person-badge fs-4"></i>
                        <div class="fw-semibold">Instructor / Funcionario</div>
                    </div>
                    <div class="text-white-50 small">
                        Registra solicitudes y adjunta la información requerida para el trámite.
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="gdf-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-person-check fs-4"></i>
                        <div class="fw-semibold">Apoyo</div>
                    </div>
                    <div class="text-white-50 small">
                        Valida datos, soportes y condiciones iniciales para continuar el flujo.
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="gdf-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-wallet2 fs-4"></i>
                        <div class="fw-semibold">Tesorería</div>
                    </div>
                    <div class="text-white-50 small">
                        Gestiona presupuesto/movimientos y asignaciones económicas según reglas.
                    </div>
                </div>
            </div>

            <div class="col-md-6 col-xl-3">
                <div class="gdf-card p-4 h-100">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <i class="bi bi-shield-lock fs-4"></i>
                        <div class="fw-semibold">Subdirección</div>
                    </div>
                    <div class="text-white-50 small">
                        Realiza la decisión final: aprobar, devolver o rechazar solicitudes.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Accesos rápidos (solo si logueado) --}}
    @auth
        <div class="mt-4">
            <div class="gdf-card p-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h5 class="fw-semibold mb-1">Accesos rápidos</h5>
                        <div class="text-white-50 small">Se habilitan según tus roles reales.</div>
                    </div>

                    @if ($isInstructor || $isCoordOrSupport)
                        <a href="{{ route('gdf.gateway') }}" class="btn btn-gdf-primary btn-sm">
                            <i class="bi bi-grid-1x2"></i> Ir al selector
                        </a>
                    @endif
                </div>

                <div class="row g-3 mt-3">
                    @if ($isSubdirection)
                        <div class="col-md-6 col-xl-3">
                            <a href="{{ route('gdf.subdirection.dashboard') }}" class="gdf-quicklink">
                                <i class="bi bi-shield-lock"></i>
                                <div>
                                    <div class="fw-semibold">Subdirección</div>
                                    <div class="small text-white-50">Aprobación final y control</div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if ($isTreasury)
                        <div class="col-md-6 col-xl-3">
                            <a href="{{ route('gdf.treasury.dashboard') }}" class="gdf-quicklink">
                                <i class="bi bi-wallet2"></i>
                                <div>
                                    <div class="fw-semibold">Tesorería</div>
                                    <div class="small text-white-50">Presupuesto y movimientos</div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if ($isInstructor || $isCoordOrSupport)
                        <div class="col-md-6 col-xl-3">
                            <a href="{{ route('gdf.gateway') }}" class="gdf-quicklink">
                                <i class="bi bi-mortarboard"></i>
                                <div>
                                    <div class="fw-semibold">Operación</div>
                                    <div class="small text-white-50">Seleccionar área/contexto</div>
                                </div>
                            </a>
                        </div>
                    @endif

                    @if ($isAdmin)
                        <div class="col-md-6 col-xl-3">
                            <a href="{{ route('gdf.admin.dashboard') }}" class="gdf-quicklink">
                                <i class="bi bi-shield-check"></i>
                                <div>
                                    <div class="fw-semibold">Admin</div>
                                    <div class="small text-white-50">Usuarios y roles</div>
                                </div>
                            </a>
                        </div>
                    @endif
                </div>

            </div>
        </div>
    @endauth

@endsection
