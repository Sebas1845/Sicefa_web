{{-- Modules/GDF/Resources/views/coordination/motorcycles/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Motos')

@section('content')
@php
  $areaKey = $areaKey ?? (str_contains(request()->path(), 'gdf/campesena') ? 'campesena' : 'academic');
  $routePrefix = $routePrefix ?? $areaKey;

  $title = $title ?? ($areaKey === 'campesena' ? 'Motos Campesena' : 'Motos Coordinación Académica');

  $hasAssign = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.assign.create');
  $hasQuota  = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.quota_status');
  $hasQueue  = \Illuminate\Support\Facades\Route::has($routePrefix.'.motorcycles.queue');
@endphp

<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / {{ $title }}</div>
      <h4 class="mb-0">Gestión de motos</h4>
      <div class="text-muted small">Acciones principales para coordinación y apoyo.</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary" href="{{ route('gdf.'.$routePrefix.'.review') }}">Volver a revisión</a>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  <div class="row g-3">
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="fw-bold mb-1">Cupo del área</div>
          <div class="text-muted small mb-3">Ver cupo configurado vs motos ubicadas en el área.</div>
          @if($hasQuota)
            <a class="btn btn-outline-warning w-100"
               href="{{ route($routePrefix.'.motorcycles.quota_status', ['area'=>$areaKey,'year'=>now()->year]) }}">
              Ver cupo
            </a>
          @else
            <button class="btn btn-outline-warning w-100" disabled>Ruta no disponible</button>
          @endif
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="fw-bold mb-1">Asignación directa</div>
          <div class="text-muted small mb-3">Asignar una moto a una persona (validando cupo).</div>
          @if($hasAssign)
            <a class="btn btn-primary w-100"
               href="{{ route($routePrefix.'.motorcycles.assign.create', ['area'=>$areaKey,'year'=>now()->year]) }}">
              + Asignar
            </a>
          @else
            <button class="btn btn-primary w-100" disabled>Ruta no disponible</button>
          @endif
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="fw-bold mb-1">Cola operativa (Apoyo)</div>
          <div class="text-muted small mb-3">Entregar, recibir, cancelar, asignar moto.</div>
          @if($hasQueue)
            <a class="btn btn-outline-primary w-100"
               href="{{ route($routePrefix.'.motorcycles.queue', ['area'=>$areaKey]) }}">
              Abrir cola
            </a>
          @else
            <button class="btn btn-outline-primary w-100" disabled>Ruta no disponible</button>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
