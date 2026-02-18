{{-- Modules/GDF/Resources/views/subdirection/movements/index.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Movimientos')

@section('content')
@php
  use Modules\GDF\Status\MovementType;
  use Modules\GDF\Status\MovementModule;

  $ok = function_exists('checkRol') && (checkRol('gdf.subdirection') || checkRol('gdf.superadmin'));
  if(!$ok) abort(403);

  $year   = (int)($year ?? request('year', now()->year));
  $q      = (string)($q ?? request('q',''));
  $type   = (string)($type ?? request('type',''));
  $module = (string)($module ?? request('module',''));
  $areaId = (int)($areaId ?? request('area_id', 0));
  $itemId = (int)($itemId ?? request('budget_item_id', 0));

  $rows  = $rows ?? collect();
  $areas = $areas ?? collect();
  $items = $items ?? collect();

  $money = fn($n) => '$ ' . number_format((float)($n ?? 0), 0, ',', '.');
  $isPaginator = fn($x) => is_object($x) && method_exists($x,'links');

  $totalRows = $isPaginator($rows) ? $rows->total() : (is_countable($rows) ? count($rows) : 0);

  $types = ['addition','commitment','reversal','adjustment','execute'];
@endphp

<style>
  .table-compact td, .table-compact th { padding: .45rem .55rem; }
  .mini { font-size: .82rem; }
  .muted { color: rgba(0,0,0,.60); }
  .chip { display:inline-flex; align-items:center; gap:.35rem; padding:.18rem .55rem; border-radius:999px; border:1px solid rgba(0,0,0,.08); background:#fff; font-size:.80rem; }
  .card-soft { border-radius: 16px; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 10px 24px rgba(0,0,0,.05); }
</style>

<div class="container py-3">

  {{-- Header --}}
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / Subdirección</div>
      <div class="d-flex align-items-center gap-2">
        <h5 class="mb-0">Movimientos</h5>
        <span class="chip">Año <b>{{ $year }}</b></span>
        <span class="chip">Total <b>{{ $totalRows }}</b></span>
      </div>
    </div>

    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('gdf.subdirection.dashboard', ['year'=>$year]) }}">
        <i class="bi bi-speedometer2 me-1"></i> Dashboard
      </a>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('gdf.subdirection.rubros', ['year'=>$year]) }}">
        <i class="bi bi-tags me-1"></i> Rubros
      </a>
    </div>
  </div>

  {{-- Alerts --}}
  @foreach (['success','error','warning','info'] as $k)
    @if(session($k))
      <div class="alert alert-{{ $k==='error'?'danger':$k }} py-2 mb-3">{{ session($k) }}</div>
    @endif
  @endforeach

  {{-- Filtros compactos --}}
  <div class="card card-soft mb-3">
    <div class="card-body py-3">
      <form method="GET" action="{{ route('gdf.subdirection.movements.index') }}">
        <div class="row g-2 align-items-end">
          <div class="col-6 col-md-2">
            <label class="form-label mini mb-1">Año</label>
            <input name="year" type="number" class="form-control form-control-sm" value="{{ $year }}">
          </div>

          <div class="col-6 col-md-3">
            <label class="form-label mini mb-1">Tipo</label>
            <select name="type" class="form-select form-select-sm">
              <option value="" @selected($type==='')>Todos</option>
              @foreach($types as $t)
                <option value="{{ $t }}" @selected($type===$t)>{{ MovementType::label($t) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mini mb-1">Módulo</label>
            <select name="module" class="form-select form-select-sm">
              <option value="" @selected($module==='')>Todos</option>
              <option value="gdf" @selected($module==='gdf')>GDF</option>
              <option value="sitrav" @selected($module==='sitrav')>SITRAV</option>
            </select>
          </div>

          <div class="col-6 col-md-2">
            <label class="form-label mini mb-1">Área</label>
            <select name="area_id" class="form-select form-select-sm">
              <option value="0" @selected($areaId===0)>Todas</option>
              @foreach($areas as $a)
                <option value="{{ $a->id }}" @selected((int)$a->id===$areaId)>{{ $a->name ?? ('Área #'.$a->id) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-3">
            <label class="form-label mini mb-1">Rubro</label>
            <select name="budget_item_id" class="form-select form-select-sm">
              <option value="0" @selected($itemId===0)>Todos</option>
              @foreach($items as $it)
                @php $label = trim(($it->code ?? '').' '.($it->name ?? '')); @endphp
                <option value="{{ $it->id }}" @selected((int)$it->id===$itemId)>{{ $label ?: ('Rubro #'.$it->id) }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-9">
            <label class="form-label mini mb-1">Buscar</label>
            <input name="q" class="form-control form-control-sm" value="{{ $q }}" placeholder="ID, budget_id, tr_id, descripción...">
          </div>

          <div class="col-12 col-md-3 d-grid">
            <button class="btn btn-sm btn-primary">
              <i class="bi bi-funnel me-1"></i> Filtrar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  {{-- Tabla compacta --}}
  <div class="card card-soft">
    <div class="table-responsive">
      <table class="table table-hover table-compact align-middle mb-0">
        <thead class="table-light">
          <tr class="mini">
            <th style="width:78px">ID</th>
            <th>Detalle</th>
            <th style="width:150px">Área</th>
            <th style="width:220px">Rubro</th>
            <th style="width:140px">Tipo</th>
            <th style="width:120px">Módulo</th>
            <th style="width:150px" class="text-end">Valor</th>
            <th style="width:170px">Fecha</th>
          </tr>
        </thead>
        <tbody>
        @forelse($rows as $m)
          @php
            $typeLbl = MovementType::label($m->type ?? null);
            $typeBg  = MovementType::badge($m->type ?? null);
            $typeIco = MovementType::icon($m->type ?? null);

            // si no quieres MovementModule.php, puedes dejar módulo como strtoupper
            $modLbl = class_exists(MovementModule::class) ? MovementModule::label($m->module ?? null) : strtoupper((string)($m->module ?? '—'));
            $modBg  = class_exists(MovementModule::class) ? MovementModule::badge($m->module ?? null) : 'light text-dark';

            $rubro = trim(($m->budget_item_code ?? '').' '.($m->budget_item_name ?? ''));
          @endphp

          <tr>
            <td class="fw-semibold">#{{ $m->id }}</td>

            <td>
              <div class="fw-semibold">{{ $m->created_by_name ?? '—' }}</div>
              <div class="mini muted">
                budget: <b>{{ $m->budget_id ?? '—' }}</b>
                · tr: <b>{{ $m->travel_request_id ?? '—' }}</b>
                @if(!empty($m->source_type))
                  · src: <b>{{ $m->source_type }}</b>
                  @if(!empty($m->source_id)) #{{ $m->source_id }} @endif
                @endif
              </div>
              @if(!empty($m->description))
                <div class="mini muted text-truncate" style="max-width: 760px;">{{ $m->description }}</div>
              @endif
            </td>

            <td class="mini">{{ $m->area_name ?? '—' }}</td>

            <td class="mini">
              <div class="fw-semibold">{{ $rubro ?: '—' }}</div>
            </td>

            <td>
              <span class="badge bg-{{ $typeBg }}">
                <i class="{{ $typeIco }} me-1"></i>{{ $typeLbl }}
              </span>
            </td>

            <td>
              @php
                // soporte para "light text-dark" (dos clases)
                $isCompound = is_string($modBg) && str_contains($modBg, ' ');
                $bg = $isCompound ? 'light' : $modBg;
              @endphp
              <span class="badge bg-{{ $bg }} {{ $isCompound ? $modBg : '' }}">{{ $modLbl }}</span>
            </td>

            <td class="text-end fw-semibold">{{ $money($m->amount ?? 0) }}</td>
            <td class="mini">{{ $m->created_at ?? '—' }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="text-center text-muted py-4">Sin movimientos.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @if($isPaginator($rows))
      <div class="card-footer bg-white py-2">
        {{ $rows->appends(request()->query())->links() }}
      </div>
    @endif
  </div>

</div>
@endsection
