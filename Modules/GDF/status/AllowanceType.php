<?php

namespace Modules\GDF\Status;

final class AllowanceType
{
    public static function label(?string $type): string
    {
        $t = strtolower(trim((string)$type));

        return match ($t) {
            'per_diem' => 'Viático (per diem)',
            'meals'    => 'Comidas',
            'lodging'  => 'Hospedaje',
            'fuel'     => 'Gasolina',
            default    => $t !== '' ? strtoupper($t) : 'N/D',
        };
    }
}
