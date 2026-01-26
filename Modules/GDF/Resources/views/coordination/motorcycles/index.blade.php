{{-- Modules/GDF/Resources/views/coordination/motorcycles/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Motos')

@section('content')
@php
  $areaKey = $areaKey ?? (str_contains(request()->path(), 'gdf/campesena') ? 'campesena' : 'academic');

  // ✅ Prefijo correcto con tus rutas actuales
  $routePrefix = 'gdf.' . ($areaKey === 'campesena' ? 'campesena' : 'academic');

  $title = $title ?? ($areaKey === 'campesena' ? 'Motos Campesena' : 'Motos Coordinación Académica');

  $rReview = $routePrefix.'.review';

  $rQuota  = $routePrefix.'.motorcycles.quota_status';
  $rAssign = $routePrefix.'.motorcycles.assign.create';
  $rQueue  = $routePrefix.'.motorcycles.queue';

  // (Opcional) si agregas inventario/crear moto:
  $rMotoCreate = $routePrefix.'.motorcycles.create';

  $hasQuota  = \Illuminate\Support\Facades\Route::has($rQuota);
  $hasAssign = \Illuminate\Support\Facades\Route::has($rAssign);
  $hasQueue  = \Illuminate\Support\Facades\Route::has($rQueue);
  $hasCreate = \Illuminate\Support\Facades\Route::has($rMotoCreate);

  $year = (int) request('year', now()->year);
@endphp

<div class="container py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / {{ $title }}</div>
      <h4 class="mb-0">Gestión de motos</h4>
      <div class="text-muted small">Cupo, asignación y (opcional) operación de apoyo.</div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
      @if(\Illuminate\Support\Facades\Route::has($rReview))
        <a class="btn btn-outline-secondary" href="{{ route($rReview) }}">Volver a revisión</a>
      @endif

      {{-- Botón crear moto (inventario) — recomendado para Apoyo --}}
      @if($hasCreate)
        <a class="btn btn-outline-primary" href="{{ route($rMotoCreate) }}">+ Crear moto</a>
      @endif
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
               href="{{ route($rQuota, ['area'=>$areaKey,'year'=>$year]) }}">
              Ver cupo
            </a>
          @else
            <button class="btn btn-outline-warning w-100" disabled>Ruta no disponible</button>
            <div class="text-muted small mt-2"><code>{{ $rQuota }}</code></div>
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
               href="{{ route($rAssign, ['area'=>$areaKey,'year'=>$year]) }}">
              + Asignar
            </a>
          @else
            <button class="btn btn-primary w-100" disabled>Ruta no disponible</button>
            <div class="text-muted small mt-2"><code>{{ $rAssign }}</code></div>
          @endif
        </div>
      </div>
    </div>

    {{-- Si NO quieres la cola operativa, puedes ocultar este bloque completo --}}
    <div class="col-md-4">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="fw-bold mb-1">Cola operativa (Apoyo)</div>
          <div class="text-muted small mb-3">Entregar, recibir, cancelar.</div>

          @if($hasQueue)
            <a class="btn btn-outline-primary w-100" href="{{ route($rQueue) }}">
              Abrir cola
            </a>
          @else
            <button class="btn btn-outline-primary w-100" disabled>Ruta no disponible</button>
            <div class="text-muted small mt-2"><code>{{ $rQueue }}</code></div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
