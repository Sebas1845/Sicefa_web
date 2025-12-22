<nav class="navbar navbar-expand-lg navbar-dark gdf-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold" href="{{ route('cefa.gdf.index') }}">
            <span class="gdf-brand-mark"></span>
            <span>GDF</span>
            <span class="text-white-50 fw-normal d-none d-sm-inline">| Traslados</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#gdfNavbar">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="gdfNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('cefa.gdf.index') ? 'active' : '' }}"
                       href="{{ route('cefa.gdf.index') }}">
                        <i class="bi bi-house"></i> Inicio
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('cefa.gdf.developers') ? 'active' : '' }}"
                       href="#">
                        <i class="bi bi-code-slash"></i> Desarrolladores
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('cefa.gdf.tecnologias') ? 'active' : '' }}"
                       href="#">
                        <i class="bi bi-laptop"></i> Sobre el software
                    </a>
                </li>

                <li class="nav-item ms-lg-3">
                    <button class="btn btn-gdf-ghost btn-sm" type="button" id="toggleTheme" aria-label="Cambiar tema">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </li>

                @guest
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-gdf-primary btn-sm" href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                        </a>
                    </li>
                @else
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-gdf-primary btn-sm" href="{{ route('gdf.gateway') }}">
                            <i class="bi bi-grid-1x2"></i> Elegir rol
                        </a>
                    </li>

                    <li class="nav-item ms-lg-2 d-flex align-items-center">
                        <div class="gdf-userpill">
                            <i class="bi bi-person-circle"></i>
                            <span class="small">{{ auth()->user()->nickname }}</span>
                        </div>
                    </li>

                    <li class="nav-item ms-lg-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-gdf-danger btn-sm" type="submit">
                                <i class="bi bi-box-arrow-right"></i> Salir
                            </button>
                        </form>
                    </li>
                @endguest
            </ul>
        </div>
    </div>
</nav>
