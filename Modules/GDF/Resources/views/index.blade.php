@extends('gdf::layouts.master')

@section('title','GDF | Inicio')

@section('content')

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
                soportes documentales y control presupuestal por rubro/área. Diseñado para operación
                por Coordinación Académica y Campesena, con aprobaciones y auditoría.
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
                    <a href="{{ route('gdf.gateway') }}" class="btn btn-gdf-primary w-100">
                        <i class="bi bi-grid-1x2"></i> Elegir rol y área
                    </a>
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

{{-- Sección: Si está logueado, mostrar accesos rápidos --}}
@auth
    <div class="mt-4">
        <div class="gdf-card p-4">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-semibold mb-1">Accesos rápidos</h5>
                    <div class="text-white-50 small">
                        Selecciona el panel según tus permisos.
                    </div>
                </div>
                <a href="{{ route('gdf.gateway') }}" class="btn btn-gdf-primary btn-sm">
                    <i class="bi bi-grid-1x2"></i> Ir al selector
                </a>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-6 col-xl-3">
                    <a href="#" class="gdf-quicklink">
                        <i class="bi bi-shield-check"></i>
                        <div>
                            <div class="fw-semibold">Subdirección / Admin</div>
                            <div class="small text-white-50">Aprobación final y control</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-3">
                    <a href="{#" class="gdf-quicklink">
                        <i class="bi bi-wallet2"></i>
                        <div>
                            <div class="fw-semibold">Tesorería</div>
                            <div class="small text-white-50">Presupuesto y movimientos</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-3">
                    <a href="#" class="gdf-quicklink">
                        <i class="bi bi-mortarboard"></i>
                        <div>
                            <div class="fw-semibold">Coordinación Académica</div>
                            <div class="small text-white-50">Gestión por área</div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-3">
                    <a href="#" class="gdf-quicklink">
                        <i class="bi bi-tree"></i>
                        <div>
                            <div class="fw-semibold">Campesena</div>
                            <div class="small text-white-50">Gestión por área</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
@endauth

@endsection
