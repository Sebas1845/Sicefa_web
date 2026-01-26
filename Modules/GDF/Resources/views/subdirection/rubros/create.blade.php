@extends('gdf::layouts.masteruser')
@section('title','GDF | Crear Rubro')

@section('content')
<div class="container py-4">
    <h4 class="fw-bold mb-3">Crear Rubro</h4>

    @if($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

    <form method="POST" action="{{ route('gdf.subdirection.rubros.store') }}" class="card shadow-sm p-4">
        @csrf

        <div class="mb-3">
            <label class="form-label">Código</label>
            <input name="code" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Descripción</label>
            <textarea name="description" class="form-control"></textarea>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allow_staff" value="1">
            <label class="form-check-label">Permite Planta</label>
        </div>

        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="allow_contractors" value="1">
            <label class="form-check-label">Permite Contratistas</label>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="active" value="1" checked>
            <label class="form-check-label">Activo</label>
        </div>

        <div class="d-flex gap-2">
            <a href="{{ route('gdf.subdirection.rubros') }}" class="btn btn-outline-secondary">Cancelar</a>
            <button class="btn btn-primary">Guardar</button>
        </div>
    </form>
</div>
@endsection
