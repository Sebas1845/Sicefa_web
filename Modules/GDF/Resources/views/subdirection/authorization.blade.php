{{-- Modules/GDF/Resources/views/subdirection/authorization.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Autorización</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #111; }
        .muted { color: #555; }
        .h1 { font-size: 14px; font-weight: 700; text-align: center; margin: 0 0 6px; }
        .h2 { font-size: 12px; font-weight: 700; margin: 14px 0 6px; }
        .row { display: table; width: 100%; }
        .col { display: table-cell; vertical-align: top; }
        .col-50 { width: 50%; }
        .box { border: 1px solid #222; padding: 8px; border-radius: 4px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .table th, .table td { border: 1px solid #222; padding: 6px; }
        .table th { background: #f2f2f2; font-weight: 700; }
        .right { text-align: right; }
        .center { text-align: center; }
        .mb6 { margin-bottom: 6px; }
        .sig { height: 55px; max-width: 170px; }
    </style>
</head>
<body>
@php
    $module = strtoupper((string)($tr->module ?? 'GDF'));
    $idReq  = (int)($tr->id ?? 0);
    $areaName = (string)($tr->area_name ?? '—');
    $areaKeyText = (string)($areaKey ?? '—');
    $status = (string)($tr->status ?? '—');

    $person = (string)($personName ?? '—');
    $doc    = trim((string)($personDocument ?? ''));
    if ($doc === '') $doc = '—';

    $personType = (string)($tr->person_type ?? '—');
    $objective = (string)($tr->objective ?? ($tr->purpose ?? ($tr->reason ?? '—')));

    $origin = (string)($originMain ?? ($tr->origin ?? '—'));
    $dest   = (string)($destMain ?? ($tr->destination ?? '—'));

    $startDate = (string)($tr->start_date ?? '—');
    $endDate   = (string)($tr->end_date ?? '');

    $transport = (string)($transportText ?? '—');

    $costsT = (float)($costsTotal ?? 0);
    $allowT = (float)($allowTotal ?? 0);
    $totalT = (float)($totalAll ?? 0);

    $issuedAt = '—';
    try {
        $issuedAt = !empty($generatedAt)
            ? \Carbon\Carbon::parse($generatedAt)->format('Y-m-d H:i')
            : \Carbon\Carbon::now()->format('Y-m-d H:i');
    } catch (\Throwable $e) {}

    $genBy = (string)($generatedBy ?? '—');

    $isSitrav = strtolower((string)($tr->module ?? '')) === 'sitrav';
    $segments = $segments ?? collect();

    // ✅ firmantes
    $signers = $signers ?? collect();
@endphp

    <div class="center mb6">
        <div style="font-weight:700;">SENA</div>
        <div class="muted">Autorización de Desplazamiento ({{ $module }})</div>
    </div>

    <div class="h1">AUTORIZACIÓN PARA DESPLAZAMIENTO Y COMISIÓN</div>

    <div class="row">
        <div class="col col-50">
            <div><strong>Solicitud:</strong> #{{ $idReq }}</div>
            <div><strong>Área:</strong> {{ $areaName }} ({{ $areaKeyText }})</div>
            <div><strong>Estado:</strong> {{ $status }}</div>
        </div>
        <div class="col col-50 right">
            <div><strong>Fecha de emisión:</strong> {{ $issuedAt }}</div>
            <div><strong>Generado por:</strong> {{ $genBy }}</div>
        </div>
    </div>

    <div class="h2">1. Identificación</div>
    <div class="box">
        <div><strong>Servidor/Contratista:</strong> {{ $person }}</div>
        <div><strong>Cédula:</strong> {{ $doc }}</div>
        <div><strong>Tipo persona:</strong> {{ $personType }}</div>
        <div><strong>Objeto / objetivo:</strong> {{ $objective }}</div>
    </div>

    <div class="h2">2. Descripción del desplazamiento</div>
    <div class="box">
        <div><strong>Lugar de origen:</strong> {{ $origin }}</div>
        <div><strong>Destino principal:</strong> {{ $dest }}</div>

        <div class="mb6">
            <strong>Fechas:</strong>
            {{ $startDate }}
            @if(!empty($endDate)) a {{ $endDate }} @endif
        </div>

        <div><strong>Medio(s) de transporte:</strong> {{ $transport }}</div>

        @if($isSitrav && $segments->count())
            <div class="h2" style="margin-top:10px;">Trayectos (SITRAV)</div>
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th>Destino</th>
                        <th style="width:120px;">Tipo</th>
                        <th style="width:120px;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($segments as $i => $s)
                        @php
                            $segDest = (string)($s->destination_name ?? $s->destination_display_name ?? $s->destination_place ?? '—');
                            $segType = (string)($s->destination_type ?? '—');
                            $segDate = (string)($s->travel_date ?? $tr->start_date ?? '—');
                        @endphp
                        <tr>
                            <td class="center">{{ $i + 1 }}</td>
                            <td>{{ $segDest }}</td>
                            <td class="center">{{ $segType }}</td>
                            <td class="center">{{ $segDate }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="muted" style="margin-top:6px;">
                Nota: En SITRAV los trayectos pueden ser múltiples dentro del mismo día, según programación SIGAC.
            </div>
        @endif
    </div>

    <div class="h2">3. Costos y viáticos</div>
    <table class="table">
        <tbody>
            <tr>
                <th>Transporte (costos)</th>
                <td class="right">$ {{ number_format($costsT, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Viáticos</th>
                <td class="right">$ {{ number_format($allowT, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Total autorizado</th>
                <td class="right"><strong>$ {{ number_format($totalT, 0, ',', '.') }}</strong></td>
            </tr>
        </tbody>
    </table>

    <div class="h2">4. Texto de autorización</div>
    <div class="box">
        Mediante la presente se autoriza el desplazamiento del(la) señor(a) <strong>{{ $person }}</strong>,
        identificado(a) con <strong>{{ $doc }}</strong>,
        para el cumplimiento del objeto/actividad: <strong>{{ $objective }}</strong>,
        con lugar de origen <strong>{{ $origin }}</strong> hasta destino <strong>{{ $dest }}</strong>,
        utilizando como medio(s) de transporte <strong>{{ $transport }}</strong>,
        en el periodo comprendido entre <strong>{{ $startDate }}</strong>
        @if(!empty($endDate)) y <strong>{{ $endDate }}</strong> @endif.
        @if($allowT > 0)
            Se reconoce viático por valor de <strong>$ {{ number_format($allowT, 0, ',', '.') }}</strong>.
        @else
            No aplica reconocimiento de viáticos.
        @endif
    </div>

    {{-- ✅ 5. Firmas --}}
    @if($signers instanceof \Illuminate\Support\Collection ? $signers->count() : count($signers))
        <div class="h2">5. Firmas</div>
        <table class="table">
            <thead>
                <tr>
                    <th style="width:30%;">Rol</th>
                    <th style="width:45%;">Firmante</th>
                    <th style="width:25%;">Firma</th>
                </tr>
            </thead>
            <tbody>
                @foreach($signers as $sg)
                    @php
                        // sg viene como (object) en tu service
                        $rk = strtolower((string)($sg->role_key ?? ''));
                        $roleText = match($rk){
                            'subdirection' => 'Subdirección',
                            'coordination' => 'Coordinación',
                            'treasury' => 'Tesorería',
                            'support' => 'Apoyo',
                            default => $rk ? ucfirst($rk) : '—',
                        };

                        $nm = (string)($sg->person_name ?? '—');
                        $dc = (string)($sg->person_document ?? '—');
                        $ps = (string)($sg->position ?? '');
                        $sigAbs = (string)($sg->signature_abs ?? '');
                    @endphp
                    <tr>
                        <td>{{ $roleText }}</td>
                        <td>
                            <div><strong>{{ $nm }}</strong></div>
                            <div class="muted">CC {{ $dc }}@if($ps) · {{ $ps }}@endif</div>
                        </td>
                        <td class="center">
                            @if($sigAbs !== '' && file_exists($sigAbs))
                                <img class="sig" src="{{ $sigAbs }}" alt="firma">
                            @else
                                <span class="muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
