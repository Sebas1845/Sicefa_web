@extends('gdf::layouts.masteruser')

@section('title','GDF | Editar Área')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Editar Área</h4>
            <div class="text-muted small">
                Modifica la información del área
            </div>
        </div>

        <a href="{{ route('gdf.subdirection.areas.index') }}"
           class="btn btn-outline-secondary">
            Volver
        </a>
    </div>

    {{-- Errores --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('gdf.subdirection.areas.update', $area->id) }}">
        @csrf
        @method('PUT')

        <div class="card shadow-sm">
            <div class="card-body">

                {{-- Nombre --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre del Área *</label>
                    <input type="text"
                           name="name"
                           class="form-control"
                           value="{{ old('name', $area->name) }}"
                           required>
                </div>

                {{-- Descripción --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea name="description"
                              class="form-control"
                              rows="3">{{ old('description', $area->description) }}</textarea>
                </div>

                {{-- Activa --}}
                <div class="form-check mb-3">
                    <input class="form-check-input"
                           type="checkbox"
                           name="active"
                           value="1"
                           id="active"
                           {{ old('active', $area->active) ? 'checked' : '' }}>
                    <label class="form-check-label fw-semibold" for="active">
                        Área activa
                    </label>
                </div>

            </div>

            <div class="card-footer text-end">
                <button class="btn btn-primary">
                    Actualizar Área
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
