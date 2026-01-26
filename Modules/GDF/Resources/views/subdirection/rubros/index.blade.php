@extends('gdf::layouts.masteruser')
@section('title','GDF | Rubros')

@section('content')
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Rubros</h4>
            <div class="text-muted">Catálogo de rubros presupuestales</div>
        </div>
        <a href="{{ route('gdf.subdirection.rubros.create') }}" class="btn btn-primary">
            Crear rubro
        </a>
    </div>

    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif

    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5">
            <input type="text" name="q" value="{{ $q }}" class="form-control" placeholder="Buscar rubro">
        </div>
        <div class="col-md-2">
            <button class="btn btn-outline-secondary w-100">Buscar</button>
        </div>
    </form>

    <div class="card shadow-sm">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th class="text-center">Planta</th>
                    <th class="text-center">Contratistas</th>
                    <th class="text-center">Activo</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rubros as $r)
                <tr>
                    <td>{{ $r->code }}</td>
                    <td>{{ $r->name }}</td>
                    <td class="text-center">{{ $r->allow_staff ? 'Sí' : 'No' }}</td>
                    <td class="text-center">{{ $r->allow_contractors ? 'Sí' : 'No' }}</td>
                    <td class="text-center">{{ $r->active ? 'Activo' : 'Inactivo' }}</td>
                    <td class="text-end">
                        <a href="{{ route('gdf.subdirection.rubros.edit',$r->id) }}"
                           class="btn btn-sm btn-outline-primary">Editar</a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted">Sin rubros</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">{{ $rubros->links() }}</div>
</div>
@endsection
