{{-- Modules/GDF/Resources/views/subdirection/motorcycles/quotas.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Cupos de motos por área')

@section('content')
@php
    $year  = $year ?? (int) request('year', now()->year);
    $years = $years ?? collect(range(now()->year - 2, now()->year + 1));

    $areas = $areas ?? collect();

    // Esperado:
    // $quotasByArea[area_id] = ['quota_total' => int, 'used' => int]
    $quotasByArea = $quotasByArea ?? [];
@endphp

<div class="container py-4">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Cupos de motos por área</h4>
            <div class="text-muted small">
                Define cupos globales por área y año. Inventario/transferencias respetan estos cupos.
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('gdf.subdirection.motorcycles.index') }}" class="btn btn-outline-secondary">
                Volver a inventario
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    {{-- Validation errors --}}
    @if ($errors->any())
        <div class="alert alert-danger border-0 shadow-sm">
            <div class="fw-semibold mb-1">Revisa los campos:</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Filtro de año --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label mb-1">Año</label>
                    <select name="year" class="form-select">
                        @foreach($years as $y)
                            <option value="{{ $y }}" @selected((int)$y === (int)$year)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <button class="btn btn-primary w-100">Aplicar</button>
                </div>

                <div class="col-12 col-md-6 text-md-end">
                    <div class="text-muted small mt-2 mt-md-0">
                        Se recomienda definir cupos antes de asignar/transferir.
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla cupos --}}
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="fw-bold">Cupos por área ({{ $year }})</div>
            <div class="text-muted small">
                Cupo = máximo permitido en el área para el año seleccionado.
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="min-width: 240px;">Área</th>
                        <th class="text-center" style="width: 120px;">Cupo</th>
                        <th class="text-center" style="width: 120px;">En área</th>
                        <th class="text-center" style="width: 120px;">Disponible</th>
                        <th style="min-width: 360px;">Actualizar cupo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($areas as $area)
                        @php
                            $row = $quotasByArea[$area->id] ?? ['quota_total' => 0, 'used' => 0];

                            $quota = (int) ($row['quota_total'] ?? 0);
                            $used  = (int) ($row['used'] ?? 0);

                            $available = max(0, $quota - $used);

                            $badge = 'secondary';
                            if ($quota === 0 && $used > 0) $badge = 'danger';
                            elseif ($quota > 0 && $used > $quota) $badge = 'danger';
                            elseif ($quota > 0 && $used >= (int)round($quota * 0.8)) $badge = 'warning';
                            elseif ($quota > 0) $badge = 'success';
                        @endphp

                        <tr>
                            <td class="fw-semibold">{{ $area->name }}</td>

                            <td class="text-center">
                                <span class="badge bg-{{ $badge }}">{{ $quota }}</span>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-info">{{ $used }}</span>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-dark">{{ $available }}</span>
                            </td>

                            <td>
                                <form method="POST"
                                      action="{{ route('gdf.subdirection.motorcycles.quotas.store') }}"
                                      class="d-flex flex-wrap gap-2 align-items-center">
                                    @csrf
                                    <input type="hidden" name="year" value="{{ $year }}">
                                    <input type="hidden" name="area_id" value="{{ $area->id }}">

                                    <div style="max-width: 140px;">
                                        <input type="number"
                                               name="quota_total"
                                               class="form-control form-control-sm"
                                               min="0" step="1"
                                               value="{{ old('quota_total', $quota) }}"
                                               required>
                                    </div>

                                    <div class="flex-grow-1" style="min-width: 220px;">
                                        <input type="text"
                                               name="notes"
                                               class="form-control form-control-sm"
                                               maxlength="2000"
                                               placeholder="Notas (opcional)"
                                               value="{{ old('notes') }}">
                                    </div>

                                    <button class="btn btn-sm btn-primary">
                                        Guardar
                                    </button>
                                </form>

                                @if($quota > 0 && $used > $quota)
                                    <div class="text-danger small mt-1">
                                        Cupo menor que las motos ya ubicadas en el área ({{ $used }}).
                                    </div>
                                @elseif($quota === 0 && $used > 0)
                                    <div class="text-warning small mt-1">
                                        Hay motos en el área pero el cupo está en 0. Define un cupo para evitar bloqueos.
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">
                                No hay áreas registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="text-muted small mt-3">
        Nota: Subdirección define cupos. Coordinación y Apoyo realizan asignación/entrega respetando cupos.
    </div>

</div>
@endsection
