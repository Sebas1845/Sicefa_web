{{-- Modules/GDF/Resources/views/subdirection/motorcycles/quotas.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title','GDF | Cupos de motos por área')

@section('content')
@php
    $isSubdirection = function_exists('checkRol') ? (checkRol('gdf.subdirection') || checkRol('gdf.superadmin')) : false;
    if(!$isSubdirection){ abort(403); }

    $year = $year ?? (int) request('year', now()->year);
    $years = $years ?? collect(range(now()->year - 2, now()->year + 1));

    $areas = $areas ?? collect();

    // Esperado: $quotasByArea[area_id] => ['quota'=>int, 'used'=>int|null]
    // Si llega como Collection de modelos, conviértelo a map por area_id
    if (!isset($quotasByArea)) {
        $quotasByArea = collect();
    }
    if ($quotasByArea instanceof \Illuminate\Support\Collection && $quotasByArea->isNotEmpty() && is_object($quotasByArea->first()) && isset($quotasByArea->first()->area_id)) {
        $quotasByArea = $quotasByArea->keyBy('area_id')->map(function($q){
            return [
                'quota' => (int) ($q->quota ?? 0),
                'used'  => isset($q->used) ? (int) $q->used : null,
            ];
        });
    }
@endphp

<div class="container py-4">

    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
            <div class="text-muted small">GDF / Subdirección</div>
            <h4 class="fw-bold mb-1">Cupos de motos por área</h4>
            <div class="text-muted small">
                Define cupos globales por área y año. El inventario/transferencias respetan estos cupos.
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('gdf.subdirection.motorcycles.index') }}" class="btn btn-outline-secondary">
                Volver a inventario
            </a>
        </div>
    </div>

    @foreach (['success'=>'success','warning'=>'warning','info'=>'info','error'=>'danger'] as $k=>$type)
        @if(session($k))
            <div class="alert alert-{{ $type }} border-0 shadow-sm">{{ session($k) }}</div>
        @endif
    @endforeach

    {{-- Filtros --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted mb-1">Año</label>
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
                        Sugerencia: define primero cupos del año {{ $year }} y luego gestiona inventario/transferencias.
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-white">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="fw-semibold">Cupos por área ({{ $year }})</div>
                <div class="text-muted small">
                    @php
                        $totalQuota = 0;
                        $totalUsed  = 0;
                        foreach($areas as $a){
                            $row = $quotasByArea[$a->id] ?? ['quota'=>0,'used'=>null];
                            $totalQuota += (int) ($row['quota'] ?? 0);
                            if(!is_null($row['used'] ?? null)) $totalUsed += (int) $row['used'];
                        }
                    @endphp
                    Total cupos: <strong>{{ $totalQuota }}</strong>
                    @if($totalUsed>0)
                        · Total asignadas: <strong>{{ $totalUsed }}</strong>
                    @endif
                </div>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width: 260px;">Área</th>
                            <th class="text-center" style="width: 140px;">Cupo</th>
                            <th class="text-center" style="width: 180px;">Asignadas</th>
                            <th class="text-center" style="width: 240px;">Actualizar cupo</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($areas as $area)
                            @php
                                $row  = $quotasByArea[$area->id] ?? ['quota'=>0,'used'=>null];
                                $quota = (int) ($row['quota'] ?? 0);
                                $used  = $row['used'] ?? null;

                                $pct = null;
                                if(!is_null($used) && $quota > 0){
                                    $pct = min(100, max(0, (int) round(($used / $quota) * 100)));
                                }

                                // badges por estado
                                $badge = 'secondary';
                                if(!is_null($used) && $quota > 0){
                                    if($used >= $quota) $badge = 'danger';
                                    elseif($pct >= 80)  $badge = 'warning';
                                    else                $badge = 'success';
                                }
                            @endphp

                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $area->name }}</div>
                                    <div class="text-muted small">
                                        {{ $area->key ?? ('ID: '.$area->id) }}
                                    </div>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-{{ $badge }}">{{ $quota }}</span>
                                </td>

                                <td class="text-center">
                                    @if(is_null($used))
                                        <span class="text-muted small">—</span>
                                    @else
                                        <div class="d-flex flex-column align-items-center gap-1">
                                            <div class="small">
                                                <strong>{{ $used }}</strong>
                                                <span class="text-muted">/ {{ max(1,$quota) }}</span>
                                                @if(!is_null($pct))
                                                    <span class="text-muted">({{ $pct }}%)</span>
                                                @endif
                                            </div>

                                            @if(!is_null($pct))
                                                <div class="progress" style="height: 7px; width: 140px;">
                                                    <div class="progress-bar bg-{{ $badge }}" role="progressbar"
                                                         style="width: {{ $pct }}%;"
                                                         aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </td>

                                <td>
                                    <form method="POST" action="{{ route('motorcycles.quotas.store') }}" class="d-flex gap-2">
                                        @csrf
                                        <input type="hidden" name="year" value="{{ $year }}">
                                        <input type="hidden" name="area_id" value="{{ $area->id }}">

                                        <input type="number"
                                               name="quota"
                                               min="0"
                                               step="1"
                                               class="form-control form-control-sm"
                                               value="{{ old('quota', $quota) }}"
                                               required>

                                        <button class="btn btn-sm btn-primary">
                                            Guardar
                                        </button>
                                    </form>

                                    @if(!is_null($used) && $used > $quota)
                                        <div class="text-danger small mt-1">
                                            Cupo insuficiente para las asignaciones actuales ({{ $used }}).
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No hay áreas configuradas.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>
    </div>

    <div class="text-muted small">
        Importante: Subdirección define cupos. Coordinación y Apoyo realizan la asignación/entrega respetando cupos.
    </div>

</div>
@endsection
