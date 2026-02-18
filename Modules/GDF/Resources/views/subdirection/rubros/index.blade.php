@extends('gdf::layouts.masteruser')
@section('title','GDF | Subdirección · Rubros')

@section('content')
@php
  $money = fn($n) => '$ ' . number_format((float)$n, 0, ',', '.');

  // ✅ Helper: rubro sin presupuesto asignado en la vigencia
  $noBudget = fn($r) => (float)($r->total ?? 0) <= 0;
@endphp

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
      <div class="text-muted small">GDF / Subdirección</div>
      <h4 class="mb-1">Rubros</h4>
      <div class="text-muted small">Vigencia: <strong>{{ $year }}</strong></div>
    </div>

    <div class="d-flex gap-2">
      {{-- ✅ Crear rubro --}}
      <a href="{{ route('gdf.subdirection.rubros.create') }}" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> Crear rubro
      </a>

      {{-- Año --}}
      <form method="GET" class="d-flex gap-2">
        <input class="form-control" type="number" name="year" value="{{ $year }}" style="width:140px">
        <button class="btn btn-primary" type="submit">
          <i class="bi bi-search me-1"></i> Ir
        </button>
      </form>
    </div>
  </div>

  @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
    @if(session($k))
      <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
    @endif
  @endforeach

  <div class="row g-2 mb-3">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Disponible</div>
          <div class="fs-5 fw-semibold">{{ $money($globalAvail) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Ejecutado</div>
          <div class="fs-5 fw-semibold">{{ $money($globalSpent) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-muted small">Total</div>
          <div class="fs-5 fw-semibold">{{ $money($globalTotal) }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="table-responsive">
      <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:90px">ID</th>
            <th>Rubro</th>
            <th class="text-end">Disponible</th>
            <th class="text-end">Ejecutado</th>
            <th class="text-end">Total</th>
            <th class="text-end">Áreas</th>
            <th class="text-end">Movimientos</th>
            <th class="text-end" style="width:140px">Acciones</th>
          </tr>
        </thead>
        <tbody>
          @forelse($rows as $r)
            @php $isNoBudget = $noBudget($r); @endphp
            <tr>
              <td class="fw-semibold">#{{ $r->id }}</td>
              <td>
                <div class="fw-semibold">
                  {{ $r->code ? ($r->code.' - ') : '' }}{{ $r->name }}

                  {{-- ✅ Etiqueta: sin presupuesto --}}
                  @if($isNoBudget)
                    <span class="badge bg-warning text-dark ms-2">
                      Sin presupuesto ({{ $year }})
                    </span>
                  @endif
                </div>
              </td>

              <td class="text-end fw-semibold">
                {{ $money($r->avail) }}
              </td>
              <td class="text-end fw-semibold">
                {{ $money($r->spent) }}
              </td>
              <td class="text-end fw-semibold">
                {{ $money($r->total) }}
              </td>

              <td class="text-end">{{ (int)$r->areas_count }}</td>
              <td class="text-end">{{ (int)$r->moves_count }}</td>

              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary"
                   href="{{ route('gdf.subdirection.rubros.edit', $r->id) }}">
                  <i class="bi bi-pencil-square me-1"></i> Editar
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="8" class="text-center text-muted py-4">Sin datos</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

</div>
@endsection
