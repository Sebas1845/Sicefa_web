<?php

namespace Modules\GDF\Services;

use Illuminate\Support\Arr;

class TravelLogTranslator
{
    /* ============================================================
     * 1) Acción (badge): submitted -> Enviada, etc.
     * ============================================================ */
    public function actionLabel(?string $action): string
    {
        $action = trim((string)$action);
        if ($action === '') return '—';

        $key = "gdf::logs.actions.$action";
        $t = trans($key);

        return $t !== $key ? $t : $action; // fallback si falta traducción
    }

    /* ============================================================
     * 2) Descripción (texto): JSON -> texto legible
     * ============================================================ */
    public function descriptionLabel(?string $description): string
    {
        if (!$description) return '—';

        $d = json_decode($description, true);
        if (!is_array($d)) {
            // no es json => muestra original
            return $description;
        }

        $out = [];

        // total_amount
        if (isset($d['total_amount'])) {
            $out[] = $this->field('total_amount') . ': ' . $this->money($d['total_amount']);
        }

        // created_from_sigac payload: program_request_id + place_type_db + municipality_id + village_id
        if (isset($d['program_request_id'])) {
            $out[] = 'SIGAC: Programa #' . (int)$d['program_request_id'];

            $placeType = (string)($d['place_type_db'] ?? '');
            if ($placeType !== '') {
                $out[] = 'Lugar: ' . $this->placeType($placeType);
            }

            if (!empty($d['municipality_id'])) {
                $out[] = 'Municipio ID: ' . (int)$d['municipality_id'];
            }
            if (!empty($d['village_id'])) {
                $out[] = 'Vereda ID: ' . (int)$d['village_id'];
            }
        }

        // create_all_saved: segments, costs, allowances
        foreach (['segments','costs','allowances'] as $k) {
            if (isset($d[$k]) && is_numeric($d[$k])) {
                $out[] = ucfirst($k) . ': ' . (int)$d[$k];
            }
        }

        // transport_sync / transport_applied (depende como lo guardes)
        $ts = Arr::get($d, 'transport_sync');
        if (!is_array($ts)) $ts = Arr::get($d, 'transport_applied');
        if (is_array($ts)) {
            $out[] = $this->transportBlock($ts);
        }

        // Si no detectó nada, devuelve el JSON original (para no perder info)
        $txt = array_values(array_filter($out, fn($x)=> is_string($x) && trim($x) !== ''));
        return $txt ? implode(' | ', $txt) : $description;
    }

    private function transportBlock(array $ts): string
    {
        $parts = [];

        if (array_key_exists('applied', $ts)) {
            $parts[] = 'Aplicado: ' . ($ts['applied'] ? 'Sí' : 'No');
        }

        if (!empty($ts['mode'])) {
            $parts[] = 'Modo: ' . $this->mode((string)$ts['mode']);
        }

        if (!empty($ts['transport'])) {
            $parts[] = $this->field('transport') . ': ' . $this->map('transport', (string)$ts['transport']);
        }

        if (!empty($ts['direction'])) {
            $parts[] = $this->field('direction') . ': ' . $this->map('direction', (string)$ts['direction']);
        }

        if (!empty($ts['trip_type'])) {
            $parts[] = $this->field('trip_type') . ': ' . $this->map('trip_type', (string)$ts['trip_type']);
        }

        if (isset($ts['multiplier']) && is_numeric($ts['multiplier'])) {
            $parts[] = 'Multiplicador: ' . (int)$ts['multiplier'];
        }

        if (isset($ts['unit_amount']) && is_numeric($ts['unit_amount'])) {
            $parts[] = $this->field('unit_amount') . ': ' . $this->money($ts['unit_amount']);
        }

        if (isset($ts['per_segment']) && is_numeric($ts['per_segment'])) {
            $parts[] = 'Por segmento: ' . (int)$ts['per_segment'];
        }

        if (isset($ts['sum']) && is_numeric($ts['sum'])) {
            $parts[] = 'Suma: ' . $this->money($ts['sum']);
        }

        return implode(' · ', array_filter($parts));
    }

    private function field(string $name): string
    {
        $key = "gdf::logs.fields.$name";
        $t = trans($key);
        return $t !== $key ? $t : $name;
    }

    private function map(string $group, string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';

        $key = "gdf::logs.$group.$value";
        $t = trans($key);

        return $t !== $key ? $t : $value;
    }

    private function money($n): string
    {
        return '$ ' . number_format((float)$n, 0, ',', '.');
    }

    private function mode(string $mode): string
    {
        // tus modos típicos
        return match($mode) {
            'same_for_all' => 'Mismo para todos',
            'per_segment'  => 'Por segmento',
            default        => $mode,
        };
    }

    private function placeType(string $placeTypeDb): string
    {
        return match($placeTypeDb) {
            'municipio', 'municipality' => 'Municipio',
            'vereda', 'village'         => 'Vereda',
            default                     => $placeTypeDb,
        };
    }
}
