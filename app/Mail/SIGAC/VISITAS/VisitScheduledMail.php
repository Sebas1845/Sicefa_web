<?php

namespace App\Mail\SIGAC\VISITAS;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\SIGAC\Entities\VisitRequest;
use Modules\SIGAC\Entities\VisitSchedule;

class VisitScheduledMail extends Mailable
{
    use Queueable, SerializesModels;

    public VisitRequest $visitRequest;
    public VisitSchedule $visitSchedule;
    public string $publicUrl;
    public bool $isVisitor;

    public function __construct(
        VisitRequest $visitRequest,
        VisitSchedule $visitSchedule,
        string $publicUrl,
        bool $isVisitor = false
    ) {
        $this->visitRequest  = $visitRequest;
        $this->visitSchedule = $visitSchedule;
        $this->publicUrl     = $publicUrl;
        $this->isVisitor     = $isVisitor;
    }

    public function build()
    {
        // Puedes usar la MISMA vista y cambiar textos con @if,
        // o dos vistas diferentes. Te dejo ejemplo con una sola.
        return $this->subject(
                $this->isVisitor
                    ? 'Confirmación de visita al SENA'
                    : 'Nueva visita asignada en SIGAC / SICEFA'
            )
            ->view('sigac::emails.scheduled')
            ->with([
                'visitRequest'  => $this->visitRequest,
                'visitSchedule' => $this->visitSchedule,
                'publicUrl'     => $this->publicUrl,
                'isVisitor'     => $this->isVisitor,
            ]);
    }
}
