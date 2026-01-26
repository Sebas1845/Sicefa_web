@extends('gdf::layouts.masteruser')
@section('title','GDF | Auditoría (Subdirección)')

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Auditoría</h4>
            <div class="text-muted small">Trazabilidad de acciones realizadas en el módulo.</div>
        </div>
        <a href="{{ route('gdf.subdirection.dashboard') ?? url()->previous() }}" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    @if(session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-10">
                    <label class="form-label text-muted small mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control"
                           placeholder="acción, descripción, usuario...">
                </div>
                <div class="col-md-2 d-grid">
                    <label class="form-label text-muted small mb-1">&nbsp;</label>
                    <button class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>Registros</span>
            <span class="text-muted small">Total: {{ method_exists($logs,'total') ? $logs->total() : 0 }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-muted">{{ $log->id }}</td>
                            <td class="text-muted">
                                {{ optional($log->created_at)->format('Y-m-d H:i') }}
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $log->action ?? '—' }}</span>
                            </td>
                            <td class="text-muted">{{ $log->description ?? '—' }}</td>
                            <td class="text-muted">
                                {{ $log->user->email ?? ('ID '.$log->user_id) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">No hay registros.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($logs,'links'))
            <div class="card-footer bg-white">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
