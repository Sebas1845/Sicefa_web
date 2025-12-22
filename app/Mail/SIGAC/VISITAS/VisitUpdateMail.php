<?php
namespace App\Mail\SIGAC\VISITAS;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\SIGAC\Entities\VisitRequest;
use Modules\SIGAC\Entities\VisitSchedule;

class VisitUpdateMail extends Mailable
{
    use Queueable, SerializesModels;

    public VisitRequest $visitRequest;
    public VisitSchedule $visitSchedule;
    public array $changes;
    public string $event;        // 'rescheduled' | 'updated' | 'canceled'
    public array $summaryLines;  // líneas humanizadas
    public string $publicUrl;
    public bool $isVisitor;      // 👈 NUEVO

    public function __construct(
        VisitRequest $visitRequest,
        VisitSchedule $visitSchedule,
        array $changes,
        string $event,
        array $summaryLines,
        string $publicUrl,
        bool $isVisitor = false      // 👈 por defecto false
    ) {
        $this->visitRequest  = $visitRequest;
        $this->visitSchedule = $visitSchedule;
        $this->changes       = $changes;
        $this->event         = $event;
        $this->summaryLines  = $summaryLines;
        $this->publicUrl     = $publicUrl;
        $this->isVisitor     = $isVisitor;
    }

    public function build()
    {
        $subjectBase = match ($this->event) {
            'rescheduled' => 'Visita reprogramada',
            'canceled'    => 'Visita cancelada',
            default       => 'Actualización de visita',
        };

        $subject = $this->isVisitor
            ? "{$subjectBase} - SENA"
            : "{$subjectBase} - SIGAC / SICEFA";

        return $this->subject($subject)
            ->view('sigac::emails.visit_updated')  // 👈 nueva vista
            ->with([
                'visitRequest'  => $this->visitRequest,
                'visitSchedule' => $this->visitSchedule,
                'changes'       => $this->changes,
                'event'         => $this->event,
                'summaryLines'  => $this->summaryLines,
                'publicUrl'     => $this->publicUrl,
                'isVisitor'     => $this->isVisitor,
            ]);
    }
}
