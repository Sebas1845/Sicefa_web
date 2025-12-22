<?php

namespace App\Mail\SIGAC\VISITAS;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Modules\SIGAC\Entities\VisitSchedule;

class SecurityVisitAuthorizationMail extends Mailable
{
    use Queueable, SerializesModels;

    public VisitSchedule $schedule;
    public ?string $pdfPath;

    /**
     * @param VisitSchedule $schedule
     * @param string|null   $pdfPath  Ruta relativa en disk('public'), ej:
     *                                'sigac/visit_authorizations/autorizacion_visita_10.pdf'
     */
    public function __construct(VisitSchedule $schedule, ?string $pdfPath = null)
    {
        $this->schedule = $schedule->load(['visitRequest.company', 'environment', 'personInCharge']);
        $this->pdfPath  = $pdfPath ?: $schedule->authorization_path;
    }

    public function build()
    {
        $visit = $this->schedule->visitRequest;

        $mail = $this->subject('Autorización de visita — SIGAC / SICEFA')
            ->view('sigac::emails.security_authorization')
            ->with([
                'schedule' => $this->schedule,
                'visit'    => $visit,
            ]);

        // ======= ADJUNTAR PDF SI EXISTE =======
        if ($this->pdfPath && Storage::disk('public')->exists($this->pdfPath)) {
            $mail->attach(
                Storage::disk('public')->path($this->pdfPath),
                [
                    'as'   => 'autorizacion_visita_' . $this->schedule->id . '.pdf',
                    'mime' => 'application/pdf',
                ]
            );
        }

        return $mail;
    }
}
