@php
    // Cambia esta ruta según dónde guardes la imagen
    $heroImage = asset('modules/gdf/images/log/buses.png');
@endphp

<header class="gdf-hero" style="--gdf-hero-image: url('{{ $heroImage }}');">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <span class="badge gdf-badge mb-3">
                    <i class="bi bi-shield-check"></i>
                    Flujo por roles · Auditoría · Presupuesto
                </span>

                <h1 class="gdf-hero-title">
                    Gestión transparente y eficiente de traslados
                </h1>

                <p class="gdf-hero-subtitle">
                    Centraliza solicitudes, validaciones y soportes con trazabilidad completa.
                    Diseñado para operación por áreas: Coordinación Académica y Campesena.
                </p>

                <div class="d-flex flex-wrap gap-2 mt-4">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-gdf-primary btn-lg">
                            Iniciar sesión <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="#info" class="btn btn-gdf-ghost btn-lg">
                            Conocer más
                        </a>
                    @else
                        <a href="{{ route('gdf.gateway') }}" class="btn btn-gdf-primary btn-lg">
                            Elegir rol <i class="bi bi-arrow-right"></i>
                        </a>
                        <a href="#info" class="btn btn-gdf-ghost btn-lg">
                            Ver información
                        </a>
                    @endguest
                </div>
            </div>

            <div class="col-lg-5">
                <div class="gdf-hero-card">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <div class="text-white-50 small">Estado</div>
                            <div class="h5 text-white mb-1">Operación controlada</div>
                            <div class="text-white-50 small">
                                Roles: Subdirección, Tesorería, Coordinación, Apoyos e Instructores
                            </div>
                        </div>
                        <span class="gdf-chip">
                            <i class="bi bi-check2-circle"></i> GDF
                        </span>
                    </div>

                    <hr class="gdf-hr">

                    <div class="d-flex gap-2 flex-wrap">
                        <span class="gdf-mini">
                            <i class="bi bi-receipt"></i> Solicitudes
                        </span>
                        <span class="gdf-mini">
                            <i class="bi bi-clipboard-check"></i> Revisiones
                        </span>
                        <span class="gdf-mini">
                            <i class="bi bi-folder2-open"></i> Soportes
                        </span>
                        <span class="gdf-mini">
                            <i class="bi bi-graph-up"></i> Reportes
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="gdf-hero-down">
            <a href="#info" class="gdf-scroll">
                <i class="bi bi-chevron-double-down"></i>
            </a>
        </div>
    </div>

    <div class="gdf-hero-overlay"></div>
</header>
