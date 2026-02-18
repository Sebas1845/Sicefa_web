<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Certificación Contractual</title>
    <style>
        @page {
            margin-top: 3.5cm;
            margin-bottom: 2.5cm;
            margin-left: 2.5cm;
            margin-right: 2.5cm;
        }

        body {
            font-family: 'Calibri', 'Arial', sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* ========== HEADER FIJO EN TODAS LAS PÁGINAS ========== */
        .page-header {
            position: fixed;
            top: -3.5cm;
            left: 0;
            right: 0;
            height: 3cm;
            text-align: center;
            padding: 0;
        }

        .page-header img {
            height: 70px;
            width: auto;
            margin-top: 20px;
        }

        /* ========== NÚMERO DE CERTIFICACIÓN - ESQUINA SUPERIOR IZQUIERDA ========== */
        .cert-number {
            font-family: 'Times New Roman', Times, serif;
            font-style: italic;
            position: fixed;
            top: -1.3cm;
            left: 0;
            font-weight: normal;
            font-size: 18pt;
            font-weight: bold;
            z-index: 900;
            color: #000;

        }

        /* ========== FOOTER FIJO EN TODAS LAS PÁGINAS ========== */
        .page-footer {
            position: fixed;
            bottom: -2.5cm;
            left: 0;
            right: 0;
            height: 2cm;
            text-align: center;
            font-size: 11pt;
            color: #008000;
            line-height: 1.4;
            padding-top: 10px;
        }

        .page-footer strong {
            font-weight: bold;
        }

        /* ========== CÓDIGO DE VERSIÓN - LATERAL DERECHO ========== */
        .version-code {
            position: fixed;
            bottom: -60px;
            right: -4cm;
            transform: rotate(-90deg);
            transform-origin: center center;
            font-size: 11pt;
            color: #000;
            white-space: nowrap;
        }



/* ========================================================================
   VERSIÓN SIMPLIFICADA (COPIA Y PEGA DIRECTO)
   ======================================================================== */

        /* ========== CONTENIDO PRINCIPAL ========== */
        .content {
            text-align: justify;
            margin-bottom: 30px;
            font-size: 11pt;
        }

        .content p {
            font-size: 11pt;
            margin: 0 0 10px 0;
        }

        /* ========== DETALLES DEL CONTRATO ========== */
        .contract-details {
            margin: 15px 0;
            font-size: 11pt;
        }

        .detail-row {
            display: table;
            width: 100%;
            margin: 10px 0;
            font-size: 11pt;
        }

        .detail-label {
            display: table-cell;
            width: 240px;
            vertical-align: top;
            padding-right: 10px;
            font-size: 11pt;
            font-weight: bold;
        }

        .detail-colon {
            display: table-cell;
            width: 15px;
            font-size: 11pt;
        }

        .detail-value {
            display: table-cell;
            text-align: justify;
            vertical-align: top;
            font-size: 11pt;
        }

        /* ========== OBLIGACIONES ========== */
        .obligations {
            margin: 20px 0;
            font-size: 11pt;
        }

        .obligations-title {
            font-weight: bold;
            margin: 20px 0 15px 0;
            font-size: 11pt;
        }

        .obligation-item {
            margin: 12px 0;
            text-align: justify;
            line-height: 1.5;
            font-size: 11pt;
            page-break-inside: avoid;
        }

        .sub-item {
            margin: 8px 0 8px 20px;
            text-align: justify;
            line-height: 1.5;
            font-size: 11pt;
            page-break-inside: avoid;
        }

        /* ========== INFORMACIÓN DE EXPEDICIÓN ========== */
        .expedition-info {
            margin: 30px 0 20px 0;
            text-align: justify;
            font-size: 11pt;
        }

        /* ========== FIRMAS ========== */
        .signatures {
            margin-top: 60px;
            font-size: 11pt;
        }

        .signature-main {
            text-align: center;
            margin-top: 70px;
            margin-bottom: 50px;
        }

        .signature-main p {
            margin: 3px 0;
            font-size: 11pt;
        }

        .signature-main .name {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 11pt;
        }

        .signature-secondary {
            margin: 15px 0;
            text-align: left;
        }

        .signature-secondary p {
            margin: 2px 0;
            font-size: 11pt;
        }

        .signature-secondary .label {
            font-weight: bold;
        }

        /* ========== UTILIDADES ========== */
        .mt-20 {
            margin-top: 20px;
        }

        /* Evitar que los items se corten entre páginas */
        p {
            orphans: 3;
            widows: 3;
        }

        /* Asegurar tamaño de fuente consistente */
        * {
            font-size: 11pt !important;
        }

        img {
            font-size: inherit !important;
        }

        /* ========== CONTENEDOR DEL TÍTULO CON BORDE ========== */
.title-section {
         /* ✅ BORDE NEGRO */
                 /* ✅ Padding interno */
    text-align: justify;
    margin: -10px auto 30px auto;
        width: 100%;
   

}

/* ========== PRIMERA LÍNEA DEL TÍTULO ========== */
.title-section h1 {
    font-size: 11pt  ;
    line-height: 1.25;  
    font-weight: bold;
    text-transform: uppercase;           /* ✅ Pequeño espacio entre líneas */
    padding: 0;
    color: #000000;
    font-family: 'Calibri', 'Arial', sans-serif;
    white-space: nowrap;
    transform: scaleX(0.85);   /* ajusta: 0.9 – 0.95 */
    transform-origin: left;
}

/* ========== SEGUNDA LÍNEA DEL TÍTULO ========== */
.title-section h2 {
    font-size: 10.5pt !important;
    text-align: center;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.25px;
    line-height: 1;
    color: #000000;
    font-family: 'Calibri', 'Arial', sans-serif;
  
}

/* ========== "HACE CONSTAR" (h3 o clase especial) ========== */
.title-section h3,
.title-section .makes-constar {
    font-size: 11pt;
    text-align: center;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 2px;               /* ✅ Más espaciado */
    top: 50px;
    color: #000000;
    font-family: 'Calibri', 'Arial', sans-serif;
    border: none;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    font-size: 11pt;
}

table tr {
    line-height: 1.5;
}

table td {
    padding: 0;
    vertical-align: top;
    font-size: 11pt;
}

table td.contract-state-row {
    width: 250px;                    /* ✅ Mismo ancho que detail-label */
    font-weight: bold;
    padding-right: 15px;
}

table td:last-child {
    text-align: left;
    font-weight: normal;
}


    </style>
</head>

<body>
    <!-- HEADER EN TODAS LAS PÁGINAS -->
    <div class="page-header">
        <img src="{{ public_path('modules/gth/images/Logo_sena.png') }}" alt="Logo SENA">
    </div>

  <!-- NÚMERO DE CERTIFICACIÓN -->
<div class="cert-number">
    Certificación No. {{ $certificateNumber }}
</div>

    <!-- FOOTER EN TODAS LAS PÁGINAS (2 líneas) -->
    <div class="page-footer">
        <strong>{{ $certificate->center_name }}</strong><br>
        {{ $certificate->center_address }}
    </div>

    <!-- CÓDIGO DE VERSIÓN LATERAL -->
    <div class="version-code">
        {{ $certificate->version_code }}
    </div>

    @php
        $contract = $certificate->contractor;

        $fechaExpedicion = \Carbon\Carbon::parse($certificate->expedition_date ?? now());
        $diaExpedicion = $fechaExpedicion->day;
        $mesExpedicion = $fechaExpedicion->monthName;
        $anioExpedicion = $fechaExpedicion->year;

        $numerosPalabras = [
            1 => 'uno', 2 => 'dos', 3 => 'tres', 4 => 'cuatro', 5 => 'cinco',
            6 => 'seis', 7 => 'siete', 8 => 'ocho', 9 => 'nueve', 10 => 'diez',
            11 => 'once', 12 => 'doce', 13 => 'trece', 14 => 'catorce', 15 => 'quince',
            16 => 'dieciséis', 17 => 'diecisiete', 18 => 'dieciocho', 19 => 'diecinueve',
            20 => 'veinte', 21 => 'veintiuno', 22 => 'veintidós', 23 => 'veintitrés',
            24 => 'veinticuatro', 25 => 'veinticinco', 26 => 'veintiséis', 27 => 'veintisiete',
            28 => 'veintiocho', 29 => 'veintinueve', 30 => 'treinta', 31 => 'treinta y uno'
        ];
        $diaTexto = $numerosPalabras[$diaExpedicion] ?? $diaExpedicion;

        $fechaContrato = \Carbon\Carbon::parse($contract->contract_date)->format('d/m/Y');
        $fechaInicio = \Carbon\Carbon::parse($certificate->execution_start_date)->format('d/m/Y');
        $fechaFin = \Carbon\Carbon::parse($certificate->execution_end_date)->format('d/m/Y');

        // ============ PROCESAMIENTO DE OBLIGACIONES (VERSIÓN QUE FUNCIONABA) ============
        $obligacionesRaw = $contract->contract_obligations ?? '';
        
        // Primero intentar separar obligaciones mezcladas en un solo párrafo
        // Solo si detectamos el patrón "X.X texto X.X texto"
        if (preg_match('/\d+\.\d+\s+[^\n]+\s+\d+\.\d+/', $obligacionesRaw)) {
            // Hay obligaciones mezcladas, separarlas
            $obligacionesRaw = preg_replace('/(\d+\.\d+)\s+/', "\n$1 ", $obligacionesRaw);
        }
        
        $lineas = preg_split('/\r\n|\r|\n/', $obligacionesRaw);
        
        $obligaciones = [];
        $numeroActual = 1;
        $obligacionActual = null;
        
        foreach ($lineas as $linea) {
            $lineaTrim = trim($linea);
            
            if (empty($lineaTrim)) {
                continue;
            }
            
            // Detectar formato con sub-numeración: 2.1, 2.2, 2.3, etc
            if (preg_match('/^(\d+)\.(\d+)\s+(.*)$/u', $lineaTrim, $matches)) {
                if ($obligacionActual !== null) {
                    $obligaciones[] = $obligacionActual;
                }
                
                $obligacionActual = [
                    'numero' => $numeroActual,
                    'texto' => $matches[3],
                    'sub_items' => []
                ];
                $numeroActual++;
            }
            // Detectar obligación principal simple: 1., 2., 3., etc
            elseif (preg_match('/^(\d+)[\.\)]\s*(.*)$/u', $lineaTrim, $matches)) {
                if ($obligacionActual !== null) {
                    $obligaciones[] = $obligacionActual;
                }
                
                $obligacionActual = [
                    'numero' => $numeroActual,
                    'texto' => $matches[2],
                    'sub_items' => []
                ];
                $numeroActual++;
            }
            // Detectar sub-item (viñeta)
            elseif (preg_match('/^[•\-\*·]\s*(.*)$/u', $lineaTrim, $matches)) {
                if ($obligacionActual !== null) {
                    $obligacionActual['sub_items'][] = $matches[1];
                }
            }
            // Línea de continuación
            else {
                if ($obligacionActual !== null) {
                    if (!empty($obligacionActual['texto'])) {
                        $obligacionActual['texto'] .= ' ' . $lineaTrim;
                    } else {
                        $obligacionActual['texto'] = $lineaTrim;
                    }
                }
            }
        }
        
        if ($obligacionActual !== null) {
            $obligaciones[] = $obligacionActual;
        }

        $genero = strtolower(trim($certificate->gender ?? ''));
        $esFemenino = in_array($genero, ['femenino', 'f', 'mujer', 'female']);
    @endphp

    <!-- CONTENIDO PRINCIPAL -->
    <div class="title-section">
    <h1>{{ $certificate->title_line_1 }}</h1>
    <h2>{{ $certificate->title_line_2 }}</h2>
    <h3>HACE CONSTAR</h3>
</div>


        <!-- Párrafo introductorio -->
        <div class="content">
            <p>
                Que {{ $esFemenino ? 'la señora' : 'el señor' }}
                <strong>{{ strtoupper($contract->person->first_name . ' ' .
                    $contract->person->first_last_name . ' ' .
                    $contract->person->second_last_name) }}</strong>,
                identificad{{ $esFemenino ? 'a' : 'o' }} con
                {{ strtolower($contract->person->document_type) }}
                {{ number_format($contract->person->document_number, 0, ',', '.') }}
                de {{ $certificate->place_of_issue }} celebró con <strong>EL SERVICIO NACIONAL DE APRENDIZAJE SENA,</strong> 
                el siguiente contrato de prestación de servicios personales regulado por la Ley 80 de 1993
                (Estatuto General de Contratación de la Administración Pública), modificada por la Ley 1150 de 2007,
                Decreto 1082 de 2015 y sus demás Decretos o normas reglamentarias, como se describe a continuación:
            </p>
        </div>

        <!-- Detalles del contrato -->
        <div class="contract-details">
            <div class="detail-row">
                <span class="detail-label">Número y Fecha del Contrato</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">{{ $contract->contract_number }} del {{ $fechaContrato }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Objeto</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">{{ $contract->contract_object ?? 'No especificado' }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Plazo de ejecución</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">Desde la fecha de inicio de ejecución del contrato hasta el {{ $fechaFin }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha de Inicio de Ejecución</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">{{ $fechaInicio }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Fecha de Terminación</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">{{ $fechaFin }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Valor del contrato</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">El valor total del contrato para todos los efectos legales y fiscales se fijó
                    en la suma {{ $totalInWords }}
                    (<strong>${{ number_format($contract->total_contract_value, 0, ',', '.') }}</strong>) M/CTE.</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Forma de Pago</span>
                <span class="detail-colon">:</span>
                <span class="detail-value">
                    @if($certificate->payment_type === 'mensual')
                        Honorarios mensuales por valor de ${{ number_format($certificate->monthly_payment, 0, ',', '.') }}
                        M/CTE y/o por la fracción del mes ejecutado.
                    @elseif($certificate->payment_type === 'horas')
                        Proporcional a las horas ejecutadas mensualmente por valor unitario de
                        ${{ number_format($certificate->unit_hour_value, 0, ',', '.') }} M/CTE.
                    @else
                        No especificada
                    @endif
                </span>
            </div>
            <table>
    <tr>
        <td class="contract-state-row" style="width: 200px;"><strong>Estado del Contrato</strong></td>
        <td style="  display: table-cell;
            width: 15px;
            font-size: 11pt;
            padding-left: 35px;">: </td> <td>{{ $contract->state === 'Activo' ? 'En ejecución' : 'Terminado' }}</td>
    </tr>
</table>

        </div>

        <!-- Título de Obligaciones -->
        <p class="obligations-title">Obligaciones Específicas del Contrato:</p>

        <!-- Obligaciones -->
        @if(count($obligaciones) > 0)
            <div class="obligations">
                @foreach($obligaciones as $obligacion)
                    <p class="obligation-item">
                        <strong>{{ $obligacion['numero'] }}.</strong> {{ $obligacion['texto'] }}
                    </p>
                    
                    @if(count($obligacion['sub_items']) > 0)
                        @foreach($obligacion['sub_items'] as $subItem)
                            <p class="sub-item">
                                <strong>•</strong> {{ $subItem }}
                            </p>
                        @endforeach
                    @endif
                @endforeach
            </div>
        @else
            <div class="obligations">
                <p class="obligation-item">No se especificaron obligaciones para este contrato.</p>
            </div>
        @endif

        <!-- Información de expedición -->
        <div class="expedition-info">
            <p>
                Se expide a solicitud de {{ $esFemenino ? 'la interesada' : 'el interesado' }},
                de acuerdo con la información registrada en los sistemas de información con los que cuenta el SENA
                y mediante los cuales reporta toda su información, el {{ $diaTexto }}
                ({{ str_pad($diaExpedicion, 2, '0', STR_PAD_LEFT) }})
                de {{ $mesExpedicion }} de {{ $anioExpedicion }}.
            </p>
        </div>

        <!-- Firmas -->
        <div class="signatures">
            <div class="signature-main">
                <p class="name">{{ $certificate->director_name ?? 'Sin asignar' }}</p>
                <p class="role">{{ $certificate->director_role ?? '' }}</p>
            </div>

            <div class="signature-secondary">
                <p><span class="label">Proyectó:</span> {{ $certificate->projected_by ?? 'Sin asignar' }}</p>
                <p>{{ $certificate->projected_by_role ?? '' }}</p>
            </div>

            <div class="signature-secondary">
                <p><span class="label">Revisó:</span> {{ $certificate->reviewed_by ?? 'Sin asignar' }}</p>
                <p>{{ $certificate->reviewed_by_role ?? '' }}</p>
            </div>
        </div>
    </div>
</body>

</html>