@extends('gdf::layouts.masteruser')
@section('title','GDF | Ajustar Presupuesto')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Ajustar Presupuesto</h4>
            <div class="text-muted">
                {{ $budget->budgetItem->code ?? '' }} - {{ $budget->budgetItem->name ?? '' }}
                | Área: {{ $budget->area->name ?? '—' }}
                | Año: {{ $budget->year }}
            </div>
        </div>
        <a href="{{ route('gdf.subdirection.budgets.index') }}" class="btn btn-outline-secondary">Volver</a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="text-muted small">Monto actual</div>
            <div class="fw-bold fs-4">$ {{ number_format($budget->current_amount,0,',','.') }}</div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('gdf.subdirection.budgets.adjust.store', $budget->id) }}">
                @csrf

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="type" class="form-select" required>
                            <option value="add">Aumentar</option>
                            <option value="subtract">Disminuir</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Valor</label>
                        <input type="number" name="amount" min="1" step="0.01" class="form-control" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Motivo</label>
                        <input type="text" name="reason" class="form-control" maxlength="2000" required>
                    </div>
                </div>

                <div class="mt-3">
                    <button class="btn btn-primary">Aplicar ajuste</button>
                </div>
            </form>
        </div>
    </div>

    @if(isset($movements))
    <div class="card shadow-sm mt-4">
        <div class="card-header fw-semibold">Últimos movimientos</div>
        <div class="card-body p-0">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th class="text-end">Valor</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($movements as $m)
                        <tr>
                            <td>{{ $m->created_at }}</td>
                            <td>{{ $m->type }}</td>
                            <td class="text-end">$ {{ number_format($m->amount,0,',','.') }}</td>
                            <td>{{ $m->description }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin movimientos</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
