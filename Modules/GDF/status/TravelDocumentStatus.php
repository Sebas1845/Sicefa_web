<?php

namespace Modules\GDF\Status;

final class TravelDocumentStatus
{
    public static function label(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'draft'     => 'Borrador',
            'submitted' => 'Subido',
            'approved'  => 'Aprobado',
            'rejected'  => 'Rechazado',
            default     => $s !== '' ? strtoupper($s) : 'N/D',
        };
    }

    public static function badge(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'draft'     => 'secondary',
            'submitted' => 'warning',
            'approved'  => 'success',
            'rejected'  => 'danger',
            default     => 'secondary',
        };
    }
}
