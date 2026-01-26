<section class="gdf-hero" id="top">
    <div id="gdfHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="7000">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#gdfHeroCarousel" data-bs-slide-to="0" class="active" aria-current="true"></button>
            <button type="button" data-bs-target="#gdfHeroCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#gdfHeroCarousel" data-bs-slide-to="2"></button>
        </div>

        <div class="carousel-inner">

            {{-- Slide 1 --}}
            <div class="carousel-item active">
                <div class="gdf-hero-slide" style="background-image:url('{{ asset('modules/gdf/images/hero/buses.jpg') }}');">
                    <div class="gdf-hero-overlay"></div>

                    <div class="container position-relative">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <span class="gdf-badge">
                                    Flujo por roles · Auditoría · Presupuesto
                                </span>
                                <h1 class="gdf-hero-title mt-3">
                                    Gestión transparente y eficiente de traslados
                                </h1>
                                <p class="gdf-hero-subtitle">
                                    Controla solicitudes, aprobaciones, soportes y trazabilidad en un solo lugar.
                                </p>

                                <div class="d-flex gap-2 mt-4 flex-wrap">
                                    <a href="#info" class="btn btn-gdf-primary">
                                        Conocer más
                                    </a>

                                    @auth
                                        <a href="{{ route('gdf.gateway') }}" class="btn btn-gdf-ghost">
                                            Cambiar rol
                                        </a>
                                    @else
                                        <a href="{{ route('login') }}" class="btn btn-gdf-ghost">
                                            Iniciar sesión
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide 2 --}}
            <div class="carousel-item">
                <div class="gdf-hero-slide" style="background-image:url('{{ asset('modules/gdf/images/hero/route.jpg') }}');">
                    <div class="gdf-hero-overlay"></div>

                    <div class="container position-relative">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <span class="gdf-badge">
                                    Coordinación · Campesena · Tesorería
                                </span>
                                <h2 class="gdf-hero-title mt-3">
                                    Aprobaciones controladas por contexto
                                </h2>
                                <p class="gdf-hero-subtitle">
                                    Cambia de rol cuando corresponda. No hay accesos cruzados sin selección explícita.
                                </p>

                                <a href="#info" class="btn btn-gdf-primary mt-3">Ver componentes</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Slide 3 --}}
            <div class="carousel-item">
                <div class="gdf-hero-slide" style="background-image:url('{{ asset('modules/gdf/images/hero/documents.jpg') }}');">
                    <div class="gdf-hero-overlay"></div>

                    <div class="container position-relative">
                        <div class="row align-items-center">
                            <div class="col-lg-7">
                                <span class="gdf-badge">
                                    Soportes · Evidencias · Historial
                                </span>
                                <h2 class="gdf-hero-title mt-3">
                                    Trazabilidad completa del proceso
                                </h2>
                                <p class="gdf-hero-subtitle">
                                    Registro de revisiones, movimientos presupuestales y documentos asociados.
                                </p>

                                <a href="#info" class="btn btn-gdf-primary mt-3">Explorar</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#gdfHeroCarousel" data-bs-slide="prev" aria-label="Anterior">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#gdfHeroCarousel" data-bs-slide="next" aria-label="Siguiente">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    <a href="#info" class="gdf-hero-scroll" aria-label="Bajar a información">
        <i class="bi bi-chevron-down"></i>
    </a>
</section>
