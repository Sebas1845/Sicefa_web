@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Distribución por áreas')

@section('content')
@php
    // Seguridad extra
    $isSubdirection = function_exists('checkRol') ? checkRol('gdf.subdirection') : false;
    if(!$isSubdirection){ abort(403); }

    // Totales para el resumen (pool compartido)
    $allocatedSum = collect($rows ?? [])->sum(fn($r) => (float)($r['allocated_amount'] ?? 0));
    $unallocated  = max(0, ((float)($budget->current_amount ?? 0)) - (float)$allocatedSum);

    // Si tu presupuesto “total” debe ser total_amount o current_amount, aquí decides:
    $budgetBase = (float)($budget->current_amount ?? $budget->total_amount ?? 0);
@endphp

<div class="container py-4">

    <div class="mb-3">
        <a href="{{ route('gdf.subdirection.budgets.index') }}"
           class="btn btn-sm btn-outline-secondary">
            ← Volver
        </a>
    </div>

    <div class="mb-4">
        <h4 class="mb-1">Distribución por áreas</h4>
        <div class="text-muted">
            Rubro: <strong>{{ $budget->budgetItem->name ?? '—' }}</strong> ·
            Año: <strong>{{ $budget->year ?? '—' }}</strong>
        </div>
    </div>

    {{-- Alerts --}}
    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    {{-- Resumen --}}
    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card text-center shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted">Presupuesto base</div>
                    <h5 class="mb-0">$ {{ number_format($budgetBase, 0, ',', '.') }}</h5>
                    <div class="text-muted small mt-1">Usado para repartir por % de áreas.</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card text-center shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted">Asignado a áreas</div>
                    <h5 class="mb-0">$ {{ number_format($allocatedSum, 0, ',', '.') }}</h5>
                    <div class="text-muted small mt-1">Suma de allocated_amount.</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card text-center shadow-sm border-0">
                <div class="card-body">
                    <div class="text-muted">Sin asignar</div>
                    <h5 class="mb-0">$ {{ number_format($unallocated, 0, ',', '.') }}</h5>
                    <div class="text-muted small mt-1">Base - asignado.</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Formulario --}}
    <form method="POST" action="{{ route('gdf.subdirection.budgets.areas.store', $budget->id) }}">
        @csrf

        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">Áreas</div>
                    <div class="text-muted small">El disponible es un pool compartido: GDF + SITRAV descuentan del mismo cupo del área.</div>
                </div>
                <span class="badge bg-primary">Total áreas: {{ count($rows ?? []) }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Área</th>
                            <th class="text-end" style="width:140px;">% Área</th>
                            <th class="text-end" style="width:170px;">Asignado</th>
                            <th class="text-end" style="width:170px;">Ejecutado (Total)</th>
                            <th class="text-end" style="width:170px;">Disponible</th>
                            <th class="text-center" style="width:110px;">Activa</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($rows as $i => $r)
                        @php
                            $pct   = (float)($r['percentage'] ?? 0);
                            $alloc = (float)($r['allocated_amount'] ?? 0);
                            $spent = (float)($r['spent_total'] ?? 0);
                            $avail = (float)($r['avail'] ?? 0);
                            $active = (bool)($r['active'] ?? true);
                        @endphp
                        <tr>
                            <td class="fw-semibold">
                                {{ $r['name'] ?? '—' }}
                                <input type="hidden" name="areas[{{ $i }}][area_id]" value="{{ $r['area_id'] }}">
                            </td>

                            <td class="text-end">
                                <input type="number"
                                       step="0.01"
                                       min="0"
                                       max="100"
                                       name="areas[{{ $i }}][percentage]"
                                       class="form-control text-end"
                                       value="{{ old("areas.$i.percentage", number_format($pct, 2, '.', '')) }}">
                            </td>

                            <td class="text-end">
                                <span class="fw-semibold">$ {{ number_format($alloc, 0, ',', '.') }}</span>
                                <div class="text-muted small">Auto (base × %).</div>
                            </td>

                            <td class="text-end">
                                $ {{ number_format($spent, 0, ',', '.') }}
                                @if(isset($r['spent_gdf']) || isset($r['spent_sitrav']))
                                    <div class="text-muted small">
                                        GDF: ${{ number_format((float)($r['spent_gdf'] ?? 0), 0, ',', '.') }}
                                        · SITRAV: ${{ number_format((float)($r['spent_sitrav'] ?? 0), 0, ',', '.') }}
                                    </div>
                                @endif
                            </td>

                            <td class="text-end">
                                <span class="{{ $avail < 0 ? 'text-danger fw-bold' : 'fw-semibold' }}">
                                    $ {{ number_format($avail, 0, ',', '.') }}
                                </span>
                                @if($avail < 0)
                                    <div class="text-danger small">Sobregiro</div>
                                @endif
                            </td>

                            <td class="text-center">
                                {{-- Importante: hidden para que se envíe 0 si está desmarcado --}}
                                <input type="hidden" name="areas[{{ $i }}][active]" value="0">
                                <input type="checkbox"
                                       class="form-check-input"
                                       name="areas[{{ $i }}][active]"
                                       value="1"
                                       {{ old("areas.$i.active", $active ? 1 : 0) ? 'checked' : '' }}>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No hay áreas registradas.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Regla: la suma de % debe ser 100%.
                </div>
                <button class="btn btn-primary">
                    Guardar distribución
                </button>
            </div>
        </div>
    </form>

</div>
@endsection
