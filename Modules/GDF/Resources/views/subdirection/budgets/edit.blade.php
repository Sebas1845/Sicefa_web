{{-- Modules/GDF/Resources/views/subdirection/budgets/edit.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Editar presupuesto #'.($budget->id ?? '—'))

@section('content')
@php
  $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
  if(!$ok) abort(403);

  $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

  // helper defensivo stdClass
  $safe = function($obj, $prop, $default=null){
    return (is_object($obj) && isset($obj->{$prop})) ? $obj->{$prop} : $default;
  };

  $budgetId = $safe($budget,'id');
  $year     = $safe($budget,'year', now()->year);

  $itemName = $safe($budget,'budget_item_name');
  $itemCode = $safe($budget,'budget_item_code');
@endphp

<div class="container py-4">
  <div class="mb-3 d-flex justify-content-between align-items-start">
    <div>
      <div class="text-muted small">GDF · Subdirección</div>
      <h4 class="mb-1">Editar presupuesto #{{ $budgetId }}</h4>
      <div class="text-muted small">
        Rubro:
        <strong>{{ $itemName ?? ('ID #'.($safe($budget,'budget_item_id','—'))) }}</strong>
        @if($itemCode)<span class="badge bg-dark ms-2">{{ $itemCode }}</span>@endif
      </div>
    </div>
  </div>

  @if($errors->any())
    <div class="alert alert-warning border-0 shadow-sm">
      <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  <div class="card shadow-sm">
    <div class="card-header fw-semibold">Valores del presupuesto</div>
    <div class="card-body">
      <form method="POST" action="{{ route('gdf.subdirection.budgets.update', $budgetId) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
          {{-- Solo lectura: Rubro --}}
          <div class="col-md-7">
            <label class="form-label">Rubro</label>
            <input class="form-control" value="{{ $itemName ?? '—' }}" readonly>
          </div>

          <div class="col-md-3">
            <label class="form-label">Total *</label>
            <input name="total_amount" class="form-control"
                   value="{{ old('total_amount', $safe($budget,'total_amount',0)) }}" required>
          </div>

          <div class="col-md-2">
            <label class="form-label">Disponible *</label>
            <input name="current_amount" class="form-control"
                   value="{{ old('current_amount', $safe($budget,'current_amount',0)) }}" required>
          </div>

          <div class="col-md-3">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="active" value="1"
                {{ old('active', (int)$safe($budget,'active',1)) ? 'checked' : '' }}>
              <label class="form-check-label">Activo</label>
            </div>
          </div>

          @if(isset($budget->notes))
            <div class="col-12">
              <label class="form-label">Notas (si existe columna)</label>
              <textarea name="notes" class="form-control" rows="3">{{ old('notes', $safe($budget,'notes','')) }}</textarea>
            </div>
          @endif
        </div>

        <div class="mt-3 d-flex gap-2">
          <a href="{{ route('gdf.subdirection.budgets.index', ['year'=>$year]) }}"
             class="btn btn-outline-secondary">Volver</a>
          <button class="btn btn-primary">Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
