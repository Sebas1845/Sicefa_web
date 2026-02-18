{{-- Modules/GDF/Resources/views/official/requests/show.blade.php --}}
@extends('gdf::layouts.masteruser')
@section('title', 'GDF | Instructor / Funcionario')

@section('content')
    @php
        use Carbon\Carbon;

        /** @var \Modules\GDF\Entities\TravelRequest $gdfRequest */

        $areaKey = $areaKey ?? 'academic';
        $areaLabel = $areaKey === 'campesena' ? 'CAMPESENA' : 'COORDINACIÓN ACADÉMICA';

        $person = $gdfRequest->person ?? null;
        $area = $gdfRequest->area ?? null;
        $budget = $gdfRequest->budgetItem ?? null;

        $segments = $gdfRequest->segments ?? collect();
        $costs = $gdfRequest->costs ?? collect();
        $allowances = $gdfRequest->allowances ?? collect();
        $docs = $gdfRequest->documents ?? collect();

        $fmtMoney = fn($v) => '$ ' . number_format((float) $v, 0, ',', '.');

        $transportTotal = (float) ($gdfRequest->total_transport ?? 0);
        $allowTotal = (float) ($gdfRequest->total_per_diem ?? 0);
        $grandTotal = (float) ($transportTotal + $allowTotal);

        $allowLabel = function ($type) {
            return match ($type) {
                'fuel' => 'Gasolina',
                'lodging' => 'Hospedaje',
                'meals' => 'Alimentación',
                'per_diem' => 'Otros',
                default => ucfirst(str_replace('_', ' ', (string) $type)),
            };
        };

        $selectedTypes = $selectedAllowanceTypes ?? [];

        $seg0 = $gdfRequest->segments->first();
        $segType = strtolower((string) ($seg0?->transport_type ?? ''));
        $metaType = strtolower((string) ($transportMeta['transport'] ?? ''));

        $map = [
            'terrestre' => 'Terrestre',
            'aereo' => 'Aéreo',
            'moto' => 'Moto',
            'camioneta' => 'Camioneta',
            'bus' => 'Terrestre',
            'air' => 'Aéreo',
            'motorcycle' => 'Moto',
            'van' => 'Camioneta',
        ];

        $key = $segType ?: $metaType;
        $transportLabel = $map[$key] ?? '—';
        $isMoto = in_array($key, ['moto', 'motorcycle'], true);

        // ✅ viene del controlador:
        $authUrl = $authUrl ?? null;
        $isAuthVisible = (bool) ($isAuthVisible ?? false);
    @endphp

    <style>
        .softbox {
            background: #fff;
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .04);
        }

        .pill {
            display: inline-block;
            padding: .25rem .6rem;
            border-radius: 999px;
            border: 1px solid rgba(0, 0, 0, .12);
            background: #f8f9fa;
            font-size: .85rem;
        }

        .muted {
            color: #6c757d;
        }

        .kpi {
            border: 1px solid rgba(0, 0, 0, .08);
            border-radius: 12px;
            padding: 12px;
            background: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, .04);
        }

        .kpi .v {
            font-weight: 800;
            font-size: 1.05rem;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .auth-section {
            background: linear-gradient(135deg, #3c3d3d 0%, #030006 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            margin-bottom: 20px;
        }

        .auth-section .fw-semibold {
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .auth-warn {
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 10px;
            padding: 14px;
            color: #fff;
        }

        .pdf-viewer {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, .1);
        }
    </style>

    <div class="container py-4">

        {{-- Alerts de sesión --}}
        @foreach (['success', 'error', 'warning', 'info'] as $k)
            @if (session($k))
                <div class="alert alert-{{ $k === 'error' ? 'danger' : $k }} alert-dismissible fade show">
                    {{ session($k) }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
        @endforeach

        {{-- Header --}}
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <h3 class="mb-1">
                    <i class="bi bi-file-earmark-text text-primary"></i>
                    Detalle de solicitud GDF
                </h3>
                <div class="muted">
                    <span class="pill">Área: {{ $areaLabel }}</span>
                    <span class="pill">ID: {{ $gdfRequest->id }}</span>
                    Estado:
                    <span class="badge bg-{{ $gdfRequest->status_badge }}">
                        {{ $gdfRequest->status_label }}
                    </span>
                </div>
            </div>

            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ route('gdf.instructor.dashboard') }}">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="kpi">
                    <div class="muted small"><i class="bi bi-bus-front"></i> Transporte</div>
                    <div class="v text-primary">{{ $fmtMoney($transportTotal) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="muted small"><i class="bi bi-wallet2"></i> Viáticos</div>
                    <div class="v text-success">{{ $fmtMoney($allowTotal) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="kpi">
                    <div class="muted small"><i class="bi bi-cash-stack"></i> Total</div>
                    <div class="v text-dark">{{ $fmtMoney($grandTotal) }}</div>
                </div>
            </div>
        </div>

        {{-- ✅ AUTORIZACIÓN: SOLO CUANDO YA ESTÁ CONFIRMADA --}}
        @if ($isAuthVisible)
            <div class="auth-section mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-pdf fs-3"></i>
                        <div>
                            <div class="fw-semibold">Documento de Autorización</div>
                            <div class="small opacity-75">Disponible cuando la solicitud está confirmada</div>
                        </div>
                    </div>

                    @if ($authUrl)
                        <div class="d-flex gap-2">
                            <a class="btn btn-light btn-sm" href="{{ $authUrl }}" target="_blank" rel="noopener">
                                <i class="bi bi-download"></i> Descargar
                            </a>
                            <a class="btn btn-outline-light btn-sm" href="{{ $authUrl }}" target="_blank"
                                rel="noopener">
                                <i class="bi bi-box-arrow-up-right"></i> Abrir
                            </a>
                        </div>
                    @endif
                </div>

                @if ($authUrl)
                    <div class="mt-3">
                        <iframe src="{{ $authUrl }}" class="pdf-viewer" style="width:100%; height:650px; border:none;"
                            title="Autorización PDF">
                        </iframe>
                    </div>
                @else
                    <div class="auth-warn mt-3">
                        <strong>Autorización pendiente</strong>
                        <div class="small mt-1">
                            La solicitud está confirmada, pero el PDF aún no está disponible (no se ha generado o no se
                            guardó).
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Datos generales --}}
        <div class="softbox mb-3">
            <h5 class="mb-3"><i class="bi bi-info-circle text-info"></i> Datos generales</h5>

            <div class="row g-3">
                <div class="col-md-4">
                    <div class="muted small"><i class="bi bi-person"></i> Solicitante</div>
                    <div class="fw-semibold">
                        {{ $person?->full_name ?? ($person?->first_name ? $person->first_name . ' ' . $person->first_last_name : '—') }}
                    </div>
                    <div class="muted small"><i class="bi bi-card-text"></i> Documento:
                        {{ $person?->document_number ?? '—' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="muted small"><i class="bi bi-building"></i> Centro / Área</div>
                    <div class="fw-semibold">{{ $area?->name ?? '—' }}</div>
                </div>

                <div class="col-md-4">
                    <div class="muted small"><i class="bi bi-tag"></i> Rubro</div>
                    <div class="fw-semibold">
                        @if ($budget)
                            {{ $budget->name }}
                        @else
                            <span class="muted">—</span>
                        @endif
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="muted small"><i class="bi bi-calendar-event"></i> Fecha inicio</div>
                    <div class="fw-semibold">
                        {{ $gdfRequest->start_date ? Carbon::parse($gdfRequest->start_date)->format('d/m/Y') : '—' }}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="muted small"><i class="bi bi-calendar-check"></i> Fecha fin</div>
                    <div class="fw-semibold">
                        {{ $gdfRequest->end_date ? Carbon::parse($gdfRequest->end_date)->format('d/m/Y') : '—' }}
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="muted small"><i class="bi bi-truck"></i> Transporte</div>
                    <div class="fw-semibold">
                        {{ $transportLabel }}
                        @if ($isMoto)
                            <span class="pill ms-1 bg-warning text-dark">
                                <i class="bi bi-scooter"></i> Moto
                            </span>
                        @endif
                    </div>
                </div>

                <div class="col-12">
                    <div class="muted small"><i class="bi bi-journal-text"></i> Objeto</div>
                    <div class="fw-semibold p-2 bg-light rounded" style="white-space:pre-wrap">
                        {{ $gdfRequest->notes ?? ($gdfRequest->purpose ?? '—') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Segmentos --}}
        <div class="softbox mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-signpost-split text-primary"></i> Trayectos</h5>
                <span class="badge bg-secondary">{{ $segments->count() }} segmento(s)</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th><i class="bi bi-geo-alt"></i> Origen</th>
                            <th><i class="bi bi-geo-alt-fill"></i> Destino</th>
                            <th class="text-end"><i class="bi bi-cash"></i> Transporte</th>
                            <th class="text-center"><i class="bi bi-x-circle"></i> Cancelado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($segments as $i => $s)
                            <tr>
                                <td><span class="badge bg-primary">{{ $i + 1 }}</span></td>
                                <td>{{ $s->origin_place ?? '—' }}</td>
                                <td>{{ $s->destination_place ?? '—' }}</td>
                                <td class="text-end fw-semibold">{{ $fmtMoney($s->transport_cost ?? 0) }}</td>
                                <td class="text-center">
                                    @if ((int) ($s->is_cancelled ?? 0) === 1)
                                        <span class="badge bg-danger">Sí</span>
                                    @else
                                        <span class="badge bg-success">No</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    <i class="bi bi-inbox"></i> Sin segmentos registrados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Viáticos --}}
        <div class="softbox mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <h5 class="mb-0"><i class="bi bi-wallet text-success"></i> Viáticos seleccionados</h5>
                <div class="text-success fw-bold">Total: {{ $fmtMoney($allowTotal) }}</div>
            </div>

            <div class="mb-3">
                <div class="muted small mb-2">Conceptos seleccionados:</div>
                <div class="d-flex flex-wrap gap-2">
                    @if (empty($selectedTypes))
                        <span class="pill bg-secondary text-white">Ninguno</span>
                    @else
                        @foreach ($selectedTypes as $t)
                            <span class="pill bg-info text-white">{{ $allowLabel($t) }}</span>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Concepto</th>
                            <th>Descripción</th>
                            <th class="text-end">Unitario</th>
                            <th class="text-end">Unidades</th>
                            <th class="text-end">Total</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($allowances as $a)
                            @php
                                $type = (string) ($a->allowance_type ?? '');
                                $unit = (float) ($a->unit_amount ?? 0);
                                $units = (float) ($a->units ?? 0);
                                $calc = (float) ($a->calculated_amount ?? $unit * $units);
                            @endphp
                            <tr>
                                <td><span class="pill bg-info text-white">{{ $allowLabel($type) }}</span></td>
                                <td>{{ $a->description ?? '—' }}</td>
                                <td class="text-end">{{ $fmtMoney($unit) }}</td>
                                <td class="text-end">{{ rtrim(rtrim(number_format($units, 2, ',', '.'), '0'), ',') }}</td>
                                <td class="text-end fw-bold text-success">{{ $fmtMoney($calc) }}</td>
                                <td class="text-center">
                                    <span class="badge bg-secondary">{{ strtoupper($a->status ?? '—') }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">
                                    <i class="bi bi-inbox"></i> No hay viáticos guardados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Costos --}}
        <div class="softbox mb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-calculator text-warning"></i> Costos</h5>
                <span class="badge bg-secondary">{{ $costs->count() }} registro(s)</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover">
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
                                $type = strtolower((string) ($c->cost_type ?? ''));
                                $isTransport = $type === 'transport';
                            @endphp

                            <tr>
                                <td>
                                    <span class="pill bg-warning text-dark">
                                        {{ strtoupper($c->cost_type ?? '—') }}
                                    </span>
                                </td>

                                <td style="max-width:520px; white-space:normal; overflow:visible;">
                                    @if ($isTransport)
                                        {!! \Modules\GDF\Services\GdfCostFormatter::transport($c->description) !!}
                                    @else
                                        {{ is_string($c->description) ? $c->description : '—' }}
                                    @endif
                                </td>

                                <td class="text-end fw-semibold">
                                    {{ $fmtMoney($c->amount ?? 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-center text-muted py-3">
                                    <i class="bi bi-inbox"></i> Sin costos registrados
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>
        </div>

        {{-- Documentos --}}
        <div class="softbox">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="bi bi-paperclip text-secondary"></i> Documentos soporte</h5>
                <span class="badge bg-secondary">{{ $docs->count() }} archivo(s)</span>
            </div>

            <div>
                @if ($docs->isEmpty())
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-inbox fs-3"></i>
                        <div class="mt-2">Sin documentos adjuntos</div>
                    </div>
                @else
                    <ul class="list-group">
                        @foreach ($docs as $d)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-earmark"></i>
                                    {{ $d->original_name ?? 'Documento #' . $d->id }}
                                    @if (!empty($d->path))
                                        <small class="text-muted d-block">{{ basename($d->path) }}</small>
                                    @endif
                                </div>

                                @if (!empty($d->path))
                                    <a href="{{ route('gdf.instructor.documents.preview', $d->id) }}" target="_blank"
                                        rel="noopener" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

    </div>

    <script>
        console.log('Vista show.blade.php cargada');
        console.log('isAuthVisible:', @json($isAuthVisible));
        console.log('authUrl:', @json($authUrl));
        console.log('Request ID:', {{ (int) $gdfRequest->id }});
    </script>
@endsection
