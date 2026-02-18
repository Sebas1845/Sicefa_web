<?php

namespace Modules\GDF\Status;

final class TravelStatus
{
    public static function label(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'draft'                => 'Borrador',
            'submitted'            => 'Enviada',
            'returned'             => 'Devuelta por apoyo',
            'rejected'             => 'Rechazada',
            'approved'             => 'Validada por apoyo',
            'pending_treasury'     => 'En Tesorería',
            'approved_by_treasury' => 'Aprobada por Tesorería',
            'executed'             => 'Ejecutada',
            'confirmed'            => 'Confirmada (cerrada)',
            'cancelled'            => 'Cancelada',
            default                => $s !== '' ? strtoupper($s) : 'N/D',
        };
    }

    public static function badge(?string $status): string
    {
        $s = strtolower(trim((string)$status));

        return match ($s) {
            'draft'                => 'secondary',
            'submitted'            => 'warning',
            'approved'             => 'success',
            'returned'             => 'danger',
            'rejected'             => 'dark',
            'pending_treasury'     => 'dark',
            'approved_by_treasury' => 'info',
            'executed'             => 'primary',
            'confirmed'            => 'primary',
            'cancelled'            => 'secondary',
            default                => 'info',
        };
    }
}
