<?php

namespace Modules\GDF\Services;

class GdfCostFormatter
{
    public static function transport(?string $json): string
    {
        if (!$json || !is_string($json)) return '—';

        $d = json_decode($json, true);
        if (!is_array($d)) return e($json);

        $transportKey = strtolower((string)($d['transport'] ?? ''));
        $transport = match ($transportKey) {
            'bus'        => 'Bus',
            'van'        => 'Camioneta',
            'motorcycle' => 'Moto',
            'moto'       => 'Moto',
            'air'        => 'Aéreo',
            'aereo'      => 'Aéreo',
            'terrestre'  => 'Terrestre',
            default      => $transportKey ? ucfirst($transportKey) : '—',
        };


        $trip = strtolower((string)($d['trip_type'] ?? ($d['direction'] ?? '')));
        $direction = match ($trip) {
            'roundtrip', 'round_trip' => 'Ida y vuelta',
            'return'                 => 'Ida y vuelta',
            'oneway', 'one_way'      => 'Solo ida',
            default                  => '—',
        };

        $unit  = (float)($d['base'] ?? ($d['unit_amount'] ?? 0));
        $mult  = (int)($d['mult'] ?? ($d['multiplier'] ?? 1));

        $total = (float)($d['total'] ?? ($unit * max(1, $mult)));

        $source = (string)($d['source'] ?? '');
        $rateId = $d['rate_id'] ?? null;

        $fmtUnit  = number_format($unit, 0, ',', '.');
        $fmtTotal = number_format($total, 0, ',', '.');

        $lines = [];
        $lines[] = "<strong>Transporte ({$transport})</strong>";
        $lines[] = "• Tipo de viaje: {$direction}";
        $lines[] = "• Valor base: \${$fmtUnit}";
        $lines[] = "• Trayectos: {$mult}";
        if ($source !== '') $lines[] = "• Fuente: " . e($source);
        if (!is_null($rateId) && $rateId !== '') $lines[] = "• Tarifa ID: " . e((string)$rateId);
        $lines[] = "<strong>Total: \${$fmtTotal}</strong>";

        return implode("<br>", $lines);
    }
}
