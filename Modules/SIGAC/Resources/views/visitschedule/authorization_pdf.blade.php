<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Autorización de visita - SIGAC / SICEFA</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 40px 50px;
            line-height: 1.5;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 5px;
        }

        .logo {
            width: 90px;
            height: auto;
        }

        .header-text {
            text-align: center;
            font-weight: bold;
            margin-bottom: 25px;
            font-size: 12px;
        }

        h1 {
            text-align: center;
            font-size: 18px;
            margin-bottom: 25px;
            text-transform: uppercase;
        }

        .paragraph {
            text-align: justify;
            margin-bottom: 18px;
        }

        .label { font-weight: bold; }

        .signature-container {
            margin-top: 60px;
            text-align: center;
        }

        .signature-img {
            width: 180px;
            height: auto;
        }

        .signature-name {
            margin-top: 5px;
            font-weight: bold;
            font-size: 13px;
        }

        .signature-role {
            font-size: 12px;
            margin-top: -3px;
        }
    </style>
</head>

<body>

@php
    $companyName   = optional($visit->company)->name ?? 'N/A';
    $contactName   = $visit->contact_name ?: (optional($visit->person)->full_name ?? 'N/D');
    $contactPhone  = $visit->contact_phone ?: 'N/D';
    $activity      = $schedule->activity ?: 'Actividad programada';
    $dateFormatted = \Carbon\Carbon::parse($schedule->date)->format('d/m/Y');
    $hourStart     = \Carbon\Carbon::parse($schedule->start_time)->format('H:i');
    $hourEnd       = \Carbon\Carbon::parse($schedule->end_time)->format('H:i');
    $environment   = optional($schedule->environment)->name ?? 'Sin ambiente asignado';
    $peopleCount   = $visit->number_of_people ?? 'N/D';

    $tipoTexto = $visit->type === 'practica'
        ? 'realizar una práctica formativa'
        : 'realizar una visita académica';
@endphp

    {{-- LOGO --}}
    <div class="logo-container">
        <img src="{{ public_path('modules/sigac/images/pdf/Logo_sena.png') }}" class="logo">
    </div>

    {{-- ENCABEZADO --}}
    <div class="header-text">
        SERVICIO NACIONAL DE APRENDIZAJE – SENA <br>
        Centro de Formación Agroindustrial <br>
        Coordinación Académica
    </div>

    <h1>AUTORIZACIÓN DE VISITA</h1>

    {{-- TEXTO PRINCIPAL --}}
    <div class="paragraph">
        La <strong>Coordinación Académica</strong> autoriza el ingreso de la empresa o entidad
        <strong>{{ $companyName }}</strong>, representada por
        <strong>{{ $contactName }}</strong> (tel. {{ $contactPhone }}),
        con el fin de <strong>{{ $tipoTexto }}</strong> en las instalaciones del Centro.
    </div>

    <div class="paragraph">
        La actividad se desarrollará conforme a la siguiente programación:
    </div>

    {{-- DATOS --}}
    <div class="paragraph">
        <span class="label">Actividad:</span> {{ $activity }} <br>
        <span class="label">Fecha:</span> {{ $dateFormatted }} <br>
        <span class="label">Horario:</span> {{ $hourStart }} – {{ $hourEnd }} <br>
        <span class="label">Ambiente:</span> {{ $environment }} <br>
        <span class="label">Número de personas autorizadas:</span> {{ $peopleCount }}
    </div>

    {{-- OBSERVACIONES --}}
    @if (!empty($schedule->observations) || !empty($visit->observations))
        <div class="paragraph">
            <span class="label">Observaciones:</span><br>
            {!! nl2br(e($schedule->observations ?? $visit->observations)) !!}
        </div>
    @endif

    <div class="paragraph">
        Esta autorización es válida únicamente para la fecha, horario y número de personas indicados.
        Cualquier ajuste deberá ser gestionado nuevamente a través de la Coordinación Académica.
    </div>

    {{-- FIRMA --}}
    <div class="signature-container">
        <img src="{{ public_path('modules/sigac/images/pdf/firma.png') }}" class="signature-img">

        <div class="signature-name">
            {{ config('sigac.academic_coordinator_name', 'Coordinador Académico') }}
        </div>
        <div class="signature-role">Coordinador Académico</div>
    </div>

</body>
</html>
