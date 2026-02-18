{{-- Modules/GDF/Resources/views/subdirection/rubros/edit.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Editar rubro #'.($rubro->id ?? '—'))

@section('content')
@php
  $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
  if(!$ok) abort(403);
@endphp

<div class="container py-4">
  <div class="mb-3">
    <div class="text-muted small">GDF · Subdirección</div>
    <h4 class="mb-1">Editar rubro #{{ $rubro->id }}</h4>
  </div>

  @if($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="POST" action="{{ route('gdf.subdirection.rubros.update', $rubro->id) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Código</label>
            <input name="code" class="form-control" value="{{ old('code', $rubro->code) }}">
          </div>
          <div class="col-md-7">
            <label class="form-label">Nombre *</label>
            <input name="name" class="form-control" value="{{ old('name', $rubro->name) }}" required>
          </div>
          <div class="col-md-2 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $rubro->active) ? 'checked':'' }}>
              <label class="form-check-label">Activo</label>
            </div>
          </div>
        </div>

        <div class="mt-3 d-flex gap-2">
          <a href="{{ route('gdf.subdirection.rubros') }}" class="btn btn-outline-secondary">Volver</a>
          <button class="btn btn-primary">Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
