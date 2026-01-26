@extends('gdf::layouts.masteruser')
@section('title','GDF | Movimientos (Subdirección)')

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Movimientos presupuestales</h4>
            <div class="text-muted small">Registro de adiciones, ajustes y ejecuciones por rubro/área.</div>
        </div>
        <a href="{{ route('gdf.subdirection.dashboard') ?? url()->previous() }}" class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-3">
                    <label class="form-label text-muted small mb-1">Año</label>
                    <input type="number" name="year" value="{{ $year ?? '' }}" class="form-control" placeholder="2025">
                </div>

                <div class="col-md-4">
                    <label class="form-label text-muted small mb-1">Área</label>
                    <select name="area_id" class="form-select">
                        <option value="">Todas</option>
                        @foreach($areas as $a)
                            <option value="{{ $a->id }}" {{ ($areaId==$a->id)?'selected':'' }}>
                                {{ $a->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label text-muted small mb-1">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos</option>
                        @foreach($types as $t)
                            <option value="{{ $t }}" {{ ($type===$t)?'selected':'' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-10">
                    <label class="form-label text-muted small mb-1">Buscar</label>
                    <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control"
                           placeholder="descripción o usuario...">
                </div>

                <div class="col-md-2 d-grid">
                    <label class="form-label text-muted small mb-1">&nbsp;</label>
                    <button class="btn btn-outline-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div> @endif

    <div class="card shadow-sm">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span>Movimientos</span>
            <span class="text-muted small">Total: {{ method_exists($movements,'total') ? $movements->total() : 0 }}</span>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Área</th>
                        <th>Rubro</th>
                        <th class="text-end">Monto</th>
                        <th>Usuario</th>
                        <th class="text-end">Detalle</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                        <tr>
                            <td class="text-muted">{{ $m->id }}</td>
                            <td class="text-muted">{{ optional($m->created_at)->format('Y-m-d H:i') }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $m->type ?? '—' }}</span></td>
                            <td class="text-muted">{{ $m->budget->area->name ?? '—' }}</td>
                            <td class="text-muted">
                                {{ $m->budget->budgetItem->code ?? '' }}
                                {{ $m->budget->budgetItem->name ?? '—' }}
                            </td>
                            <td class="text-end fw-semibold">
                                $ {{ number_format($m->amount ?? 0,0,',','.') }}
                            </td>
                            <td class="text-muted">{{ $m->user->email ?? ('ID '.$m->user_id) }}</td>
                            <td class="text-end">
                                <a href="{{ route('gdf.subdirection.movements.show',$m->id) }}"
                                   class="btn btn-sm btn-outline-secondary">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">No hay movimientos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($movements,'links'))
            <div class="card-footer bg-white">
                {{ $movements->withQueryString()->links() }}
            </div>
        @endif
    </div>

</div>
@endsection
