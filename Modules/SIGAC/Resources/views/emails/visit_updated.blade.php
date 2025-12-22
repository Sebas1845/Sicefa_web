<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>
        @if($event === 'canceled')
            Visita cancelada - SIGAC / SICEFA
        @elseif($event === 'rescheduled')
            Visita reprogramada - SIGAC / SICEFA
        @else
            Actualización de visita - SIGAC / SICEFA
        @endif
    </title>
    {{-- aquí mismo CSS que en "scheduled.blade.php" (puedes copiar/pegar) --}}
</head>
<body>
<div class="mail-wrapper">
    <div class="header">
        <div class="logo-circle">S</div>
        <div>
            <h1>
                @if(!empty($isVisitor) && $isVisitor)
                    @if($event === 'canceled')
                        Su visita ha sido cancelada
                    @elseif($event === 'rescheduled')
                        Su visita ha sido reprogramada
                    @else
                        Se han actualizado datos de su visita
                    @endif
                @else
                    @if($event === 'canceled')
                        Visita cancelada en SIGAC
                    @elseif($event === 'rescheduled')
                        Visita reprogramada en SIGAC
                    @else
                        Actualización de visita en SIGAC
                    @endif
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

    {{-- Intro según evento --}}
    <p style="font-size:14px; color:#374151;">
        @if($event === 'canceled')
            @if(!empty($isVisitor) && $isVisitor)
                Le informamos que la visita asociada a la solicitud
                <strong>#{{ $visitRequest->id }}</strong> ha sido
                <strong>cancelada</strong>.
            @else
                La visita asociada a la solicitud
                <strong>#{{ $visitRequest->id }}</strong> ha sido
                <strong>cancelada</strong>.
            @endif
        @elseif($event === 'rescheduled')
            @if(!empty($isVisitor) && $isVisitor)
                Le informamos que la visita asociada a la solicitud
                <strong>#{{ $visitRequest->id }}</strong> ha sido
                <strong>reprogramada</strong>. A continuación se muestran los nuevos datos:
            @else
                La visita asociada a la solicitud
                <strong>#{{ $visitRequest->id }}</strong> ha sido
                <strong>reprogramada</strong>. Revise los nuevos detalles:
            @endif
        @else
            Se han realizado cambios en la visita asociada a la solicitud
            <strong>#{{ $visitRequest->id }}</strong>.
        @endif
    </p>

    {{-- Resumen de cambios (las líneas que generas en el controlador) --}}
    @if(!empty($summaryLines))
        <div class="section">
            <div class="section-title">Resumen de cambios</div>
            <div class="card">
                <ul style="margin:0; padding-left:18px; font-size:13px; color:#374151;">
                    @foreach($summaryLines as $line)
                        <li>{!! $line !!}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif

    {{-- Info de la visita (igual que en el "scheduled") --}}
    {{-- ... (puedes reusar exactamente el bloque de información de la visita que ya tienes) --}}

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

    {{-- Cierre --}}
    <div class="divider"></div>

    <p style="font-size:13px; color:#374151;">
        @if(!empty($isVisitor) && $isVisitor)
            Ante cualquier duda sobre la visita, puede comunicarse con la Coordinación Académica del Centro
            o con el encargado interno asignado.
        @else
            Si considera que debe realizarse un nuevo ajuste, por favor comuníquese con la Coordinación Académica.
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
