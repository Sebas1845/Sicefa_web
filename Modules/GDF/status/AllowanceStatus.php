<?php

namespace Modules\GDF\Status;

final class AllowanceStatus
{
    public static function label(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'draft'     => 'Borrador',
            'liquidated'=> 'Liquidado',
            'approved'  => 'Aprobado',
            'rejected'  => 'Rechazado',
            default     => $s !== '' ? strtoupper($s) : 'N/D',
        };
    }

    public static function badge(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'draft'      => 'secondary',
            'liquidated' => 'info',
            'approved'   => 'success',
            'rejected'   => 'danger',
            default      => 'secondary',
        };
    }
}
