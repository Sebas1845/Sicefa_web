@extends('gdf::layouts.masteruser')
@section('title','GDF | Tesorería - Revisión')

@section('content')
@php
    $isTreasury = function_exists('checkRol') ? checkRol('gdf.treasury') : false;
    if(!$isTreasury){ abort(403); }

    $group = $group ?? request('group', 'academic');
    $q     = $q ?? request('q');

    $tabs = [
        'academic'  => 'Coordinación Académica',
        'campesena' => 'Campesena',
    ];

    $fmt = fn($n) => '$ ' . number_format((float)$n, 0, ',', '.');
@endphp

<div class="container py-4">

    <div class="mb-3">
        <h4 class="mb-0">Tesorería</h4>
        <small class="text-muted">Revisión de recursos (vista única con filtro fijo)</small>
    </div>

    {{-- Tabs fijos --}}
    <ul class="nav nav-tabs mb-3">
        @foreach($tabs as $key => $label)
            <li class="nav-item">
                <a class="nav-link {{ $group === $key ? 'active' : '' }}"
                   href="{{ request()->fullUrlWithQuery(['group' => $key, 'page' => 1]) }}">
                    {{ $label }}
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Buscar --}}
    <form class="row g-2 mb-3" method="GET" action="{{ url('/gdf/treasury/review') }}">
        <input type="hidden" name="group" value="{{ $group }}">
        <div class="col-md-6">
            <input class="form-control" name="q" value="{{ $q }}"
                   placeholder="Buscar por código, documento o nombre...">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Buscar</button>
        </div>
        <div class="col-md-2">
            <a class="btn btn-outline-secondary w-100" href="{{ url('/gdf/treasury/review?group='.$group) }}">Limpiar</a>
        </div>
    </form>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <div class="fw-semibold">Solicitudes pendientes — {{ $tabs[$group] ?? $group }}</div>
            <small class="text-muted">Acciones: Aprobar / Devolver / Cerrar</small>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:120px;">Código</th>
                        <th>Solicitante</th>
                        <th style="width:140px;">Total</th>
                        <th>Rubro</th>
                        <th style="width:160px;">Disponible</th>
                        <th style="width:360px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($requests as $r)
                    @php
                        $a = $availability[$r->id] ?? ['name'=>'Sin rubro','allocated'=>0,'executed'=>0,'available'=>0];
                        $ok = ((float)$a['available'] >= (float)($r->total_amount ?? 0));
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $r->code ?? ('#'.$r->id) }}</td>
                        <td>
                            <div class="fw-semibold">{{ $r->applicant_name ?? 'N/D' }}</div>
                            <small class="text-muted">{{ $r->applicant_document ?? '' }}</small>
                        </td>
                        <td class="fw-semibold">{{ $fmt($r->total_amount ?? 0) }}</td>
                        <td>
                            <div class="fw-semibold">{{ $a['name'] }}</div>
                            <small class="text-muted">
                                Asignado: {{ $fmt($a['allocated']) }} |
                                Ejecutado: {{ $fmt($a['executed']) }}
                            </small>
                        </td>
                        <td>
                            <div class="fw-semibold {{ $ok ? 'text-success' : 'text-danger' }}">
                                {{ $fmt($a['available']) }}
                            </div>
                            <small class="{{ $ok ? 'text-success' : 'text-danger' }}">
                                {{ $ok ? 'Suficiente' : 'Insuficiente' }}
                            </small>
                        </td>

                        <td>
                            {{-- Aprobar --}}
                            <form class="d-inline" method="POST" action="{{ url("/gdf/treasury/review/{$r->id}/approve") }}">
                                @csrf
                                <input type="hidden" name="comment" value="">
                                <button class="btn btn-success btn-sm" {{ $ok ? '' : 'disabled' }}>
                                    Aprobar
                                </button>
                            </form>

                            {{-- Devolver a apoyo --}}
                            <button class="btn btn-info btn-sm" data-bs-toggle="collapse" data-bs-target="#retSup{{ $r->id }}">
                                Devolver Apoyo
                            </button>

                            {{-- Devolver a persona --}}
                            <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#retApp{{ $r->id }}">
                                Devolver Persona
                            </button>

                            {{-- Cerrar --}}
                            <button class="btn btn-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#close{{ $r->id }}">
                                Cerrar
                            </button>

                            <div class="collapse mt-2" id="retSup{{ $r->id }}">
                                <form method="POST" action="{{ url("/gdf/treasury/review/{$r->id}/return") }}">
                                    @csrf
                                    <input type="hidden" name="target" value="support">
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" name="comment" required placeholder="Motivo (obligatorio)">
                                        <button class="btn btn-outline-dark">Enviar</button>
                                    </div>
                                </form>
                            </div>

                            <div class="collapse mt-2" id="retApp{{ $r->id }}">
                                <form method="POST" action="{{ url("/gdf/treasury/review/{$r->id}/return") }}">
                                    @csrf
                                    <input type="hidden" name="target" value="applicant">
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" name="comment" required placeholder="Motivo (obligatorio)">
                                        <button class="btn btn-outline-dark">Enviar</button>
                                    </div>
                                </form>
                            </div>

                            <div class="collapse mt-2" id="close{{ $r->id }}">
                                <form method="POST" action="{{ url("/gdf/treasury/review/{$r->id}/close") }}">
                                    @csrf
                                    <div class="input-group input-group-sm">
                                        <input class="form-control" name="comment" required placeholder="Motivo de cierre (obligatorio)">
                                        <button class="btn btn-outline-dark">Cerrar</button>
                                    </div>
                                </form>
                                <small class="text-muted d-block mt-1">
                                    Cierre: no editable; deben crear una nueva solicitud si aplica.
                                </small>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-4 text-muted">No hay solicitudes pendientes.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $requests->links() }}
        </div>
    </div>

</div>
@endsection
