{{-- Modules/GDF/Resources/views/subdirection/budgets/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Presupuestos')

@section('content')
@php
  use Illuminate\Support\Facades\Route;

  $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
  if(!$ok) abort(403);

  $year    = (int)($year ?? now()->year);
  $q       = (string)($q ?? request('q',''));
  $warning = $warning ?? null;

  $fmtMoney = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');

  $hasCreate = Route::has('gdf.subdirection.budgets.create');
  $hasEdit   = Route::has('gdf.subdirection.budgets.edit');
  $hasShow   = Route::has('gdf.subdirection.budgets.show');

  // helpers defensivos (stdClass)
  $safe = function($obj, $prop, $default=null){
    return (is_object($obj) && isset($obj->{$prop})) ? $obj->{$prop} : $default;
  };

  $kpiTotal     = (float)($kpiTotal ?? 0);
  $kpiAvailable = (float)($kpiAvailable ?? 0);
  $kpiExecuted  = (float)($kpiExecuted ?? 0);
@endphp

<div class="container py-4">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
      <div class="text-muted small">GDF · Subdirección</div>
      <h4 class="mb-1">Presupuestos</h4>
      
    </div>
    <div class="d-flex gap-2">
      @if($hasCreate)
        <a class="btn btn-primary" href="{{ route('gdf.subdirection.budgets.create', ['year'=>$year]) }}">
          + Nuevo
        </a>
      @endif
    </div>
  </div>

  {{-- Warnings --}}
  @if($warning)
    <div class="alert alert-warning border-0 shadow-sm">
      {{ $warning }}
    </div>
  @endif

  {{-- Flash --}}
  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  {{-- KPIs --}}
  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total</div>
          <div class="fs-5 fw-semibold">{{ $fmtMoney($kpiTotal) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Disponible</div>
          <div class="fs-5 fw-semibold">{{ $fmtMoney($kpiAvailable) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Ejecutado</div>
          <div class="fs-5 fw-semibold">{{ $fmtMoney($kpiExecuted) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Filters --}}
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <form method="GET" action="{{ route('gdf.subdirection.budgets.index') }}" class="row g-2 align-items-end">
        <div class="col-md-2">
          <label class="form-label small mb-1">Año</label>
          <input type="number" name="year" class="form-control" value="{{ $year }}" min="2000" max="2100">
        </div>

        <div class="col-md-8">
          <label class="form-label small mb-1">Buscar</label>
          <input name="q" class="form-control" value="{{ $q }}"
                 placeholder="Rubro (nombre/código) · Área · ID presupuesto">
          <small class="text-muted">
            Tip: puedes buscar por nombre/código del rubro o por nombre del área.
          </small>
        </div>

        <div class="col-md-2 d-grid">
          <button class="btn btn-outline-primary">Filtrar</button>
        </div>
      </form>
    </div>
  </div>

  {{-- Table --}}
  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px">ID</th>
            <th style="width:220px">Área</th>
            <th>Rubro</th>
            <th style="width:140px">Código</th>
            <th style="width:160px" class="text-end">Total</th>
            <th style="width:160px" class="text-end">Disponible</th>
            <th style="width:120px">Activo</th>
            <th style="width:180px">Acciones</th>
          </tr>
        </thead>
        <tbody>
        @forelse(($budgets ?? collect()) as $b)
          @php
            $budgetId = $safe($b,'id', 0);
            $areaName = $safe($b,'area_name', '—');

            $itemId   = $safe($b,'budget_item_id');
            $itemName = $safe($b,'budget_item_name');
            $itemCode = $safe($b,'budget_item_code');

            $total    = $safe($b,'total_amount', 0);
            $current  = $safe($b,'current_amount', 0);
            $active   = (int)$safe($b,'active', 0);
          @endphp

          <tr>
            <td class="fw-semibold">#{{ $budgetId }}</td>

            <td class="fw-semibold">
              {{ $areaName }}
            </td>

            <td class="fw-semibold">
              {{ $itemName ?? ('Rubro #'.$itemId) }}
              @if($safe($b,'notes'))
                <div class="text-muted small text-truncate" style="max-width:520px;">
                  {{ $safe($b,'notes') }}
                </div>
              @endif
            </td>

            <td>
              @if(!empty($itemCode))
                <span class="badge bg-dark">{{ $itemCode }}</span>
              @else
                <span class="text-muted small">—</span>
              @endif
            </td>

            <td class="text-end">{{ $fmtMoney($total) }}</td>
            <td class="text-end">{{ $fmtMoney($current) }}</td>

            <td>
              <span class="badge {{ $active ? 'bg-success' : 'bg-secondary' }}">
                {{ $active ? 'Sí' : 'No' }}
              </span>
            </td>

            <td>
              <div class="d-flex gap-2 flex-wrap">
                @if($hasShow && $budgetId)
                  <a class="btn btn-outline-secondary btn-sm"
                     href="{{ route('gdf.subdirection.budgets.show', $budgetId) }}">
                    Ver
                  </a>
                @endif

                @if($hasEdit && $budgetId)
                  <a class="btn btn-outline-primary btn-sm"
                     href="{{ route('gdf.subdirection.budgets.edit', $budgetId) }}">
                    Editar
                  </a>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="8" class="text-center text-muted py-4">
              Sin presupuestos para mostrar.
            </td>
          </tr>
        @endforelse
        </tbody>
      </table>
    </div>

    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
      <div class="text-muted small">
        @php
          $count = is_object($budgets ?? null) && method_exists($budgets,'total')
            ? (int)$budgets->total()
            : (is_iterable($budgets ?? null) ? count($budgets) : 0);
        @endphp
        Total: <strong>{{ $count }}</strong>
      </div>

      <div>
        @if(is_object($budgets ?? null) && method_exists($budgets,'links'))
          {{ $budgets->appends(request()->query())->links() }}
        @else
          <span class="text-muted small">Sin paginación.</span>
        @endif
      </div>
    </div>
  </div>

</div>
@endsection
