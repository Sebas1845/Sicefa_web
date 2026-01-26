{{-- Modules/GDF/Resources/views/support/motorcycles/create.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Crear moto')

@section('content')
@php
  $routePrefix = 'gdf.' . ($areaKey === 'campesena' ? 'campesena' : 'academic');
@endphp

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <div class="text-muted small">GDF / Motos</div>
      <h4 class="mb-0">Crear moto (inventario)</h4>
      <div class="text-muted small">Registro operativo (modelo y kilometraje).</div>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.motorcycles.index') }}">Volver</a>
  </div>

  @if ($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route($routePrefix.'.motorcycles.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Placa *</label>
            <input name="plate" class="form-control" value="{{ old('plate') }}" required maxlength="20">
          </div>

          <div class="col-md-4">
            <label class="form-label">Marca</label>
            <input name="brand" class="form-control" value="{{ old('brand') }}" maxlength="60">
          </div>

          <div class="col-md-4">
            <label class="form-label">Modelo</label>
            <input name="model" class="form-control" value="{{ old('model') }}" maxlength="60">
          </div>

          <div class="col-md-4">
            <label class="form-label">Kilometraje actual *</label>
            <input type="number" name="current_odometer" class="form-control" value="{{ old('current_odometer', 0) }}" min="0" required>
          </div>

          <div class="col-md-8">
            <label class="form-label">Adjunto (opcional)</label>
            <input type="file" name="attachment" class="form-control">
            <div class="text-muted small mt-1">Foto de tarjeta, evidencia, etc. (máx 5MB)</div>
          </div>

          <div class="col-12">
            <label class="form-label">Notas</label>
            <textarea name="notes" class="form-control" rows="3" maxlength="2000">{{ old('notes') }}</textarea>
          </div>

          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary">Guardar moto</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
