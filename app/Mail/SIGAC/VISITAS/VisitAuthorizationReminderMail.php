<?php

namespace App\Mail\SIGAC\VISITAS;

use MODULES\SIGAC\Entities\VisitRequest;
use Modules\SIGAC\Entities\VisitSchedule;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class VisitAuthorizationReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public VisitRequest $visit;
    public VisitSchedule $schedule;
    public ?string $pdfPath;

    /**
     * @param VisitRequest   $visit
     * @param VisitSchedule  $schedule
     * @param string|null    $pdfPath  Ruta relativa en el disk('public'), por ej:
     *                                 "sigac/visit_authorizations/autorizacion_visita_5.pdf"
     */
    public function __construct(VisitRequest $visit, VisitSchedule $schedule, ?string $pdfPath = null)
    {
        $this->visit    = $visit;
        $this->schedule = $schedule;
        $this->pdfPath  = $pdfPath;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $mail = $this->subject('Autorización de ingreso — Visita programada')
            ->view('sigac::emails.visit_authorization_reminder')
            ->with([
                'visit'    => $this->visit,
                'schedule' => $this->schedule,
            ]);

        // Adjuntar PDF si viene ruta y existe en disk('public')
        if ($this->pdfPath && Storage::disk('public')->exists($this->pdfPath)) {
            $fullPath = Storage::disk('public')->path($this->pdfPath);

            $mail->attach($fullPath, [
                'as'   => 'autorizacion_visita_' . $this->schedule->id . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
