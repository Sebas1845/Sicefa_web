@extends('sigac::layouts.master')

@section('content')


<div class="container mt-4">

    <h3 class="mb-3">Rondas de Ambiente</h3>

    <!-- Filtros -->
    <form method="GET" action="{{ route('sigac.coordinador.environment_rounds.index') }}" class="card p-3 mb-4">

        <div class="row">

            <div class="col-md-4">
                <label class="form-label">Fecha</label>
                <input type="date" name="date" value="{{ $date }}" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">Jornada</label>
                <select name="shift" class="form-control">
                    <option value="MANANA" {{ $shift == 'MANANA' ? 'selected' : '' }}>Mañana</option>
                    <option value="TARDE"  {{ $shift == 'TARDE'  ? 'selected' : '' }}>Tarde</option>
                    <option value="NOCHE"  {{ $shift == 'NOCHE'  ? 'selected' : '' }}>Noche</option>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-primary w-100">Filtrar</button>
            </div>
        </div>
    </form>

    <!-- Crear ronda -->
    <form method="POST" action="{{ route('sigac.coordinador.environment_rounds.create') }}" class="card p-3">
        @csrf

        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="shift" value="{{ $shift }}">

        <button class="btn btn-success">
            Crear / Abrir ronda de {{ $date }} ({{ $shift }})
        </button>
    </form>

    <!-- Resultados -->
    <div class="card mt-4">
        <div class="card-header">Rondas encontradas</div>

        <table class="table table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Jornada</th>
                    <th>Entradas</th>
                    <th>Estado</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rounds as $r)
                <tr>
                    <td>{{ $r->date }}</td>
                    <td>{{ $r->shift }}</td>
                    <td>{{ $r->entries_count }}</td>
                    <td>
                        @if ($r->is_locked)
                            <span class="badge bg-danger">Cerrada</span>
                        @else
                            <span class="badge bg-success">Abierta</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('sigac.coordinador.environment_rounds.show', $r->id) }}" class="btn btn-sm btn-primary">
                            Ver ronda
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>

    </div>

</div>

@endsection
