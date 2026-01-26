@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Presupuestos')

@section('content')
<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Presupuestos</h4>

        <a href="{{ route('gdf.subdirection.budgets.create', ['year' => $year]) }}"
           class="btn btn-primary">
            Nuevo presupuesto
        </a>
    </div>

    {{-- Filtros --}}
    <form class="row g-2 mb-3">
        <div class="col-md-3">
            <input type="text"
                   name="q"
                   class="form-control"
                   placeholder="Buscar rubro..."
                   value="{{ $q }}">
        </div>

        <div class="col-md-2">
            <select name="year" class="form-select" onchange="this.form.submit()">
                @foreach($years as $y)
                    <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100">Filtrar</button>
        </div>
    </form>

    {{-- Tabla --}}
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rubro</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Disponible</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($budgets as $b)
                    <tr>
                        <td>
                            <strong>{{ $b->budgetItem->name }}</strong><br>
                            <small class="text-muted">{{ $b->budgetItem->code }}</small>
                        </td>
                        <td class="text-end">
                            ${{ number_format($b->total_amount, 0, ',', '.') }}
                        </td>
                        <td class="text-end">
                            ${{ number_format($b->current_amount, 0, ',', '.') }}
                        </td>
                        <td class="text-center">
                            <a href="{{ route('gdf.subdirection.budgets.areas.edit', $b->id) }}"
                               class="btn btn-sm btn-outline-primary">
                                Distribuir por áreas
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">
                            No hay presupuestos registrados para este año.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $budgets->links() }}
    </div>

</div>
@endsection
