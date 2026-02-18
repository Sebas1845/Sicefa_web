<?php

namespace Modules\GDF\Status;

final class TravelDocumentType
{
    public static function label(?string $type): string
    {
        $t = strtolower(trim((string)$type));

        return match ($t) {
            'agenda'        => 'Agenda / Cronograma',
            'invitation'    => 'Invitación',
            'authorization' => 'Autorización',
            'justification' => 'Justificación del traslado',
            'proof'         => 'Soporte / Evidencia',
            'other'         => 'Otro documento',
            default         => $t !== '' ? strtoupper($t) : 'N/D',
        };
    }
}
