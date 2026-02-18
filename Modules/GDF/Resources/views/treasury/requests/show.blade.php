{{-- Modules/GDF/Resources/views/treasury/requests/show.blade.php --}}
@extends('gdf::layouts.masteruser')

@section('title', 'Tesorería | Ver solicitud')

@push('styles')
    <style>
        .card {
            border-radius: 14px;
        }

        .badge {
            font-weight: 600;
        }

        .muted {
            color: #6c757d;
        }

        .kv {
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }

        .kv .k {
            min-width: 180px;
            color: #6c757d;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }
    </style>
@endpush

@section('content')
    @php
        use Carbon\Carbon;

        use Modules\GDF\Status\TravelStatus;
        use Modules\GDF\Status\AllowanceStatus;
        use Modules\GDF\Status\AllowanceType;

        /** @var \Modules\GDF\Entities\TravelRequest $r */
        $module = (string) ($r->module ?? 'gdf');
        $isGdf = $module === 'gdf' || $module === '' || is_null($r->module);
        $isSitrav = $module === 'sitrav';

        $status = (string) ($r->status ?? '');
        $isPending = in_array($status, ['submitted', 'in_treasury_review', 'pending_treasury', 'approved'], true);

        $segments = collect($r->segments ?? []);
        $costs = collect($r->costs ?? []);
        $allowances = collect($r->allowances ?? []);

        // ✅ Totales consistentes
        $totalCosts = (float) ($costs->sum('amount') ?? 0);
        $totalAllow = (float) ($allowances->sum('calculated_amount') ?? 0);

        $totalAll = (float) ($r->total_amount ?? 0);
        if ($totalAll <= 0) {
            $totalAll = $totalCosts + $totalAllow;
        }

        // Presupuesto
        $availExists = is_array($avail) && (bool) ($avail['exists'] ?? false);
        $availValue = (float) ($avail['available'] ?? 0);
        $availName = (string) ($avail['name'] ?? '—');
        $canApprove = $isPending && $availExists && $availValue >= $totalAll;

        // Labels
        $areaName = $r->area->name ?? ($r->area->denomination ?? null);
        $areaLabel = $areaName ?: 'Área #' . (int) $r->area_id;

        $itemName = $r->budgetItem->name ?? null;
        $itemCode = $r->budgetItem->code ?? null;
        $budgetLabel = $itemName
            ? trim(($itemCode ? $itemCode . ' - ' : '') . $itemName)
            : 'Rubro #' . (int) $r->budget_item_id;

        // Transporte
        $transportHeader = $segments->first()?->transport_type ?? ($segments->first()['transport_type'] ?? null);
        $transportLabel = '—';
        if ($transportHeader) {
            $transportLabel = match ((string) $transportHeader) {
                'terrestre' => 'Terrestre',
                'aereo' => 'Aéreo',
                'moto' => 'Moto',
                'camioneta' => 'Camioneta',
                default => strtoupper((string) $transportHeader),
            };
        } else {
            $transportCost = $costs->firstWhere('cost_type', 'transport');
            if ($transportCost) {
                $transportLabel = 'Transporte';
            }
        }

        $hasRadicado = !empty($r->radicado_code);

        // valor editable de transporte: preferir TravelCost transport, fallback TR.total_transport
        $transportCostRow = $costs->firstWhere('cost_type', 'transport');
        $editableTransport = $transportCostRow ? (float) $transportCostRow->amount : (float) ($r->total_transport ?? 0);
    @endphp

    <div class="container-fluid">

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <b>Hay errores:</b>
                <ul class="mb-0">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h4 class="mb-1">Solicitud #{{ $r->id }}</h4>
                <div class="muted">
                    Módulo: <b class="text-dark">{{ strtoupper($module) }}</b> ·
                    Estado: <span
                        class="badge bg-{{ TravelStatus::badge($status) }}">{{ TravelStatus::label($status) }}</span> ·
                    Año: <b class="text-dark">{{ $year }}</b>
                </div>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-secondary" href="{{ route('gdf.treasury.dashboard', request()->query()) }}">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>

                @if ($isPending)
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalRadicate">
                        <i class="fas fa-hashtag"></i>
                        {{ $hasRadicado ? 'Actualizar radicado' : 'Radicar' }}
                    </button>
                @endif

                {{-- ✅ Ajustes por excepción --}}
                @if ($isPending && !empty($canAdjust))
                    <button class="btn btn-outline-dark" data-bs-toggle="modal" data-bs-target="#modalAdjustTransport">
                        <i class="fas fa-pen"></i> Editar transporte
                    </button>
                @endif

                @if ($isPending)
                    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalReject">
                        <i class="fas fa-times"></i> Rechazar
                    </button>

                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#modalReturn">
                        <i class="fas fa-undo"></i> Devolver
                    </button>

                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalApprove"
                        @if (!$canApprove) disabled @endif>
                        <i class="fas fa-check"></i> Aprobar
                    </button>
                @endif
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-lg-7">

                <div class="card shadow-sm">
                    <div class="card-header bg-white"><b>Detalle de la solicitud</b></div>
                    <div class="card-body">
                        <div class="kv mb-2">
                            <div class="k">Radicado</div>
                            <div class="v">
                                <b>{{ $r->radicado_code ?? '—' }}</b>
                                @if ($r->radicated_at)
                                    <span class="muted"> ·
                                        {{ Carbon::parse($r->radicated_at)->format('Y-m-d H:i') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="kv mb-2">
                            <div class="k">Origen</div>
                            <div class="v">{{ $r->origin ?? '—' }}</div>
                        </div>
                        <div class="kv mb-2">
                            <div class="k">Destino</div>
                            <div class="v">{{ $r->destination ?? '—' }}</div>
                        </div>

                        <div class="kv mb-2">
                            <div class="k">Medio de transporte</div>
                            <div class="v"><b>{{ $transportLabel }}</b></div>
                        </div>

                        <div class="kv mb-2">
                            <div class="k">Fecha creación</div>
                            <div class="v">{{ optional($r->created_at)->format('Y-m-d H:i') ?? '—' }}</div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-12 col-md-6">
                                <div class="kv mb-2">
                                    <div class="k">Área</div>
                                    <div class="v"><b>{{ $areaLabel }}</b></div>
                                </div>
                                <div class="kv mb-2">
                                    <div class="k">Rubro</div>
                                    <div class="v"><b>{{ $budgetLabel }}</b></div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6">
                                <div class="kv mb-2">
                                    <div class="k">Total costos</div>
                                    <div class="v"><b>${{ number_format($totalCosts, 0, ',', '.') }}</b></div>
                                </div>
                                <div class="kv mb-2">
                                    <div class="k">Total viáticos</div>
                                    <div class="v"><b>${{ number_format($totalAllow, 0, ',', '.') }}</b></div>
                                </div>
                                <div class="kv mb-2">
                                    <div class="k">Total solicitud</div>
                                    <div class="v"><b>${{ number_format($totalAll, 0, ',', '.') }}</b></div>
                                </div>
                            </div>
                        </div>

                        @if (!empty($r->treasury_comment))
                            <hr>
                            <div class="alert alert-light border mb-0">
                                <div class="muted mb-1"><b>Último comentario Tesorería</b></div>
                                <div>{{ $r->treasury_comment }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Segmentos --}}
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white"><b>Trayectos / Segmentos</b></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Salida</th>
                                        <th>Regreso</th>
                                        <th>Origen</th>
                                        <th>Destino</th>
                                        <th>Transporte</th>
                                        <th>Tipo viaje</th>
                                        <th class="text-end">Viajes</th>
                                        <th class="text-end">Total seg.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($segments as $i => $s)
                                        @php
                                            $dep = !empty($s->departure_at ?? null)
                                                ? Carbon::parse($s->departure_at)->format('Y-m-d H:i')
                                                : '—';
                                            $ret = !empty($s->return_at ?? null)
                                                ? Carbon::parse($s->return_at)->format('Y-m-d H:i')
                                                : '—';

                                            $orig = $s->origin_display_name ?: $s->origin_place ?? '—';
                                            $dest = $s->destination_display_name ?: $s->destination_place ?? '—';

                                            $transport = $s->transport_type ?? '—';
                                            $tripType = $s->trip_type ?? '—';

                                            $transportLbl = match ((string) $transport) {
                                                'terrestre' => 'Terrestre',
                                                'aereo' => 'Aéreo',
                                                'moto' => 'Moto',
                                                'camioneta' => 'Camioneta',
                                                default => strtoupper((string) $transport),
                                            };

                                            $tripLbl = match ((string) $tripType) {
                                                'one_way' => 'Solo ida',
                                                'round_trip' => 'Ida y vuelta',
                                                'two_way' => 'Dos trayectos',
                                                default => strtoupper((string) $tripType),
                                            };

                                            $trips = (int) ($s->trips ?? 0);
                                            $segTotal = (float) ($s->total_cost ?? 0);
                                            $cancelled = (int) ($s->is_cancelled ?? 0) === 1;
                                        @endphp

                                        <tr @if ($cancelled) class="table-secondary" @endif>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $dep }}</td>
                                            <td>{{ $ret }}</td>
                                            <td class="text-break">{{ $orig }}</td>
                                            <td class="text-break">{{ $dest }}</td>
                                            <td><span class="badge bg-info text-dark">{{ $transportLbl }}</span></td>
                                            <td><span class="badge bg-secondary">{{ $tripLbl }}</span></td>
                                            <td class="text-end">{{ $trips ?: '—' }}</td>
                                            <td class="text-end"><b>${{ number_format($segTotal, 0, ',', '.') }}</b></td>
                                        </tr>

                                        @if (!empty($s->change_reason))
                                            <tr>
                                                <td></td>
                                                <td colspan="8" class="muted"><b>Motivo cambio:</b>
                                                    {{ $s->change_reason }}</td>
                                            </tr>
                                        @endif
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center muted py-3">Sin trayectos.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Costos --}}
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <b>Costos</b>
                        <span class="muted">Total: <b
                                class="text-dark">${{ number_format($totalCosts, 0, ',', '.') }}</b></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th class="text-end">Valor</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($costs as $c)
                                        @php
                                            $type = $c->cost_type ?? ($c['cost_type'] ?? '—');
                                            $desc = $c->description ?? ($c['description'] ?? null);
                                            $amt = (float) ($c->amount ?? ($c['amount'] ?? 0));
                                        @endphp
                                        <tr>
                                            <td><span class="badge bg-info text-dark">{{ strtoupper($type) }}</span></td>
                                            <td class="text-break">
                                                @if ($type === 'transport' && is_string($desc) && $desc !== '')
                                                    {!! \Modules\GDF\Services\GdfCostFormatter::transport($desc) !!}
                                                @else
                                                    {{ is_string($desc) && $desc !== '' ? $desc : '—' }}
                                                @endif
                                            </td>

                                            <td class="text-end"><b>${{ number_format($amt, 0, ',', '.') }}</b></td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center muted py-3">Sin costos.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Viáticos --}}
                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <b>Viáticos</b>
                        <span class="muted">Total: <b
                                class="text-dark">${{ number_format($totalAllow, 0, ',', '.') }}</b></span>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Tipo</th>
                                        <th>Estado</th>
                                        <th>Descripción</th>
                                        <th class="text-end">Valor</th>
                                        @if ($isPending && !empty($canAdjust))
                                            <th class="text-end">Acción</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($allowances as $a)
                                        @php
                                            $type = $a->allowance_type ?? ($a['allowance_type'] ?? 'allowance');
                                            $st = $a->status ?? ($a['status'] ?? '—');
                                            $desc = $a->description ?? ($a['description'] ?? null);
                                            $amt =
                                                (float) ($a->calculated_amount ??
                                                    ($a['calculated_amount'] ?? (0 ?? ($a->amount ?? 0))));
                                        @endphp
                                        <tr>
                                            <td><span class="badge bg-primary">{{ AllowanceType::label($type) }}</span>
                                            </td>
                                            <td><span
                                                    class="badge bg-{{ AllowanceStatus::badge($st) }}">{{ AllowanceStatus::label($st) }}</span>
                                            </td>
                                            <td class="text-break">{{ is_string($desc) && $desc !== '' ? $desc : '—' }}
                                            </td>
                                            <td class="text-end"><b>${{ number_format($amt, 0, ',', '.') }}</b></td>

                                            @if ($isPending && !empty($canAdjust))
                                                <td class="text-end">
                                                    <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal"
                                                        data-bs-target="#modalAdjustAllowance"
                                                        data-allowance-id="{{ (int) $a->id }}"
                                                        data-allowance-label="{{ AllowanceType::label($type) }}"
                                                        data-allowance-amount="{{ $amt }}">
                                                        <i class="fas fa-pen"></i> Editar
                                                    </button>
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $isPending && !empty($canAdjust) ? 5 : 4 }}"
                                                class="text-center muted py-3">Sin viáticos.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Derecha --}}
            <div class="col-12 col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-header bg-white"><b>Disponibilidad presupuestal</b></div>
                    <div class="card-body">
                        @if (!$availExists)
                            <div class="alert alert-warning mb-0">
                                No existe presupuesto activo para este <b>Área/Rubro</b> en el año
                                <b>{{ $year }}</b>.
                            </div>
                        @else
                            <div class="kv mb-2">
                                <div class="k">Presupuesto</div>
                                <div class="v"><b>{{ $availName }}</b></div>
                            </div>
                            <div class="kv mb-2">
                                <div class="k">Disponible</div>
                                <div class="v"><b>${{ number_format($availValue, 0, ',', '.') }}</b></div>
                            </div>
                            <div class="kv mb-2">
                                <div class="k">Necesita</div>
                                <div class="v"><b>${{ number_format($totalAll, 0, ',', '.') }}</b></div>
                            </div>

                            @if ($availValue < $totalAll)
                                <div class="alert alert-danger mb-0">No hay recursos suficientes para aprobar.</div>
                            @else
                                <div class="alert alert-success mb-0">Hay recursos suficientes para aprobar.</div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm mt-3">
                    <div class="card-header bg-white"><b>Revisión Tesorería</b></div>
                    <div class="card-body">

                        <div class="kv mb-2">
                            <div class="k">Radicado por</div>
                            <div class="v">
                                @if (!empty($radicatedByUser))
                                    {{ $radicatedByUser->name ?? ($radicatedByUser->nickname ?? 'Usuario #' . (int) $r->radicated_by) }}
                                    <span class="muted"> (id: {{ (int) $r->radicated_by }})</span>
                                @else
                                    {{ $r->radicated_by ? 'Usuario #' . (int) $r->radicated_by : '—' }}
                                @endif
                            </div>
                        </div>

                        <div class="kv mb-2">
                            <div class="k">Fecha radicado</div>
                            <div class="v">
                                {{ $r->radicated_at ? Carbon::parse($r->radicated_at)->format('Y-m-d H:i') : '—' }}
                            </div>
                        </div>

                        <div class="kv mb-2">
                            <div class="k">Fecha aprobación</div>
                            <div class="v">
                                {{ $r->approved_at ? Carbon::parse($r->approved_at)->format('Y-m-d H:i') : '—' }}
                            </div>
                        </div>

                        <div class="kv mb-0">
                            <div class="k">Observación</div>
                            <div class="v">
                                {{ !empty($r->notes) ? $r->notes : '—' }}
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- ===================== MODALS ===================== --}}

    {{-- Radicar --}}
    <div class="modal fade" id="modalRadicate" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('gdf.treasury.requests.radicate', $r->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Radicar solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="year" value="{{ $year }}">

                    <div class="mb-3">
                        <label class="form-label">Código de radicado</label>
                        <input type="text" name="radicado_code" class="form-control" maxlength="80" required
                            value="{{ old('radicado_code', $r->radicado_code) }}" placeholder="Ej: CEFA-2026-000123">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Ajuste Transporte --}}
    @if ($isPending && !empty($canAdjust))
        <div class="modal fade" id="modalAdjustTransport" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('gdf.treasury.requests.adjustTransport', $r->id) }}"
                    class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Editar costo de transporte (excepción)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="year" value="{{ $year }}">

                        <div class="alert alert-warning">
                            Este cambio es un <b>ajuste por excepción</b> y debe quedar justificado.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nuevo valor transporte</label>
                            <input type="number" step="0.01" min="0" name="transport_cost"
                                class="form-control" value="{{ old('transport_cost', $editableTransport) }}" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Comentario (obligatorio)</label>
                            <textarea class="form-control" name="comment" rows="4" maxlength="2000" required
                                placeholder="¿Por qué se ajusta este valor?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-dark" type="submit"><i class="fas fa-save"></i> Guardar ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Ajuste Viático (modal reutilizable) --}}
    @if ($isPending && !empty($canAdjust))
        <div class="modal fade" id="modalAdjustAllowance" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <form method="POST" id="formAdjustAllowance" action="#" class="modal-content">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Editar viático (excepción)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="year" value="{{ $year }}">

                        <div class="mb-2 muted">
                            Viático: <b class="text-dark" id="allowanceLabel">—</b>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nuevo valor</label>
                            <input type="number" step="0.01" min="0" name="amount" id="allowanceAmount"
                                class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Comentario (obligatorio)</label>
                            <textarea class="form-control" name="comment" rows="4" maxlength="2000" required
                                placeholder="¿Por qué se ajusta este viático?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button"
                            data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-dark" type="submit"><i class="fas fa-save"></i> Guardar ajuste</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- Aprobar --}}
    <div class="modal fade" id="modalApprove" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('gdf.treasury.requests.approve', $r->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Aprobar por Tesorería</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="year" value="{{ $year }}">

                    <div class="mb-2 muted">
                        Total solicitud: <b class="text-dark">${{ number_format($totalAll, 0, ',', '.') }}</b><br>
                        Disponible: <b class="@if ($availValue < $totalAll) text-danger @else text-success @endif">
                            ${{ number_format($availValue, 0, ',', '.') }}
                        </b>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comentario (opcional)</label>
                        <textarea class="form-control" name="comment" rows="4" maxlength="2000"
                            placeholder="Observación de Tesorería..."></textarea>
                    </div>

                    @if (!$canApprove)
                        <div class="alert alert-warning mb-0">
                            No puedes aprobar: falta presupuesto activo o no hay disponibilidad suficiente.
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-success" type="submit" @if (!$canApprove) disabled @endif>
                        <i class="fas fa-check"></i> Aprobar
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Devolver --}}
    <div class="modal fade" id="modalReturn" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('gdf.treasury.requests.return', $r->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Devolver solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">¿A quién devolver?</label>
                        <select name="target" class="form-select" required>
                            <option value="support">Apoyo</option>
                            <option value="applicant">Solicitante</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comentario (obligatorio)</label>
                        <textarea class="form-control" name="comment" rows="4" maxlength="2000" required
                            placeholder="Indica el motivo de la devolución..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-warning" type="submit"><i class="fas fa-undo"></i> Devolver</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Rechazar --}}
    <div class="modal fade" id="modalReject" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('gdf.treasury.requests.reject', $r->id) }}" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Rechazar solicitud</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        Esta acción marcará la solicitud como <b>rejected_by_treasury</b>.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comentario (obligatorio)</label>
                        <textarea class="form-control" name="comment" rows="4" maxlength="2000" required
                            placeholder="Indica claramente el motivo del rechazo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-danger" type="submit"><i class="fas fa-times"></i> Rechazar</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('scripts')
    @if ($isPending && !empty($canAdjust))
        <script>
            (function() {
                const modal = document.getElementById('modalAdjustAllowance');
                if (!modal) return;

                modal.addEventListener('show.bs.modal', function(event) {
                    const btn = event.relatedTarget;
                    const allowanceId = btn.getAttribute('data-allowance-id');
                    const label = btn.getAttribute('data-allowance-label');
                    const amount = btn.getAttribute('data-allowance-amount');

                    document.getElementById('allowanceLabel').textContent = label || ('#' + allowanceId);
                    document.getElementById('allowanceAmount').value = amount || 0;

                    const form = document.getElementById('formAdjustAllowance');
                    // arma la URL: /treasury/requests/{id}/allowances/{allowanceId}/adjust
                    form.action =
                        "{{ route('gdf.treasury.requests.allowances.adjust', ['id' => $r->id, 'allowanceId' => 0]) }}"
                        .replace(/\/0\/adjust$/, '/' + allowanceId + '/adjust');
                });
            })();
        </script>
    @endif
@endpush
