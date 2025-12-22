<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Autorización de visita - SIGAC / SICEFA</title>
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

        h1 {
            font-size: 20px;
            margin: 0 0 6px;
            color: #111827;
        }

        .subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 16px;
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
    </style>
</head>

<body>
    <div class="mail-wrapper">
        <h1>Autorización de visita</h1>
        <div class="subtitle">
            Sistema de Gestión Académica — SIGAC / SICEFA
        </div>

        <p style="font-size:14px; color:#374151;">
            Cordial saludo, <br><br>
            Se remite la autorización de ingreso para la siguiente visita programada al Centro:
        </p>

        <div class="section-title">Datos principales</div>
        <div class="card">
            <div class="grid">
                <div>
                    <div class="label">Empresa / Entidad</div>
                    <div class="value">
                        {{ optional($visit->company)->name ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="label">Solicitante</div>
                    <div class="value">
                        {{ optional($visit->person)->full_name ?? ($visit->contact_name ?? '—') }}
                    </div>
                </div>
                <div>
                    <div class="label">Fecha</div>
                    <div class="value">
                        {{ \Carbon\Carbon::parse($schedule->date)->format('d/m/Y') }}
                    </div>
                </div>
                <div>
                    <div class="label">Horario</div>
                    <div class="value">
                        {{ $schedule->start_time }} – {{ $schedule->end_time }}
                    </div>
                </div>
                <div>
                    <div class="label">Ambiente</div>
                    <div class="value">
                        {{ optional($schedule->environment)->name ?? 'Por definir' }}
                    </div>
                </div>
                <div>
                    <div class="label">N.º de personas</div>
                    <div class="value">
                        {{ $visit->number_of_people ?? '—' }}
                    </div>
                </div>
            </div>

            @if (!empty($schedule->observations))
                <div style="margin-top:10px;">
                    <div class="label">Observaciones</div>
                    <div class="value">
                        {{ $schedule->observations }}
                    </div>
                </div>
            @endif
        </div>

        <p style="font-size:13px; color:#374151; margin-top:16px;">
            Se adjunta en este correo el documento en PDF con la autorización correspondiente,
            firmado por la Coordinación Académica.
        </p>

        <p style="font-size:12px; color:#9ca3af; margin-top:12px;">
            Este mensaje fue generado automáticamente por SIGAC/SICEFA.
        </p>
    </div>
</body>

</html>
