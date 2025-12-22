@php
    $area = session('gdf.area'); // academic|campesena|null
    $mode = session('gdf.mode'); // coord|support|null

    $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
    $isTreasury     = function_exists('checkRol') ? checkRol('gdf.treasury') : false;

    $isAcademicCoord = function_exists('checkRol') ? checkRol('gdf.academic_coordination') : false;
    $isCampesenaCoord = function_exists('checkRol') ? checkRol('gdf.campesena_coordination') : false;

    $isSupportAcademic = function_exists('checkRol') ? checkRol('gdf.support_academic') : false;
    $isSupportCampesena = function_exists('checkRol') ? checkRol('gdf.support_campesena') : false;

    $isAnyAdmin = $isSubdirection || $isTreasury;
@endphp

<aside class="gdf-sidebar" id="gdfSidebar">
    <div class="gdf-sidebar-header">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="gdf-brand-mark"></span>
                <div>
                    <div class="fw-semibold">Panel GDF</div>
                    <div class="text-white-50 small">
                        {{ $area ? strtoupper($area) : 'SIN ÁREA' }} · {{ $mode ? strtoupper($mode) : 'MODO' }}
                    </div>
                </div>
            </div>

            <button class="btn btn-gdf-ghost btn-sm" type="button" id="toggleSidebar" aria-label="Contraer sidebar">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
        </div>
    </div>

    <div class="gdf-sidebar-body">

        {{-- Acceso rápido --}}
        <a class="gdf-side-link {{ request()->routeIs('gdf.gateway') ? 'active' : '' }}"
           href="{{ route('gdf.gateway') }}">
            <i class="bi bi-grid-1x2"></i>
            <span>Elegir rol</span>
        </a>

        @if($isAnyAdmin)
            <div class="gdf-side-section">Administración</div>

            <a class="gdf-side-link {{ request()->routeIs('gdf.view.admin') ? 'active' : '' }}"
               href="{{ route('gdf.view.admin') }}">
                <i class="bi bi-shield-check"></i>
                <span>Dashboard Admin</span>
            </a>

            {{-- Filament opcional --}}
            <a class="gdf-side-link" href="{{ url('/gdf/panel') }}">
                <i class="bi bi-window"></i>
                <span>Panel Filament</span>
            </a>
        @endif

        <div class="gdf-side-section">Operación</div>

        @if($area === 'academic' && ($isAcademicCoord || $isSupportAcademic))
            <a class="gdf-side-link {{ request()->routeIs('gdf.view.coordination') ? 'active' : '' }}"
               href="{{ route('gdf.view.coordination') }}">
                <i class="bi bi-mortarboard"></i>
                <span>Coordinación Académica</span>
            </a>

            <a class="gdf-side-link" href="#">
                <i class="bi bi-list-check"></i>
                <span>Solicitudes</span>
            </a>

            <a class="gdf-side-link" href="#">
                <i class="bi bi-people"></i>
                <span>Instructores</span>
            </a>
        @endif

        @if($area === 'campesena' && ($isCampesenaCoord || $isSupportCampesena))
            <a class="gdf-side-link {{ request()->routeIs('gdf.view.campesena') ? 'active' : '' }}"
               href="{{ route('gdf.view.campesena') }}">
                <i class="bi bi-tree"></i>
                <span>Campesena</span>
            </a>

            <a class="gdf-side-link" href="#">
                <i class="bi bi-list-check"></i>
                <span>Solicitudes</span>
            </a>

            <a class="gdf-side-link" href="#">
                <i class="bi bi-people"></i>
                <span>Instructores</span>
            </a>
        @endif

        <div class="gdf-side-section">Soporte</div>
        <a class="gdf-side-link" href="{{ route('cefa.gdf.tecnologias') }}">
            <i class="bi bi-info-circle"></i>
            <span>Sobre el software</span>
        </a>
    </div>

    <div class="gdf-sidebar-footer">
        <div class="small text-white-50">
            {{ auth()->user()->nickname ?? 'Invitado' }}
        </div>
    </div>
</aside>
