@extends('gdf::layouts.masteruser')
@section('title','GDF | Áreas')

@section('content')
@php
    $q = $q ?? request('q');
    $active = $active ?? request('active');

    $isAcademic = fn($name) => mb_strtoupper(trim($name)) === 'COORDINACION ACADEMICA' || mb_strtoupper(trim($name)) === 'COORDINACIÓN ACADEMICA';
    $isCampesena = fn($name) => mb_strtoupper(trim($name)) === 'CAMPESENA';
@endphp

<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Áreas</h4>
            <div class="text-muted small">
                Administración de áreas (Coordinación Académica, Campesena, etc.). Desde aquí también se configura el acceso a rubros por área.
            </div>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('gdf.subdirection.dashboard') }}" class="btn btn-outline-secondary">
                Volver
            </a>
            <a href="{{ route('gdf.subdirection.areas.create') }}" class="btn btn-primary">
                + Crear Área
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    {{-- Filters --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-7">
                    <label class="form-label text-muted small mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $q }}" class="form-control"
                           placeholder="Nombre o descripción...">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Estado</label>
                    <select name="active" class="form-select">
                        <option value="">Todos</option>
                        <option value="1" {{ (string)$active==='1' ? 'selected' : '' }}>Activas</option>
                        <option value="0" {{ (string)$active==='0' ? 'selected' : '' }}>Inactivas</option>
                    </select>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>

            <div class="d-flex justify-content-between align-items-center mt-2">
                <div class="text-muted small">
                    @if(isset($areas) && method_exists($areas,'total'))
                        Total: {{ $areas->total() }}
                    @else
                        Total: {{ is_countable($areas) ? count($areas) : 0 }}
                    @endif
                </div>
                <a href="{{ route('gdf.subdirection.areas.index') }}" class="small text-decoration-none">
                    Limpiar filtros
                </a>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="fw-bold">Listado de áreas</div>
            <div class="text-muted small">
                Acciones: Editar / Activar-Inactivar / Configurar rubros por área.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:80px">#</th>
                        <th>Nombre</th>
                        <th>Descripción</th>
                        <th class="text-center" style="width:120px">Estado</th>
                        <th class="text-end" style="width:240px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($areas as $a)
                        @php
                            $tag = null;
                            if ($isAcademic($a->name)) $tag = ['label'=>'Académica','class'=>'bg-primary'];
                            if ($isCampesena($a->name)) $tag = ['label'=>'Campesena','class'=>'bg-success'];
                        @endphp

                        <tr>
                            <td class="text-muted">{{ $a->id }}</td>

                            <td>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="fw-semibold">{{ $a->name }}</div>
                                    @if($tag)
                                        <span class="badge {{ $tag['class'] }}">{{ $tag['label'] }}</span>
                                    @endif
                                </div>

                                <div class="text-muted small">
                                    Creado: {{ optional($a->created_at)->format('Y-m-d') ?? '—' }}
                                </div>
                            </td>

                            <td class="text-muted">
                                {{ $a->description ?: '—' }}
                            </td>

                            <td class="text-center">
                                <span class="badge {{ $a->active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $a->active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </td>

                            <td class="text-end">
                                <div class="d-inline-flex gap-2">

                                    {{-- NUEVO: Configurar rubros por área --}}
                                    <a href="{{ route('gdf.subdirection.area_budget_items.edit', $a->id) }}"
                                       class="btn btn-sm btn-outline-dark">
                                        Rubros
                                    </a>

                                    <a href="{{ route('gdf.subdirection.areas.edit',$a->id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        Editar
                                    </a>

                                    <div class="btn-group">
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            Más
                                        </button>

                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('gdf.subdirection.areas.edit',$a->id) }}">
                                                    Ver / Editar
                                                </a>
                                            </li>

                                            <li>
                                                <a class="dropdown-item"
                                                   href="{{ route('gdf.subdirection.area_budget_items.edit', $a->id) }}">
                                                    Configurar rubros (Área ↔ Rubro)
                                                </a>
                                            </li>

                                            <li><hr class="dropdown-divider"></li>

                                            {{-- Activar/Inactivar (requiere rutas y métodos) --}}
                                            @if($a->active)
                                                <li>
                                                    <form method="POST" action="{{ route('gdf.subdirection.areas.deactivate',$a->id) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="dropdown-item text-danger"
                                                                onclick="return confirm('¿Inactivar esta área?')">
                                                            Inactivar
                                                        </button>
                                                    </form>
                                                </li>
                                            @else
                                                <li>
                                                    <form method="POST" action="{{ route('gdf.subdirection.areas.activate',$a->id) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button class="dropdown-item text-success"
                                                                onclick="return confirm('¿Activar esta área?')">
                                                            Activar
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                No hay áreas registradas.
                                <div class="mt-2">
                                    <a href="{{ route('gdf.subdirection.areas.create') }}" class="btn btn-sm btn-primary">
                                        + Crear Área
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if(method_exists($areas,'links'))
            <div class="card-footer bg-white">
                {{ $areas->withQueryString()->links() }}
            </div>
        @endif
    </div>

    {{-- Ayuda --}}
    <div class="mt-3 text-muted small">
        Recomendación: define rubros permitidos por área en <code>area_budget_items</code>. Ejemplo:
        Campesena solo rubro Campesena; Coordinación Académica puede manejar varios rubros.
    </div>

</div>
@endsection
