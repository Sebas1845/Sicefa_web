<?php

namespace App\Jobs\SIGAC\VISITAS;

use MODULES\SIGAC\Entities\VisitSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendSecurityVisitReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scheduleId,
        public ?string $authPath = null
    ) {}

    public function handle(): void
    {
        $schedule = VisitSchedule::with(['visitRequest.company', 'environment', 'personInCharge'])
            ->find($this->scheduleId);

        if (!$schedule || !$schedule->visitRequest) {
            return;
        }

        $visit = $schedule->visitRequest;

        /* ============================
         * 1) Asegurar PDF en disco
         * ============================ */
        $path = $this->authPath;

        if (!$path || !Storage::disk('public')->exists($path)) {
            $pdf = Pdf::loadView('sigac::visitschedule.authorization_pdf', [
                'schedule' => $schedule,
                'visit'    => $visit,
            ]);

            $dir = 'sigac/visit_authorizations';
            Storage::disk('public')->makeDirectory($dir);

            $name = 'autorizacion_visita_' . $schedule->id . '.pdf';
            $path = $dir . '/' . $name;

            Storage::disk('public')->put($path, $pdf->output());

            $schedule->authorization_path = $path;
            $schedule->save();
        }

        $fullPath = Storage::disk('public')->path($path);

        /* ============================
         * 2) Enviar a PORTERÍA
         * ============================ */
        $securityEmails = config('sigac.security_emails', []);
        $securityEmails = array_filter($securityEmails, fn ($m) => filter_var($m, FILTER_VALIDATE_EMAIL));

        foreach ($securityEmails as $email) {
            Mail::to($email)->send(
                (new \App\Mail\SIGAC\VISITAS\SecurityVisitAuthorizationMail($schedule))
                    ->attach($fullPath)
            );
        }

        /* ============================
         * 3) Enviar al VISITANTE
         * ============================ */
        $recipients = [];

        if ($visit->contact_email && filter_var($visit->contact_email, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = strtolower(trim($visit->contact_email));
        }

        $recipients = array_values(array_unique($recipients));

        foreach ($recipients as $email) {
            Mail::to($email)->send(
                (new \app\Mail\SIGAC\VISITAS\VisitAuthorizationReminderMail($visit, $schedule))
                    ->attach($fullPath)
            );
        }
    }
}
