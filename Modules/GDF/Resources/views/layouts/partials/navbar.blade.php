@php
    use Illuminate\Support\Facades\Auth;

    // Contexto GDF (si existe)
    $ctxRole = data_get(session('gdf_context'), 'role');
    $ctxArea = data_get(session('gdf_context'), 'area');

    $roleLabel = match($ctxRole) {
        'subdirection' => 'Subdirección',
        'treasury'     => 'Tesorería',
        'coord'        => $ctxArea === 'campesena' ? 'Coordinación Campesena' : 'Coordinación Académica',
        'support'      => $ctxArea === 'campesena' ? 'Apoyo Campesena' : 'Apoyo Académico',
        'official'     => $ctxArea === 'campesena' ? 'Instructor Campesena' : 'Instructor Académico',
        default        => null,
    };

    // Roles altos (solo si está logueado)
    $isAdminGdf = $isSubdirection = $isTreasury = false;

    if (Auth::check() && function_exists('checkRol')) {
        $isAdminGdf     = checkRol('gdf.admin');
        $isSubdirection = checkRol('gdf.subdirection');
        $isTreasury     = checkRol('gdf.treasury');
    }

    $isHighRole = $isAdminGdf || $isSubdirection || $isTreasury;

    // Ruta del panel rápido
    $panelRoute = $isAdminGdf
        ? 'gdf.admin.dashboard'
        : ($isSubdirection
            ? 'gdf.subdirection.dashboard'
            : ($isTreasury ? 'gdf.treasury.dashboard' : null));
@endphp

<nav class="navbar navbar-expand-lg navbar-dark gdf-navbar sticky-top">
    <div class="container">

        {{-- BRAND --}}
        <a class="navbar-brand d-flex align-items-center gap-2 fw-semibold"
           href="{{ route('gdf.index') }}">
            <span class="gdf-brand-mark"></span>
            <span>GDF</span>
            <span class="text-white-50 fw-normal d-none d-sm-inline">| Traslados</span>
        </a>

        {{-- TOGGLER --}}
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#gdfNavbar" aria-controls="gdfNavbar"
                aria-expanded="false" aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        {{-- MENU --}}
        <div class="collapse navbar-collapse" id="gdfNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">

                {{-- ===================== --}}
                {{-- LINKS PÚBLICOS --}}
                {{-- ===================== --}}
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('gdf.index') ? 'active' : '' }}"
                       href="{{ route('gdf.index') }}">
                        <i class="bi bi-house"></i> Inicio
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('gdf.developers') ? 'active' : '' }}"
                       href="{{ route('gdf.developers') }}">
                        <i class="bi bi-code-slash"></i> Desarrolladores
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('gdf.about') ? 'active' : '' }}"
                       href="{{ route('gdf.about') }}">
                        <i class="bi bi-info-circle"></i> Sobre el software
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('gdf.tech') ? 'active' : '' }}"
                       href="{{ route('gdf.tech') }}">
                        <i class="bi bi-tools"></i> Tecnologías
                    </a>
                </li>

                {{-- ===================== --}}
                {{-- BOTÓN TEMA --}}
                {{-- ===================== --}}
                <li class="nav-item ms-lg-3">
                    <button class="btn btn-gdf-ghost btn-sm" type="button"
                            id="toggleTheme" aria-label="Cambiar tema">
                        <i class="bi bi-moon-stars"></i>
                    </button>
                </li>

                {{-- ===================== --}}
                {{-- GUEST --}}
                {{-- ===================== --}}
                @guest
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-gdf-primary btn-sm"
                           href="{{ route('login') }}">
                            <i class="bi bi-box-arrow-in-right"></i> Iniciar sesión
                        </a>
                    </li>
                @endguest

                {{-- ===================== --}}
                {{-- AUTH --}}
                {{-- ===================== --}}
                @auth

                    {{-- PANEL RÁPIDO (roles altos, sin gateway) --}}
                    @if($isHighRole && $panelRoute)
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-outline-success btn-sm"
                               href="{{ route($panelRoute) }}">
                                <i class="bi bi-shield-check"></i>
                                {{ $isAdminGdf ? 'Admin GDF' : ($isSubdirection ? 'Subdirección' : 'Tesorería') }}
                            </a>
                        </li>

                    {{-- OPERACIÓN (roles con contexto) --}}
                    @else
                        @if($roleLabel)
                            <li class="nav-item ms-lg-2">
                                <span class="btn btn-outline-success btn-sm disabled">
                                    <i class="bi bi-person-badge"></i> {{ $roleLabel }}
                                </span>
                            </li>
                            <li class="nav-item ms-lg-1">
                                <a class="btn btn-gdf-ghost btn-sm"
                                   href="{{ route('gdf.gateway') }}">
                                    <i class="bi bi-arrow-repeat"></i> Cambiar
                                </a>
                            </li>
                        @else
                            <li class="nav-item ms-lg-2">
                                <a class="btn btn-gdf-primary btn-sm"
                                   href="{{ route('gdf.gateway') }}">
                                    <i class="bi bi-grid-1x2"></i> Elegir rol
                                </a>
                            </li>
                        @endif
                    @endif

                    {{-- USUARIO --}}
                    <li class="nav-item ms-lg-2 d-flex align-items-center">
                        <div class="gdf-userpill">
                            <i class="bi bi-person-circle"></i>
                            <span class="small">
                                {{ auth()->user()->nickname ?? auth()->user()->email }}
                            </span>
                        </div>
                    </li>

                    {{-- LOGOUT --}}
                    <li class="nav-item ms-lg-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-gdf-danger btn-sm" type="submit">
                                <i class="bi bi-box-arrow-right"></i> Salir
                            </button>
                        </form>
                    </li>
                @endauth

            </ul>
        </div>
    </div>
</nav>
