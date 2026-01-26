@extends('gdf::layouts.masteruser')
@section('title','GDF | Solicitudes')

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Solicitudes</h4>
            <div class="text-muted">Validación final de solicitudes: aprobar, rechazar o devolver.</div>
        </div>

        <a href="{{ route('gdf.subdirection.dashboard') }}" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Filtro por estado --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-6">
                    <label class="form-label small text-muted mb-1">Estado</label>
                    <select name="status" class="form-select">
                        @php
                            $opts = [
                                'pending_subdirection' => 'Pendiente Subdirección',
                                'approved_subdirection' => 'Aprobada Subdirección',
                                'rejected_subdirection' => 'Rechazada Subdirección',
                                'returned_to_coordination' => 'Devuelta a Coordinación',
                            ];
                        @endphp
                        @foreach($opts as $k=>$lbl)
                            <option value="{{ $k }}" {{ $status===$k?'selected':'' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-outline-primary">Filtrar</button>
                </div>

                <div class="col-12 col-md-3 d-grid">
                    <a href="{{ route('gdf.subdirection.requests') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>Listado</span>
            <span class="text-muted small">{{ $requests->total() }} registro(s)</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:90px;">#</th>
                            <th>Instructor</th>
                            <th class="text-end" style="width:170px;">Valor</th>
                            <th style="width:220px;">Estado</th>
                            <th class="text-end" style="width:160px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            <tr>
                                <td class="fw-semibold">{{ $req->id }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $req->instructor_name ?? '—' }}</div>
                                    <div class="text-muted small">
                                        {{ $req->created_at ? $req->created_at->format('Y-m-d H:i') : '' }}
                                    </div>
                                </td>
                                <td class="text-end fw-bold">
                                    $ {{ number_format($req->amount ?? 0,0,',','.') }}
                                </td>
                                <td>
                                    @php
                                        $badge = match($req->status) {
                                            'pending_subdirection' => 'bg-warning text-dark',
                                            'approved_subdirection' => 'bg-success',
                                            'rejected_subdirection' => 'bg-danger',
                                            'returned_to_coordination' => 'bg-secondary',
                                            default => 'bg-info'
                                        };
                                    @endphp
                                    <span class="badge {{ $badge }}">{{ $req->status }}</span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('gdf.subdirection.requests.show',$req->id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        Revisar
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    No hay solicitudes para este estado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        {{ $requests->links() }}
    </div>

</div>
@endsection
