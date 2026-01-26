@extends('gdf::layouts.masteruser')
@section('title','GDF | ' . ($title ?? 'Apoyo'))

@section('content')
@php
  // Detecta área por URL (academic/campesena) y construye routePrefix
  $areaKey = $areaKey ?? (str_contains(request()->path(), 'gdf/campesena') ? 'campesena' : 'academic');
  $routePrefix = $routePrefix ?? ($areaKey === 'campesena' ? 'gdf.campesena' : 'gdf.academic');
  $title = $title ?? ($areaKey === 'campesena' ? 'Apoyo Campesena' : 'Apoyo Coordinación Académica');

  $isSupport = function_exists('checkRol')
      ? ($areaKey === 'campesena' ? checkRol('gdf.campesena_support') : checkRol('gdf.academic_support'))
      : false;
  if(!$isSupport){ abort(403); }

  $kpis = $kpis ?? [
    'needs_motorcycle' => 0,
    'to_deliver' => 0,
    'delivered' => 0,
  ];
@endphp

<div class="container py-4">

  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="text-muted small">GDF / {{ $title }}</div>
      <h3 class="fw-bold mb-1">Panel de Apoyo</h3>
      <div class="text-muted">Operación: asignar moto, entregar, recibir, registrar novedades.</div>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-outline-primary" href="{{ route($routePrefix.'.motorcycles.queue') }}">Cola de motos</a>
      <a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.people.index') }}">Personas</a>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  <div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Aprobadas sin moto</div>
          <div class="fw-bold fs-3">{{ $kpis['needs_motorcycle'] ?? 0 }}</div>
          <div class="text-muted small mt-2">Pendientes de asignación.</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Listas para entregar</div>
          <div class="fw-bold fs-3">{{ $kpis['to_deliver'] ?? 0 }}</div>
          <div class="text-muted small mt-2">Aprobadas con moto asignada.</div>
        </div>
      </div>
    </div>

    <div class="col-sm-6 col-xl-4">
      <div class="card shadow-sm border-0 h-100">
        <div class="card-body">
          <div class="text-muted small">Entregadas</div>
          <div class="fw-bold fs-3">{{ $kpis['delivered'] ?? 0 }}</div>
          <div class="text-muted small mt-2">Pendientes de devolución.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center">
      <div>
        <div class="fw-bold">Ir a la operación</div>
        <div class="text-muted small">Gestiona asignación, entrega y devolución desde una sola cola.</div>
      </div>
      <a class="btn btn-primary" href="{{ route($routePrefix.'.motorcycles.queue') }}">Abrir cola</a>
    </div>
  </div>

</div>
@endsection
