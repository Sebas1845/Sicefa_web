@extends('gdf::layouts.masteruser')

@section('title','GDF | Crear Área')

@section('content')
<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Crear Área</h4>
            <div class="text-muted small">
                Registra una nueva área del sistema (ej: Coordinación Académica, Campesena)
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

    <form method="POST" action="{{ route('gdf.subdirection.areas.store') }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-body">

                {{-- Nombre --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Nombre del Área *</label>
                    <input type="text"
                           name="name"
                           class="form-control"
                           value="{{ old('name') }}"
                           placeholder="Ej: Coordinación Académica"
                           required>
                </div>

                {{-- Descripción --}}
                <div class="mb-3">
                    <label class="form-label fw-semibold">Descripción</label>
                    <textarea name="description"
                              class="form-control"
                              rows="3"
                              placeholder="Descripción opcional del área">{{ old('description') }}</textarea>
                </div>

                {{-- Activa --}}
                <div class="form-check mb-3">
                    <input class="form-check-input"
                           type="checkbox"
                           name="active"
                           value="1"
                           id="active"
                           checked>
                    <label class="form-check-label fw-semibold" for="active">
                        Área activa
                    </label>
                </div>

            </div>

            <div class="card-footer text-end">
                <button class="btn btn-primary">
                    Guardar Área
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
