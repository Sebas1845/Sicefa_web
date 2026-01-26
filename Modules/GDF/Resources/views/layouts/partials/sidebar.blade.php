@php
    $ctx  = session('gdf_context', ['role' => null, 'area' => null]);
    $role = $ctx['role'] ?? null;
    $area = $ctx['area'] ?? null;

    $roleLabel = match($role) {
        'subdirection' => 'Subdirección',
        'treasury'     => 'Tesorería',
        'coord'        => 'Coordinación',
        'support'      => 'Apoyo',
        'official'     => 'Instructor',
        default        => 'SIN ROL',
    };

    $areaLabel = match($area) {
        'academic'  => 'Académica',
        'campesena' => 'Campesena',
        default     => null,
    };

    $headerLabel = $areaLabel ? "{$roleLabel} · {$areaLabel}" : $roleLabel;
@endphp

<aside class="gdf-sidebar" id="gdfSidebar">
    <div class="gdf-sidebar-header">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="gdf-brand-mark"></span>
                <div>
                    <div class="fw-semibold">Panel GDF</div>
                    <div class="text-white-50 small">{{ $headerLabel }}</div>
                </div>
            </div>

            <button class="btn btn-gdf-ghost btn-sm" type="button" id="toggleSidebar" aria-label="Contraer sidebar">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
        </div>
    </div>

    <div class="gdf-sidebar-body">

        <a class="gdf-side-link {{ request()->routeIs('gdf.gateway') ? 'active' : '' }}"
           href="{{ route('gdf.gateway') }}">
            <i class="bi bi-grid-1x2"></i>
            <span>Cambiar rol</span>
        </a>

        {{-- ADMIN --}}
        @if(in_array($role, ['subdirection','treasury'], true))
            <div class="gdf-side-section">Administración</div>

            <a class="gdf-side-link {{ request()->routeIs('gdf.admin.dashboard') ? 'active' : '' }}"
               href="{{ route('gdf.admin.dashboard') }}">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>

            <a class="gdf-side-link {{ request()->routeIs('gdf.admin.users.*') ? 'active' : '' }}"
               href="{{ route('gdf.admin.users.index') }}">
                <i class="bi bi-people"></i>
                <span>Usuarios y roles</span>
            </a>

            <a class="gdf-side-link" href="{{ url('/gdf/panel') }}">
                <i class="bi bi-window"></i>
                <span>Panel Filament</span>
            </a>
        @endif

        {{-- ACADÉMICA --}}
        @if(in_array($role, ['coord','support'], true) && $area === 'academic')
            <div class="gdf-side-section">Operación</div>

            <a class="gdf-side-link {{ request()->routeIs('gdf.academic.dashboard') ? 'active' : '' }}"
               href="{{ route('gdf.academic.dashboard') }}">
                <i class="bi bi-mortarboard"></i>
                <span>Dashboard Académica</span>
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

        {{-- CAMPESENA --}}
        @if(in_array($role, ['coord','support'], true) && $area === 'campesena')
            <div class="gdf-side-section">Operación</div>

            <a class="gdf-side-link {{ request()->routeIs('gdf.campesena.dashboard') ? 'active' : '' }}"
               href="{{ route('gdf.campesena.dashboard') }}">
                <i class="bi bi-tree"></i>
                <span>Dashboard Campesena</span>
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

        {{-- SOPORTE --}}
        <div class="gdf-side-section">Soporte</div>
        <a class="gdf-side-link {{ request()->routeIs('cefa.gdf.tecnologias') ? 'active' : '' }}"
           href="{{ route('cefa.gdf.tecnologias') }}">
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
