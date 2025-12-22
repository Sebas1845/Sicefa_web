<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Visita Agendada - SIGAC / SICEFA</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 24px;
        }
        .mail-wrapper {
            max-width: 640px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            padding: 24px 28px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            border: 1px solid #e5e7eb;
        }
        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }
        .logo-circle {
            width: 40px;
            height: 40px;
            border-radius: 999px;
            background: #047857;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
        }
        h1 {
            font-size: 20px;
            margin: 0;
            color: #111827;
        }
        .subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 2px;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            background: #e0f2fe;
            color: #1d4ed8;
            font-weight: 600;
        }
        .section {
            margin-top: 18px;
            margin-bottom: 12px;
        }
        .section-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .card {
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 12px 14px;
            background: #f9fafb;
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 16px;
            font-size: 14px;
        }
        .label {
            font-size: 12px;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        .value {
            font-size: 14px;
            color: #111827;
        }
        .value-muted {
            color: #6b7280;
        }
        .btn-primary {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 999px;
            background: linear-gradient(135deg, #1d4ed8, #0f766e);
            color: #ffffff !important;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            margin: 16px 0;
        }
        .btn-primary span {
            margin-left: 6px;
            font-size: 13px;
        }
        .footer-text {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 12px;
        }
        .divider {
            height: 1px;
            background: #e5e7eb;
            margin: 18px 0;
        }
    </style>
</head>
<body>
    <div class="mail-wrapper">
        {{-- Encabezado --}}
        <div class="header">
            <div class="logo-circle">S</div>
            <div>
                <h1>
                    @if(!empty($isVisitor) && $isVisitor)
                        Confirmación de visita al SENA
                    @else
                        Nueva visita asignada en SIGAC
                    @endif
                </h1>
                <div class="subtitle">
                    Sistema de Gestión Académica — SICEFA / SENA
                </div>
            </div>
        </div>

        <div class="pill">
            {{ strtoupper($visitSchedule->activity ?? 'VISITA ACADÉMICA') }}
        </div>

        {{-- Saludo --}}
        <p style="font-size:14px; color:#111827; margin-top:14px;">
            @if(!empty($isVisitor) && $isVisitor)
                Estimado/a
                <strong>{{ $visitRequest->contact_name ?? 'visitante' }}</strong>,
            @else
                Estimado/a
                <strong>
                    {{ $visitSchedule->personInCharge->full_name
                        ?? $visitRequest->person->full_name
                        ?? 'encargado(a)' }}
                </strong>,
            @endif
        </p>

        {{-- Intro según tipo de destinatario --}}
        <p style="font-size:14px; color:#374151;">
            @if(!empty($isVisitor) && $isVisitor)
                Le informamos que su visita al
                <strong>SENA{{ $visitSchedule->environment ? ' - '.$visitSchedule->environment->name : '' }}</strong>
                ha sido programada bajo la solicitud
                <strong>#{{ $visitRequest->id }}</strong>. A continuación encontrará los detalles:
            @else
                Se ha programado una visita asociada a la solicitud
                <strong>#{{ $visitRequest->id }}</strong>,
                en la cual usted figura como encargado interno. A continuación encontrará los detalles:
            @endif
        </p>

        {{-- Bloque principal --}}
        <div class="section">
            <div class="section-title">Información de la visita</div>
            <div class="card">
                <div class="grid">
                    <div>
                        <div class="label">Empresa</div>
                        <div class="value">
                            {{ $visitRequest->company->name ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="label">
                            @if(!empty($isVisitor) && $isVisitor)
                                Contacto en el SENA
                            @else
                                Solicitante
                            @endif
                        </div>
                        <div class="value">
                            {{ $visitRequest->person->full_name ?? $visitRequest->contact_name ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="label">Fecha</div>
                        <div class="value">
                            {{ \Carbon\Carbon::parse($visitSchedule->date)->format('d/m/Y') }}
                        </div>
                    </div>
                    <div>
                        <div class="label">Horario</div>
                        <div class="value">
                            {{ $visitSchedule->start_time }} – {{ $visitSchedule->end_time }}
                        </div>
                    </div>
                    <div>
                        <div class="label">Tipo de visita</div>
                        <div class="value text-capitalize">
                            {{ $visitRequest->type ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="label">Ambiente</div>
                        <div class="value">
                            {{ $visitSchedule->environment->name ?? 'Por definir' }}
                        </div>
                    </div>
                    <div>
                        <div class="label">N.º de personas</div>
                        <div class="value">
                            {{ $visitRequest->number_of_people ?? '—' }}
                        </div>
                    </div>
                    <div>
                        <div class="label">
                            @if(!empty($isVisitor) && $isVisitor)
                                Encargado interno
                            @else
                                Encargado asignado
                            @endif
                        </div>
                        <div class="value">
                            {{ $visitSchedule->personInCharge->full_name ?? '—' }}
                        </div>
                    </div>
                </div>

                @if (!empty($visitSchedule->observations))
                    <div style="margin-top:10px;">
                        <div class="label">Observaciones</div>
                        <div class="value value-muted">
                            {{ $visitSchedule->observations }}
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Bloque de recomendaciones según destinatario --}}
        <div class="section">
            @if(!empty($isVisitor) && $isVisitor)
                <div class="section-title">Recomendaciones para su visita</div>
                <div class="card">
                    <ul style="padding-left:18px; margin:0; color:#374151; font-size:13px;">
                        <li>Preséntese en portería unos minutos antes de la hora indicada.</li>
                        <li>Lleve su documento de identidad para el registro de ingreso.</li>
                        <li>Siga las indicaciones del personal de Seguridad y del encargado interno.</li>
                    </ul>
                </div>
            @else
                <div class="section-title">Recordatorio para el encargado</div>
                <div class="card">
                    <ul style="padding-left:18px; margin:0; color:#374151; font-size:13px;">
                        <li>Verifique el ambiente asignado y la disponibilidad de recursos.</li>
                        <li>Coordine con la empresa/visitante cualquier instrucción adicional si es necesario.</li>
                        <li>Informe a Coordinación Académica en caso de requerir cambios en la programación.</li>
                    </ul>
                </div>
            @endif
        </div>

        {{-- Botón principal --}}
        <p style="text-align:center;">
            <a href="{{ $publicUrl }}" class="btn-primary" target="_blank" rel="noopener">
                Ver detalle en SIGAC
                <span>➜</span>
            </a>
        </p>

        <p style="font-size:12px; color:#4b5563;">
            Si el botón no funciona, copie y pegue este enlace en su navegador:
        </p>
        <p style="font-size:11px; color:#6b7280; word-break:break-all;">
            {{ $publicUrl }}
        </p>

        <div class="divider"></div>

        <p style="font-size:13px; color:#374151;">
            @if(!empty($isVisitor) && $isVisitor)
                Ante cualquier duda sobre la visita, puede comunicarse con la Coordinación Académica del Centro o
                con el encargado interno asignado.
            @else
                Por favor verifique la programación y, si requiere cambios, póngase en contacto con
                la Coordinación Académica del Centro.
            @endif
        </p>

        <p style="font-size:13px; color:#374151; margin-top:12px;">
            Atentamente,<br>
            <strong>Equipo SIGAC — SICEFA</strong><br>
            Servicio Nacional de Aprendizaje SENA
        </p>

        <p class="footer-text">
            Este mensaje fue generado automáticamente por el sistema SIGAC/SICEFA.  
            Por favor, no responda directamente a este correo.
        </p>
    </div>
</body>
</html>
