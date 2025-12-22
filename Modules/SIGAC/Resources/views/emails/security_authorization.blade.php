<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Autorización de visita - SIGAC / SICEFA</title>

    <style>
        @page { margin: 40px 40px; }

        body {
            font-family: system-ui, -apple-system, "Segoe UI", sans-serif;
            color: #111827;
            font-size: 12px;
        }

        .side { width: 28px; background: #111827; }

        .content { padding: 32px 36px; }

        .header { text-align: center; margin-bottom: 20px; }
        .logo img { max-height: 60px; }
        .institution { font-size:16px; font-weight:700; letter-spacing:0.15em; }
        .subtitle { font-size: 11px; color:#6b7280; }

        .title { margin-top: 20px; font-size: 15px; font-weight:700; text-align:center; }

        .date-place { text-align:right; font-size:11px; margin-top:10px; color:#374151; }

        .body-text { margin-top:24px; line-height:1.55; text-align:justify; }
        .body-text p { margin-bottom: 12px; }

        .data-block { margin: 16px 0; padding-left:8px; }
        .item { margin-bottom:6px; }
        .label { font-weight:600; }

        .closing { margin-top:26px; }
        .signature-block { margin-top:36px; text-align:center; }
        .signature img { max-height:60px; }

        .signature-name { font-weight:700; margin-top:4px; }
        .signature-role { font-size:11px; }

        .footer { text-align:center; font-size:10px; margin-top:40px; color:#6b7280; }
    </style>
</head>

<body>
<table width="100%">
    <tr>
        <td class="side"></td>

        <td class="content">

            {{-- ENCABEZADO --}}
            <div class="header">
                <div class="logo">
                    @if(config('sigac.pdf_logo_path') && file_exists(public_path(config('sigac.pdf_logo_path'))))
                        <img src="{{ public_path(config('sigac.pdf_logo_path')) }}" alt="Logo">
                    @endif
                </div>

                <div class="institution">
                    {{ config('sigac.institution_name', 'SERVICIO NACIONAL DE APRENDIZAJE – SENA') }}
                </div>

                <div class="subtitle">
                    {{ config('sigac.center_name', 'Centro de Formación Agroindustrial') }}
                </div>
            </div>

            <div class="title">AUTORIZACIÓN DE VISITA</div>

            <div class="date-place">
                {{ config('sigac.city_name', 'Neiva') }},
                {{ \Carbon\Carbon::parse($schedule->date)->format('d/m/Y') }}
            </div>

            {{-- CUERPO DE LA CARTA --}}
            <div class="body-text">

                @php
                    $empresa     = optional($visit->company)->name ?? '—';
                    $solicitante = optional($visit->person)->full_name ?? ($visit->contact_name ?? '—');
                    $tipoVisita  = $visit->type === 'practica' ? 'una práctica formativa' : 'una visita técnica';
                    $ambiente    = optional($schedule->environment)->name ?? 'Por definir';
                    $actividad   = $schedule->activity ?? 'actividad académica';
                @endphp

                <p>
                    Por medio de la presente, la Coordinación Académica del 
                    <strong>{{ config('sigac.center_name', 'Centro de Formación Agroindustrial') }}</strong>
                    autoriza oficialmente el ingreso al Centro del señor/ señora
                    <strong>{{ $solicitante }}</strong>,
                    representante de la empresa/entidad 
                    <strong>{{ $empresa }}</strong>.
                </p>

                <p>
                    La autorización se otorga con el propósito de realizar 
                    <strong>{{ $tipoVisita }}</strong>, cuyo objetivo es
                    <strong>{{ $actividad }}</strong>,
                    según lo solicitado mediante la plataforma SIGAC/SICEFA.
                </p>

                <p>La visita se llevará a cabo en las siguientes condiciones:</p>

                <div class="data-block">
                    <div class="item">
                        <span class="label">Fecha:</span>
                        {{ \Carbon\Carbon::parse($schedule->date)->format('d/m/Y') }}
                    </div>

                    <div class="item">
                        <span class="label">Horario:</span>
                        {{ $schedule->start_time }} – {{ $schedule->end_time }}
                    </div>

                    <div class="item">
                        <span class="label">Ambiente / Área asignada:</span>
                        {{ $ambiente }}
                    </div>

                    <div class="item">
                        <span class="label">Cantidad de personas autorizadas:</span>
                        {{ $visit->number_of_people ?? '—' }}
                    </div>

                    @if(!empty($schedule->observations))
                        <div class="item">
                            <span class="label">Observaciones:</span>
                            {{ $schedule->observations }}
                        </div>
                    @endif
                </div>

                <p>
                    El ingreso del grupo se efectuará previa verificación de esta autorización en portería
                    y presentación del documento de identidad por parte de los visitantes.
                </p>

                <p>
                    La empresa y el responsable del grupo se comprometen a cumplir las normas de seguridad,
                    convivencia y bioseguridad establecidas por el SENA.
                </p>

                <div class="closing">
                    <p>Atentamente,</p>
                </div>
            </div>

            {{-- FIRMA --}}
            <div class="signature-block">
                <div class="signature">
                    @if(config('sigac.coordination_signature_path') && file_exists(public_path(config('sigac.coordination_signature_path'))))
                        <img src="{{ public_path(config('sigac.coordination_signature_path')) }}" alt="Firma">
                    @endif
                </div>

                <div class="signature-name">
                    {{ config('sigac.coordination_name', 'Coordinador(a) Académico(a)') }}
                </div>

                <div class="signature-role">
                    Coordinación Académica<br>
                    {{ config('sigac.center_name', 'Centro de Formación Agroindustrial') }}
                </div>
            </div>

            {{-- PIE DE PÁGINA --}}
            <div class="footer">
                {{ config('sigac.center_address', 'Dirección del Centro de Formación') }}<br>
                {{ config('sigac.center_contact', 'Teléfono – correo institucional') }}<br>
                Sistema de Gestión Académica — SIGAC / SICEFA
            </div>

        </td>

        <td class="side"></td>
    </tr>
</table>
</body>
</html>
