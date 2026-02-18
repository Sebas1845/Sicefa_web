@extends('gdf::layouts.masteruser')
@section('title', 'SITRAV | Detalle de solicitud')

@section('content')
@php
    use Carbon\Carbon;
    use Modules\GDF\Services\GdfCostFormatter;
    use Modules\GDF\Status\AllowanceType;

    /** @var \Modules\GDF\Entities\TravelRequest $travel */
    $segments   = $travel->segments ?? collect();
    $costs      = $travel->costs ?? collect();
    $allowances = $travel->allowances ?? collect();

    $fmtMoney = fn($v) => '$ ' . number_format((float)$v, 0, ',', '.');

    $status = strtoupper((string)($travel->status ?? 'N/A'));
    $statusBadge = match ((string)$travel->status) {
        'submitted'  => 'info',
        'approved'   => 'primary',
        'confirmed'  => 'success',
        'returned'   => 'warning',
        'rejected'   => 'danger',
        default      => 'secondary',
    };

    $areaName = null;
    $budgetName = null;
    if (!empty($program)) {
        $areaName = $program->area->name ?? $program->area->nombre ?? null;
        $budgetName = $program->budgetItem->name ?? $program->budgetItem->nombre ?? null;
    }

    $destType = !empty($program) ? (((int)($program->village_id ?? 0) > 0) ? 'Vereda' : 'Municipio') : '—';

    $transportRow = $costs->firstWhere('cost_type', 'transport');
    $transportHtml = $transportRow ? GdfCostFormatter::transport($transportRow->description) : '—';

    $backUrl = route('gdf.instructor.dashboard');
@endphp

<div class="container py-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
        <div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h3 class="fw-bold mb-0">Detalle de solicitud SITRAV</h3>
                <span class="badge bg-{{ $statusBadge }}">{{ $status }}</span>
            </div>

            <div class="mt-2 d-flex gap-2 flex-wrap">
                <span class="badge bg-light text-dark">ID: {{ $travel->id }}</span>
                <span class="badge bg-light text-dark">Origen: {{ $travel->origin }}</span>
                <span class="badge bg-light text-dark">Destino: {{ $travel->destination }}</span>

                @if($areaName)
                    <span class="badge bg-light text-dark">Área: {{ $areaName }}</span>
                @endif
                @if($budgetName)
                    <span class="badge bg-light text-dark">Rubro: {{ $budgetName }}</span>
                @endif

                @if(!empty($program))
                    <span class="badge bg-light text-dark">SIGAC: #{{ $program->id }}</span>
                @endif
            </div>
        </div>

        <div>
            <a href="{{ $backUrl }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    {{-- Totales --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Transporte</div>
                    <div class="fs-4 fw-bold text-primary">{{ $fmtMoney($transport ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Viáticos</div>
                    <div class="fs-4 fw-bold text-success">{{ $fmtMoney($perDiem ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Otros</div>
                    <div class="fs-4 fw-bold">{{ $fmtMoney($other ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="text-muted small">Total</div>
                    <div class="fs-4 fw-bold">{{ $fmtMoney($total ?? 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Datos generales --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-info-circle text-primary"></i> Datos generales
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Fecha inicio</div>
                    <div class="fw-semibold">{{ $travel->start_date ? Carbon::parse($travel->start_date)->format('d/m/Y') : '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Fecha fin</div>
                    <div class="fw-semibold">{{ $travel->end_date ? Carbon::parse($travel->end_date)->format('d/m/Y') : '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Destino (tipo)</div>
                    <div class="fw-semibold">{{ $destType }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Transporte (config)</div>
                    <div class="fw-semibold">{!! $transportHtml !!}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Segmentos --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <div><i class="bi bi-signpost-split text-primary"></i> Fechas / Segmentos</div>
            <span class="badge bg-secondary">{{ $segments->where('is_cancelled',0)->count() }} segmento(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Salida</th>
                            <th>Regreso</th>
                            <th>Destino</th>
                            <th class="text-end">Transporte</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($segments->where('is_cancelled',0)->values() as $i => $s)
                            <tr>
                                <td><span class="badge bg-primary">{{ $i+1 }}</span></td>
                                <td>{{ $s->departure_at ? Carbon::parse($s->departure_at)->format('d/m/Y H:i') : '—' }}</td>
                                <td>{{ $s->return_at ? Carbon::parse($s->return_at)->format('d/m/Y H:i') : '—' }}</td>
                                <td>{{ $s->destination_display_name ?? $s->destination_place ?? $travel->destination }}</td>
                                <td class="text-end">{{ $fmtMoney($s->transport_cost ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Sin segmentos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Viáticos --}}
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-cash-coin text-success"></i> Viáticos
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th class="text-end">Unitario</th>
                            <th class="text-end">Unidades</th>
                            <th class="text-end">Total</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allowances as $a)
                            @php
                                $amt = $a->approved_amount !== null ? (float)$a->approved_amount : (float)($a->calculated_amount ?? 0);
                                $badge = match ((string)$a->status) {
                                    'approved' => 'success',
                                    'liquidated' => 'primary',
                                    'draft' => 'secondary',
                                    default => 'secondary',
                                };
                            @endphp
                            <tr>
                                <td><span class="badge bg-light text-dark">{{ strtoupper((string)($a->allowance_type ?? '—')) }}</span></td>
                                <td>{{ $a->description ?? '—' }}</td>
                                <td class="text-end">{{ $fmtMoney($a->unit_amount ?? 0) }}</td>
                                <td class="text-end">{{ (int)($a->units ?? 0) }}</td>
                                <td class="text-end fw-semibold text-success">{{ $fmtMoney($amt) }}</td>
                                <td><span class="badge bg-{{ $badge }}">{{ strtoupper((string)($a->status ?? '—')) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Sin viáticos</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Documentos --}}
    <div class="card shadow-sm mb-5">
        <div class="card-header bg-white fw-semibold">
            <i class="bi bi-paperclip text-primary"></i> Documentos
        </div>
        <div class="card-body">
            @if(empty($docs))
                <div class="text-muted">No hay documentos asociados.</div>
            @else
                <div class="row g-3">
                    @foreach($docs as $d)
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div class="fw-semibold">
                                        {{ $d['name'] }}
                                        <div class="small text-muted">
                                            Fuente: {{ strtoupper($d['source']) }} · .{{ $d['ext'] }}
                                        </div>
                                    </div>
                                    <span class="badge bg-{{ $d['source']==='sigac' ? 'info' : 'primary' }}">
                                        {{ strtoupper($d['source']) }}
                                    </span>
                                </div>

                                <div class="mt-2">
                                    @if($d['is_image'])
                                        <a href="{{ $d['url'] }}" target="_blank" class="text-decoration-none">
                                            <img src="{{ $d['url'] }}" alt="documento" class="img-fluid rounded border">
                                        </a>
                                    @else
                                        <div class="d-flex align-items-center gap-2 text-muted">
                                            <i class="bi bi-file-earmark-{{ $d['is_pdf'] ? 'pdf' : 'text' }} fs-3"></i>
                                            <div class="small">{{ $d['is_pdf'] ? 'PDF' : 'Archivo' }}</div>
                                        </div>
                                    @endif
                                </div>

                                <div class="mt-3 d-flex gap-2">
                                    <a href="{{ $d['url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <a href="{{ $d['url'] }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-box-arrow-up-right"></i> Abrir
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
