<?php

namespace App\Mail\SIGAC\ProgramRequest;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\SIGAC\Entities\ProgramRequest;

class ProgramRequestStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ProgramRequest $pr,
        public string $type, // confirmed | returned | dismissed
        public array $payload = []
    ) {}

    public function build()
    {
        $subjects = [
            'confirmed'  => "SICEFA | Solicitud confirmada #{$this->pr->id}",
            'returned'   => "SICEFA | Solicitud devuelta #{$this->pr->id}",
            'dismissed'  => "SICEFA | Solicitud desestimada #{$this->pr->id}",
        ];

        return $this->from(
                config('mail.from.address'),
                'GDF/SITRAV - SICEFA'
            )
            ->subject($subjects[$this->type] ?? "SICEFA | Solicitud #{$this->pr->id}")
            ->view('sigac::emails.program_request_status');
    }
}
