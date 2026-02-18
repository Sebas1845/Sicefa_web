<?php

namespace Modules\GDF\Status;

final class MovementType
{
    public static function label(?string $type): string
    {
        $t = strtolower(trim((string) $type));

        return match ($t) {
            'addition'    => 'Adición',
            'commitment'  => 'Compromiso',
            'reversal'    => 'Reversión',
            'adjustment'  => 'Ajuste',
            'execute'     => 'Ejecución',
            default       => $t !== '' ? strtoupper($t) : 'N/D',
        };
    }

    public static function badge(?string $type): string
    {
        $t = strtolower(trim((string) $type));

        return match ($t) {
            'addition'    => 'success',   // verde
            'commitment'  => 'warning',   // amarillo
            'reversal'    => 'secondary', // gris
            'adjustment'  => 'info',      // azul
            'execute'     => 'danger',    // rojo
            default       => 'dark',
        };
    }

    // opcional
    public static function icon(?string $type): string
    {
        $t = strtolower(trim((string) $type));

        return match ($t) {
            'addition'    => 'bi bi-plus-circle',
            'commitment'  => 'bi bi-lock',
            'reversal'    => 'bi bi-arrow-counterclockwise',
            'adjustment'  => 'bi bi-sliders',
            'execute'     => 'bi bi-cash-coin',
            default       => 'bi bi-dot',
        };
    }
}
