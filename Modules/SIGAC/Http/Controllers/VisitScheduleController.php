<?php

namespace Modules\SIGAC\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Modules\SIGAC\Entities\VisitRequest;
use Modules\SIGAC\Entities\VisitSchedule;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Environment;
use Modules\SIGAC\Entities\InstructorProgram;
use Modules\SIGAC\Entities\EnvironmentInstructorProgram;
use Modules\SICA\Entities\ClassEnvironment;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Support\IcsBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use App\Mail\SIGAC\VISITAS\VisitScheduledMail;
use App\Mail\SIGAC\VISITAS\VisitUpdateMail;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Jobs\SIGAC\VISITAS\SendSecurityVisitReminder;
use app\Mail\SIGAC\VISITAS\SecurityVisitAuthorizationMail;
use Barryvdh\DomPDF\Facade\Pdf;   // 👈👈 AGREGAR ESTA LÍNEA






class VisitScheduleController extends Controller
{
    /**
     * Mostrar formulario para crear una agenda de visita.
     */
    public function create(VisitRequest $request)
    {
        $persons = Person::all()->mapWithKeys(function ($person) {
            $fullName = trim($person->first_name . ' ' . $person->first_last_name . ' ' . ($person->second_last_name ?? ''));
            return [$person->id => $fullName];
        });

        $environments = Environment::all()->pluck('name', 'id');

        $activities = VisitSchedule::select('activity')->distinct()->pluck('activity');

        return view('sigac::visitschedule.create', [
            'request'      => $request,
            'persons'      => $persons,
            'environments' => $environments,
            'activities'   => $activities,
            'titlePage'    => 'Agendar visita',
            'titleView'    => 'Agendar visita',
        ]);
    }

    /**
     * Buscador unificado de personal (empleado / contratista).
     */
    public function searchStaff(Request $request)
    {
        $q    = trim((string) $request->input('q', ''));
        $type = $request->input('type', 'all'); // all | employee | contractor

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $needle = '%' . str_replace(' ', '%', $q) . '%';

        // Empleados (planta)
        $employees = DB::table('employees')
            ->join('people', 'people.id', '=', 'employees.person_id')
            ->where(function ($w) use ($needle) {
                $w->where('people.first_name', 'like', $needle)
                    ->orWhere('people.first_last_name', 'like', $needle)
                    ->orWhere('people.second_last_name', 'like', $needle);
            })
            ->select([
                DB::raw('people.id as person_id'),
                DB::raw("TRIM(CONCAT_WS(' ', people.first_name, people.first_last_name, COALESCE(people.second_last_name,''))) as name"),
                DB::raw("'employee' as type"),
                DB::raw('employees.id as source_id'),
            ]);

        // Contratistas
        $contractors = DB::table('contractors')
            ->join('people', 'people.id', '=', 'contractors.person_id')
            ->where(function ($w) use ($needle) {
                $w->where('people.first_name', 'like', $needle)
                    ->orWhere('people.first_last_name', 'like', $needle)
                    ->orWhere('people.second_last_name', 'like', $needle);
            })
            ->select([
                DB::raw('people.id as person_id'),
                DB::raw("TRIM(CONCAT_WS(' ', people.first_name, people.first_last_name, COALESCE(people.second_last_name,''))) as name"),
                DB::raw("'contractor' as type"),
                DB::raw('contractors.id as source_id'),
            ]);

        if ($type === 'employee') {
            $union = $employees;
        } elseif ($type === 'contractor') {
            $union = $contractors;
        } else {
            $union = $employees->unionAll($contractors);
        }

        $results = DB::query()
            ->fromSub($union, 'u')
            ->orderBy('name')
            ->limit(25)
            ->get();

        return response()->json($results);
    }
    /**
     * Almacenar la agenda de la visita y actualizar el estado de la solicitud.
     */
    public function store(Request $request)
    {
        $minDate = Carbon::today('America/Bogota')->addDay()->toDateString();

        foreach (['start_time', 'end_time'] as $f) {
            $v = (string) $request->input($f, '');
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
                $request->merge([$f => substr($v, 0, 5)]);
            }
        }

        $validated = $request->validate([
            'visit_request_id'     => 'required|exists:visit_requests,id',
            'person_in_charge_id'  => 'required|exists:people,id',
            'notification_email'   => 'nullable|email',
            'activity'             => 'required|string',
            'date'                 => ['required', 'date', 'after_or_equal:' . $minDate],
            'start_time'           => ['required', 'date_format:H:i'],
            'end_time'             => ['required', 'date_format:H:i', 'after:start_time'],
            'environment_id'       => 'nullable|exists:environments,id',
            'observations'         => 'nullable|string',
        ]);

        // 1) Crear programación
        $schedule = VisitSchedule::create([
            'visit_request_id'     => $validated['visit_request_id'],
            'person_in_charge_id'  => $validated['person_in_charge_id'],
            'notification_email'   => $validated['notification_email'] ?? null,
            'activity'             => $validated['activity'],
            'date'                 => $validated['date'],
            'start_time'           => $validated['start_time'],
            'end_time'             => $validated['end_time'],
            'environment_id'       => $validated['environment_id'] ?? null,
            'observations'         => $validated['observations'] ?? null,
        ]);

        // 2) Actualizar la solicitud
        $visitRequest = VisitRequest::findOrFail($validated['visit_request_id']);
        $visitRequest->state = 'Agendada';
        $visitRequest->save();

        // 2.1) Generar PDF de autorización y programar recordatorio a portería
        $authPath = $this->buildAuthorizationPdf($schedule);          // guarda y setea authorization_path

        $this->scheduleSecurityReminder($schedule, $authPath);

        // 3) Enlace público para ver la visita
        $publicUrl = $this->buildPublicLink($schedule);

        // 4) Destinatarios: visitante + encargado
        $recipients = $this->allVisitRecipients($schedule);
        if (empty($recipients)) {
            return redirect()
                ->route('sigac.academic_coordination.visitrequest.index')
                ->with('warning', 'Visita agendada, pero no hay correos válidos para notificar.');
        }

        try {
            foreach ($recipients as $email) {
                $email = strtolower(trim($email));

                $isVisitor = (
                    $visitRequest->contact_email &&
                    strtolower(trim($visitRequest->contact_email)) === $email
                );

                Mail::to($email)->send(
                    new VisitScheduledMail($visitRequest, $schedule, $publicUrl, $isVisitor)
                );
            }

            return redirect()
                ->route('sigac.academic_coordination.visitschedule.calendar.general')
                ->with('success', 'Visita agendada y correos enviados.');
        } catch (\Throwable $e) {
            Log::error('Error enviando correo (store): ' . $e->getMessage(), [
                'recipients'  => $recipients,
                'schedule_id' => $schedule->id,
            ]);

            return redirect()
                ->route('sigac.academic_coordination.visitschedule.calendar.general')
                ->with('error', 'Visita agendada, pero los correos no se enviaron. Detalle: ' . $e->getMessage());
        }
    }



    /**
     * Ambientes disponibles según fecha y rango horario.
     */
    public function available_environments(Request $request)
    {
        $date       = $request->input('date');
        $start_time = $request->input('start_time');
        $end_time   = $request->input('end_time');

        $programIds = InstructorProgram::where('date', $date)->pluck('id');

        $occupiedByClasses = EnvironmentInstructorProgram::whereIn('instructor_program_id', $programIds)
            ->pluck('environment_id');

        $occupiedByVisits = VisitSchedule::where('date', $date)
            ->where(function ($query) use ($start_time, $end_time) {
                $query->whereBetween('start_time', [$start_time, $end_time])
                    ->orWhereBetween('end_time', [$start_time, $end_time])
                    ->orWhere(function ($q) use ($start_time, $end_time) {
                        $q->where('start_time', '<=', $start_time)
                            ->where('end_time', '>=', $end_time);
                    });
            })
            ->pluck('environment_id');

        $externalIds = ClassEnvironment::where('name', 'Externo')->pluck('id');

        $occupied  = $occupiedByClasses->merge($occupiedByVisits)->unique();
        $available = Environment::whereNotIn('id', $occupied)
            ->whereNotIn('class_environment_id', $externalIds)
            ->get();

        return response()->json(
            $available->map(fn($env) => ['id' => $env->id, 'name' => $env->name])
        );
    }

    /**
     * Calendario por solicitud.
     */
    public function calendar(VisitRequest $request)
    {
        $schedules = VisitSchedule::with('environment')
            ->where('visit_request_id', $request->id)
            ->orderBy('date')
            ->get();

        $initialDate = $schedules->first()->date ?? ($request->date_received ?? now()->toDateString());

        return view('sigac::visitschedule.calendar', [
            'visitRequest' => $request,
            'schedules'    => $schedules,
            'initialDate'  => $initialDate,
            'titlePage'    => 'Agenda de la solicitud',
            'titleView'    => 'Agenda de la solicitud',
        ]);
    }

    /**
     * Eventos JSON para FullCalendar por solicitud.
     */
    public function eventsByRequest(VisitRequest $request)
    {
        return response()->json(
            VisitSchedule::with('environment')
                ->where('visit_request_id', $request->id)
                ->get()
                ->map(function ($v) {
                    $rt = $this->runtimeStateForSchedule($v);
                    return [
                        'id'    => 'visit-' . $v->id,
                        'title' => $v->activity ?: 'Visita',
                        'start' => $v->date . 'T' . $v->start_time,
                        'end'   => $v->date . 'T' . $v->end_time,
                        'color' => '#5b9bd5',
                        'extendedProps' => [
                            'activity'         => $v->activity,
                            'environment_name' => $v->environment?->name,
                            'runtime_state'    => $rt['state'],
                            'runtime_color'    => $rt['color'],
                        ],
                    ];
                })
        );
    }

    public function calendarAll()
    {
        $initialDate = now('America/Bogota')->toDateString();

        return view('sigac::visitschedule.calendar_all', [
            'initialDate' => $initialDate,
            'titlePage'   => 'Calendario general de visitas',
            'titleView'   => 'Calendario general de visitas',
        ]);
    }

    /**
     * Feed de eventos para FullCalendar (todas las visitas).
     */
    public function eventsAll(Request $request)
    {
        $from          = trim((string) $request->query('from', ''));
        $to            = trim((string) $request->query('to', ''));
        $environmentId = $request->query('environment_id');
        $companyLike   = trim((string) $request->query('company', ''));

        $q = VisitSchedule::query()
            ->with([
                'environment:id,name',
                'visitRequest.company:id,name',
            ]);

        if ($from !== '') {
            $q->whereDate('date', '>=', $from);
        }
        if ($to !== '') {
            $q->whereDate('date', '<=', $to);
        }
        if (!empty($environmentId)) {
            $q->where('environment_id', (int) $environmentId);
        }
        if ($companyLike !== '') {
            $needle = '%' . str_replace(' ', '%', $companyLike) . '%';
            $q->whereHas('visitRequest.company', function ($w) use ($needle) {
                $w->where('name', 'like', $needle);
            });
        }

        $q->orderBy('date')->orderBy('start_time');

        $events = $q->get()->map(function ($v) {
            $envName = $v->environment?->name ?? 'Ambiente';
            $title   = trim(($v->activity ?: 'Visita') . ' — ' . $envName);
            $company = $v->visitRequest?->company?->name ?? 'Empresa';

            $rt = $this->runtimeStateForSchedule($v);

            return [
                'id'    => 'visit-' . $v->id,
                'title' => $title,
                'start' => $v->date . 'T' . $v->start_time,
                'end'   => $v->date . 'T' . $v->end_time,
                'color' => '#5b9bd5',
                'extendedProps' => [
                    'activity'          => $v->activity,
                    'environment_name'  => $envName,
                    'company'           => $company,
                    'request_id'        => $v->visit_request_id,
                    'observations'      => $v->observations,
                    'date'              => $v->date,
                    'start_time'        => $v->start_time,
                    'end_time'          => $v->end_time,
                    'runtime_state'     => $rt['state'],
                    'runtime_color'     => $rt['color'],
                ],
            ];
        });

        return response()->json($events);
    }

    /**
     * Actualizar un agendamiento (reprogramación / cambios).
     */
    public function update(Request $request, VisitSchedule $schedule)
    {
        $minDate = Carbon::today('America/Bogota')->toDateString();

        // Normalizar horas en caso de que lleguen con segundos H:i:s
        foreach (['start_time', 'end_time'] as $f) {
            $v = (string) $request->input($f, '');
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $v)) {
                $request->merge([$f => substr($v, 0, 5)]); // "08:15:00" -> "08:15"
            }
        }

        $validated = $request->validate([
            'date'                => ['sometimes', 'required', 'date', 'after_or_equal:' . $minDate],
            'start_time'          => ['sometimes', 'required', 'date_format:H:i'],
            'end_time'            => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'environment_id'      => ['sometimes', 'nullable', 'exists:environments,id'],
            'person_in_charge_id' => ['sometimes', 'nullable', 'exists:people,id'],
            'notification_email'  => ['sometimes', 'nullable', 'email'],
            'activity'            => ['sometimes', 'nullable', 'string'],
            'observations'        => ['sometimes', 'nullable', 'string'],
            'change_assignee'     => ['sometimes'],
        ], [
            'date.after_or_equal' => "La fecha debe ser igual o posterior a $minDate.",
            'end_time.after'      => 'La hora de fin debe ser mayor que la hora de inicio.',
        ]);

        // Copia del estado anterior para comparar cambios
        $before = $schedule->replicate(['id', 'created_at', 'updated_at']);
        $before->setRelation('environment',    $schedule->environment);
        $before->setRelation('personInCharge', $schedule->personInCharge);
        $before->setRelation('visitRequest',   $schedule->visitRequest);

        // Payload a actualizar
        $payload        = $validated;
        $changeAssignee = $request->boolean('change_assignee', false);

        // Si NO se marcó "cambiar encargado", no tocamos esos campos
        if (!$changeAssignee) {
            unset($payload['person_in_charge_id'], $payload['notification_email']);
        }

        // Guardar cambios
        $schedule->fill($payload);
        $schedule->save();

        // Refrescar relaciones y asegurar estado de la solicitud
        $schedule->load(['environment', 'personInCharge', 'visitRequest']);
        $schedule->visitRequest?->update(['state' => 'Agendada']);

        // Detectar qué cambió
        $changes = $this->changedFields($before, $schedule);
        if (empty($changes)) {
            return back()->with('info', 'No hubo cambios en la visita.');
        }

        // 👉 Si cambió fecha/hora/ambiente, regenerar PDF y reprogramar recordatorio a portería
        if (!empty($changes['_schedule_changed'])) {
            // Esto REGENERA y SOBREESCRIBE el PDF con los datos nuevos
            $authPath = $this->buildAuthorizationPdf($schedule);

            // Reprograma el Job para 1 día antes usando el PDF actualizado
            $this->scheduleSecurityReminder($schedule, $authPath);
        }

        $event        = (!empty($changes['_schedule_changed'])) ? 'rescheduled' : 'updated';
        $summaryLines = $this->humanizeChanges($changes);

        // Destinatarios (visitante + encargado + antiguo encargado si cambió)
        $recipients = $this->recipientsForUpdate($before, $schedule);
        if (empty($recipients)) {
            return back()->with('warning', 'La visita se actualizó, pero no hay destinatarios con correo válido para notificar.');
        }

        $publicUrl = $this->buildPublicLink($schedule);

        try {
            foreach ($recipients as $to) {
                $toNorm      = strtolower(trim($to));
                $contactNorm = strtolower(trim((string) $schedule->visitRequest->contact_email));

                $isVisitor = ($contactNorm !== '' && $toNorm === $contactNorm);

                Mail::to($toNorm)->send(new VisitUpdateMail(
                    $schedule->visitRequest,
                    $schedule,
                    $changes,
                    $event,
                    $summaryLines,
                    $publicUrl,
                    $isVisitor
                ));
            }

            return back()->with('success', 'Visita actualizada y notificaciones enviadas.');
        } catch (\Throwable $e) {
            Log::error('Error enviando notificaciones de actualización: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id,
                'recipients'  => $recipients,
            ]);

            return back()->with(
                'error',
                'Visita actualizada, pero falló el envío de correos. Detalle: ' . $e->getMessage()
            );
        }
    }



    /**
     * Cancela la visita y notifica.
     */
    public function cancel(VisitSchedule $schedule, Request $request)
    {
        $before = $schedule->replicate(['id', 'created_at', 'updated_at']);
        $before->setRelation('personInCharge', $schedule->personInCharge);
        $before->setRelation('visitRequest',   $schedule->visitRequest);

        $schedule->visitRequest?->update(['state' => 'Cancelada']);

        $reason = trim((string) $request->input('reason', ''));
        if ($reason !== '') {
            $schedule->observations = trim(
                ($schedule->observations ? $schedule->observations . "\n" : '') .
                    'Cancelada: ' . $reason
            );
        }
        $schedule->save();

        $schedule->load(['personInCharge', 'visitRequest', 'environment']);

        $recipients = $this->recipientsForUpdate($before, $schedule);
        if (empty($recipients)) {
            return back()->with(
                'warning',
                'La visita fue cancelada, pero no hay destinatarios con correo válido para notificar.'
            );
        }

        $summaryLines = [
            'La visita fue <strong>cancelada</strong>' . ($reason ? " (motivo: {$reason})" : '') . '.',
        ];

        $publicUrl = $this->buildPublicLink($schedule);

        try {
            foreach ($recipients as $to) {
                $toNorm      = strtolower(trim($to));
                $contactNorm = strtolower(trim((string) $schedule->visitRequest->contact_email));

                $isVisitor = ($contactNorm !== '' && $toNorm === $contactNorm);

                Mail::to($toNorm)->send(new VisitUpdateMail(
                    $schedule->visitRequest,
                    $schedule,
                    ['canceled' => true],
                    'canceled',
                    $summaryLines,
                    $publicUrl,
                    $isVisitor
                ));
            }

            return back()->with('success', 'Visita cancelada y notificaciones enviadas.');
        } catch (\Throwable $e) {
            Log::error('Error enviando notificaciones de cancelación: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id,
                'recipients'  => $recipients,
            ]);

            return back()->with(
                'error',
                'La visita fue cancelada, pero falló el envío de correos. Detalle: ' . $e->getMessage()
            );
        }
    }
    private function normalizeEmail(?string $mail): ?string
    {
        $mail = trim((string) $mail);
        if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return strtolower($mail);
    }

    private function bestEmailFromPerson($person): ?string
    {
        if (!$person) {
            return null;
        }

        foreach (['sena_email', 'misena_email', 'personal_email'] as $field) {
            $value = trim((string) ($person->$field ?? ''));
            if ($value && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }

        return null;
    }


    public function personEmails(Person $person)
    {
        $candidates = [
            'sena_email'     => trim((string) ($person->sena_email ?? '')),
            'misena_email'   => trim((string) ($person->misena_email ?? '')),
            'personal_email' => trim((string) ($person->personal_email ?? '')),
        ];

        $emails = [];
        foreach ($candidates as $label => $value) {
            if ($value && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emails[] = ['label' => $label, 'email' => $value];
            }
        }

        return response()->json($emails);
    }

    /**
     * Devuelve mapa de cambios con before/after y flags para fácil lectura.
     */
    private function changedFields(VisitSchedule $before, VisitSchedule $after): array
    {
        $ch = [];

        if ($before->date !== $after->date) {
            $ch['date'] = [
                'before' => $before->date,
                'after'  => $after->date,
            ];
        }
        if ($before->start_time !== $after->start_time) {
            $ch['start_time'] = [
                'before' => $before->start_time,
                'after'  => $after->start_time,
            ];
        }
        if ($before->end_time !== $after->end_time) {
            $ch['end_time'] = [
                'before' => $before->end_time,
                'after'  => $after->end_time,
            ];
        }
        if ((int) $before->environment_id !== (int) $after->environment_id) {
            $ch['environment'] = [
                'before' => optional($before->environment)->name ?? '—',
                'after'  => optional($after->environment)->name ?? '—',
            ];
        }
        if ((int) $before->person_in_charge_id !== (int) $after->person_in_charge_id) {
            $ch['assignee'] = [
                'before_id' => $before->person_in_charge_id,
                'after_id'  => $after->person_in_charge_id,
                'before'    => optional($before->personInCharge)->first_name
                    ? trim($before->personInCharge->first_name . ' ' . $before->personInCharge->first_last_name) : '—',
                'after'     => optional($after->personInCharge)->first_name
                    ? trim($after->personInCharge->first_name . ' ' . $after->personInCharge->first_last_name) : '—',
            ];
        }
        if (trim((string) $before->notification_email) !== trim((string) $after->notification_email)) {
            $ch['notification_email'] = [
                'before' => $before->notification_email ?: '—',
                'after'  => $after->notification_email ?: '—',
            ];
        }
        if (trim((string) $before->activity) !== trim((string) $after->activity)) {
            $ch['activity'] = [
                'before' => $before->activity ?: '—',
                'after'  => $after->activity ?: '—',
            ];
        }
        if (trim((string) $before->observations) !== trim((string) $after->observations)) {
            $ch['observations'] = [
                'before' => $before->observations ?: '—',
                'after'  => $after->observations ?: '—',
            ];
        }

        $ch['_schedule_changed'] =
            isset($ch['date']) || isset($ch['start_time']) || isset($ch['end_time']) || isset($ch['environment']);

        return $ch;
    }

    /**
     * @return string[] correos únicos
     */
    private function recipientsForUpdate(VisitSchedule $before, VisitSchedule $after): array
    {
        $recipients = [];

        // 1) Visitante
        $visit = $after->visitRequest;
        if ($visit) {
            if ($mail = $this->normalizeEmail($visit->contact_email ?? null)) {
                $recipients[] = $mail;
            }
        }

        // 2) Nuevo encargado
        $newAssigneeMail = $after->notification_email
            ?: $this->bestEmailFromPerson($after->personInCharge ?? null);

        $newAssigneeMailNorm = $this->normalizeEmail($newAssigneeMail);
        if ($newAssigneeMailNorm) {
            $recipients[] = $newAssigneeMailNorm;
        }

        // 3) Encargado anterior (solo si cambió)
        $oldAssigneeMailNorm = null;

        if (
            $before->personInCharge && $after->personInCharge
            && $before->personInCharge->id !== $after->personInCharge->id
        ) {
            $oldAssigneeMail = $before->notification_email
                ?: $this->bestEmailFromPerson($before->personInCharge ?? null);

            $oldAssigneeMailNorm = $this->normalizeEmail($oldAssigneeMail);

            if ($oldAssigneeMailNorm && $oldAssigneeMailNorm !== $newAssigneeMailNorm) {
                $recipients[] = $oldAssigneeMailNorm;
            }
        }

        // 4) Únicos
        return array_values(array_unique($recipients));
    }


    private function humanizeChanges(array $ch): array
    {
        $lines = [];
        if (isset($ch['date']))               $lines[] = "Se cambió el <strong>día</strong>: {$ch['date']['before']} → {$ch['date']['after']}";
        if (isset($ch['start_time']))         $lines[] = "Se cambió la <strong>hora de inicio</strong>: {$ch['start_time']['before']} → {$ch['start_time']['after']}";
        if (isset($ch['end_time']))           $lines[] = "Se cambió la <strong>hora de fin</strong>: {$ch['end_time']['before']} → {$ch['end_time']['after']}";
        if (isset($ch['environment']))        $lines[] = "Se cambió el <strong>ambiente</strong>: {$ch['environment']['before']} → {$ch['environment']['after']}";
        if (isset($ch['assignee']))           $lines[] = "Se cambió el <strong>encargado</strong>: {$ch['assignee']['before']} → {$ch['assignee']['after']}";
        if (isset($ch['notification_email'])) $lines[] = "Se cambió el <strong>correo de notificación</strong>: {$ch['notification_email']['before']} → {$ch['notification_email']['after']}";
        if (isset($ch['activity']))           $lines[] = "Se cambió la <strong>actividad</strong>: " . ($ch['activity']['before'] ?: '—') . " → " . ($ch['activity']['after'] ?: '—');
        if (isset($ch['observations']))       $lines[] = "Se actualizaron las <strong>observaciones</strong>.";
        return $lines;
    }

    public function invitation(VisitSchedule $schedule)
    {
        $schedule->load(['visitRequest.company', 'environment', 'personInCharge']);
        return view('sigac::visits.invitation', [
            'schedule'  => $schedule,
            'visit'     => $schedule->visitRequest,
            'titlePage' => 'Invitación a visita',
            'titleView' => 'Invitación a visita',
        ]);
    }



    /**
     * Vista pública (enlace firmado).
     */
    public function publicView($scheduleId)
    {
        Log::info('publicView hit', ['scheduleId' => $scheduleId]);

        $schedule = VisitSchedule::with(['visitRequest.company', 'environment', 'personInCharge'])
            ->findOrFail($scheduleId);

        if ($schedule->date && now('America/Bogota')->gt(Carbon::parse($schedule->date, 'America/Bogota')->endOfDay())) {
            abort(403, 'Este enlace ya no está disponible.');
        }

        return view('sigac::visitschedule.invitation', [
            'schedule'  => $schedule,
            'visit'     => $schedule->visitRequest,
            'titlePage' => 'Detalle de visita',
            'titleView' => 'Detalle de visita',
        ]);
    }

    private function buildPublicLink(VisitSchedule $schedule): string
    {
        $expiresAt = $schedule->date
            ? Carbon::parse($schedule->date, 'America/Bogota')->endOfDay()
            : now('America/Bogota')->addDays(3);

        return URL::temporarySignedRoute('cefa.sigac.visit.public', $expiresAt, [
            'schedule' => $schedule->id,
        ]);
    }

    private function runtimeStateForVisit(VisitRequest $visit): array
    {
        if (strcasecmp((string) $visit->state, 'Cancelada') === 0) {
            return ['state' => 'Cancelada', 'color' => 'danger'];
        }

        $last = VisitSchedule::where('visit_request_id', $visit->id)
            ->orderByDesc('date')
            ->orderByDesc('start_time')
            ->first();

        if (!$last) {
            return ['state' => 'Sin agendar', 'color' => 'secondary'];
        }

        return $this->runtimeStateForSchedule($last);
    }

    private function runtimeStateForSchedule(VisitSchedule $s): array
    {
        $tz  = 'America/Bogota';
        $now = Carbon::now($tz);

        $dateOnly = Carbon::parse($s->date, $tz)->toDateString();

        $day = Carbon::parse($dateOnly, $tz);
        $ini = Carbon::parse($dateOnly . ' ' . $s->start_time, $tz);
        $fin = Carbon::parse($dateOnly . ' ' . $s->end_time, $tz);

        if ($now->lt($day->copy()->startOfDay())) {
            return ['state' => 'Agendada', 'color' => 'primary'];
        }

        if ($now->isSameDay($day)) {
            if ($now->lt($ini)) {
                return ['state' => 'Hoy', 'color' => 'info'];
            }

            // 👇 aquí el cambio importante
            if ($now->between($ini, $fin, true)) {
                return ['state' => 'En curso', 'color' => 'warning'];
            }

            if ($now->gt($fin)) {
                return ['state' => 'Finalizada', 'color' => 'secondary'];
            }
        }

        if ($now->gt($day->copy()->endOfDay())) {
            return ['state' => 'Finalizada', 'color' => 'secondary'];
        }

        return ['state' => 'Agendada', 'color' => 'primary'];
    }



    /**
     * Resolver ruta del archivo de listado de visitantes.
     */
    private function resolvePeopleList(VisitRequest $visit): array
    {
        $raw = trim((string) ($visit->people_list_path ?? ''));
        if ($raw === '') {
            return [null, null, null];
        }

        $rel = str_replace('\\', '/', $raw);
        if (Str::startsWith($rel, ['storage/app/', '/storage/app/'])) {
            $rel = Str::after($rel, 'storage/app/');
        }

        $disk = Storage::disk('public');
        if ($disk->exists($rel)) {
            $full = $disk->path($rel);
            $mime = mime_content_type($full) ?: 'application/octet-stream';
            $url  = $disk->url($rel);
            return [$full, $mime, $url];
        }

        if (Storage::disk('local')->exists($rel)) {
            $full = storage_path('app/' . $rel);
            $mime = mime_content_type($full) ?: 'application/octet-stream';
            return [$full, $mime, null];
        }

        if (is_file($rel)) {
            $mime = mime_content_type($rel) ?: 'application/octet-stream';
            return [$rel, $mime, null];
        }

        return [null, null, null];
    }

    private function peopleListPublicUrl(?VisitRequest $visit): ?string
    {
        if (!$visit) return null;
        $raw = trim((string) ($visit->people_list_path ?? ''));
        if ($raw === '') return null;

        $rel = str_replace('\\', '/', $raw);
        if (Str::startsWith($rel, ['storage/app/', '/storage/app/'])) {
            $rel = Str::after($rel, 'storage/app/');
        }

        $disk = Storage::disk('public');
        return $disk->exists($rel) ? $disk->url($rel) : null;
    }

    public function previewPeopleListHtml(VisitRequest $visit)
    {
        [$fullPath, $mime] = $this->resolvePeopleList($visit);

        if (!$fullPath || !is_file($fullPath)) {
            return back()->with('error', 'No se encontró el archivo asociado a esta solicitud.');
        }

        $ext = Str::lower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if ($ext === 'csv') {
            $rows = [];
            if (($h = fopen($fullPath, 'r')) !== false) {
                while (($data = fgetcsv($h, 0, ',')) !== false) {
                    $rows[] = $data;
                }
                fclose($h);
            }

            return response()->view('sigac::visitschedule.preview_csv', [
                'rows'      => $rows,
                'filename'  => basename($fullPath),
                'titlePage' => 'Vista previa del listado',
                'titleView' => 'Vista previa del listado',
            ]);
        }

        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = IOFactory::load($fullPath);
            $writer      = IOFactory::createWriter($spreadsheet, 'Html');

            if (method_exists($writer, 'setPreCalculateFormulas')) {
                $writer->setPreCalculateFormulas(false);
            }

            ob_start();
            $writer->save('php://output');
            $html = ob_get_clean();

            return response()->view('sigac::visitschedule.preview_html', [
                'html'      => $html,
                'filename'  => basename($fullPath),
                'titlePage' => 'Vista previa del listado',
                'titleView' => 'Vista previa del listado',
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type'        => $mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"',
        ]);
    }

    /* ===================== SEGURIDAD / PORTERÍA ===================== */

    /**
     * Vista para Seguridad/Portería: visitas del día actual.
     */
    public function securityToday(Request $request)
    {
        $today = Carbon::today('America/Bogota')->toDateString();

        $schedules = VisitSchedule::with([
            'visitRequest.company',
            'environment',
            'personInCharge',
        ])
            ->whereDate('date', $today)
            ->orderBy('start_time')
            ->get();

        return view('sigac::visitschedule.security_today', [
            'schedules'  => $schedules,
            'today'      => $today,
            'titlePage'  => 'Control de visitas de hoy',
            'titleView'  => 'Control de visitas de hoy',
        ]);
    }

    /**
     * Seguridad marca el ingreso (Check-In).
     */
    public function securityCheckIn(VisitSchedule $schedule)
    {
        if ($schedule->check_in_at) {
            return back()->with('info', 'Esta visita ya tiene registrado el ingreso.');
        }

        $now = Carbon::now('America/Bogota');

        $schedule->check_in_at      = $now;
        $schedule->security_user_id = auth()->id();

        if (strcasecmp((string)$schedule->status, 'Cancelada') !== 0) {
            $schedule->status = 'En curso';
        }

        $schedule->save();

        return back()->with('success', 'Ingreso registrado correctamente.');
    }

    /**
     * Seguridad marca la salida (Check-Out).
     */
    public function securityCheckOut(VisitSchedule $schedule)
    {
        if ($schedule->check_out_at) {
            return back()->with('info', 'Esta visita ya tiene registrada la salida.');
        }

        $now = Carbon::now('America/Bogota');

        $schedule->check_out_at     = $now;
        $schedule->security_user_id = auth()->id();

        if (strcasecmp((string)$schedule->status, 'Cancelada') !== 0) {
            $schedule->status = 'Finalizada';
        }

        $schedule->save();

        return back()->with('success', 'Salida registrada correctamente.');
    }

    /**
     * Correos de visitante + encargado.
     */
    private function allVisitRecipients(VisitSchedule $schedule): array
    {
        $visit = $schedule->visitRequest;

        $emails = [];

        if ($visit && filter_var($visit->contact_email, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower(trim($visit->contact_email));
        }

        $assignee = $schedule->notification_email ?: $this->bestEmailFromPerson($schedule->personInCharge);
        if ($assignee && filter_var($assignee, FILTER_VALIDATE_EMAIL)) {
            $emails[] = strtolower(trim($assignee));
        }

        return array_values(array_unique($emails));
    }

    /**
     * Correos configurados para Seguridad.
     */
    private function securityEmails(): array
    {
        $list = config('sigac.security_emails', []);

        return array_values(array_filter($list, function ($mail) {
            return filter_var($mail, FILTER_VALIDATE_EMAIL);
        }));
    }


    /**
     * Construye el PDF de autorización y guarda la ruta en el schedule.
     */
    private function buildAuthorizationPdf(VisitSchedule $schedule): ?string
    {
        // Cargar relaciones necesarias
        $schedule->load(['visitRequest.company', 'environment', 'personInCharge']);

        $visit = $schedule->visitRequest;

        // 1) Renderizar PDF desde la vista
        $pdf = Pdf::loadView('sigac::visitschedule.authorization_pdf', [
            'schedule' => $schedule,
            'visit'    => $visit,
        ]);

        // 2) Carpeta donde se guardan las autorizaciones
        $dir = 'sigac/visit_authorizations';
        Storage::disk('public')->makeDirectory($dir);

        // 3) Nombre ESTABLE del archivo
        //    - usa id de la solicitud o, en su defecto, el id del schedule
        //    - usa un slug de la empresa para que sea legible
        $companyName = $visit?->company?->name ?? 'empresa';
        $companySlug = Str::slug($companyName, '_');      // "Compañía XYZ" -> "compania_xyz"
        $baseId      = $visit?->id ?? $schedule->id;      // por si acaso

        $name = 'autorizacion_visita_' . $baseId . '_' . $companySlug . '.pdf';
        $path = $dir . '/' . $name;

        // 4) Guardar (sobrescribe si ya existía)
        Storage::disk('public')->put($path, $pdf->output());

        // 5) Actualizar el schedule con la ruta
        $schedule->authorization_path = $path;
        $schedule->save();

        return $path;
    }





    /**
     * Reenvía notificación de agendamiento (visitante + encargado).
     */
    public function notify(Request $request, VisitRequest $visit)
    {
        // 1) Buscar agenda asociada
        $schedule = VisitSchedule::where('visit_request_id', $visit->id)
            ->with(['personInCharge', 'visitRequest.company', 'environment'])
            ->latest('id')
            ->first();

        if (!$schedule) {
            return back()->with('error', 'No hay una agenda asociada a esta solicitud.');
        }

        // 2) Targets (a quién reenviar)
        // Esperado: targets[] = visitor | assignee | security
        // Fallback: si no llega nada, reenviar a los destinatarios "por defecto" (visitante + encargado)
        $targets = (array) $request->input('targets', []);

        if (empty($targets)) {
            // Comportamiento retrocompatible (como hoy)
            $recipients = $this->allVisitRecipients($schedule);
            $targets = ['visitor', 'assignee']; // para logging/consistencia
        } else {
            $recipients = $this->recipientsByTargets($schedule, $targets);
        }

        if (empty($recipients)) {
            return back()->with('error', 'No hay correos válidos para reenviar la notificación.');
        }

        // 3) URL pública
        $publicUrl = $this->buildPublicLink($schedule);

        // 4) Generar ICS (calendario)
        $tz = 'America/Bogota';
        $dateOnly = Carbon::parse($schedule->date, $tz)->toDateString();

        $summaryCompany = optional($schedule->visitRequest->company)->name ?? 'SENA';
        $ics = IcsBuilder::singleEvent([
            'uid'         => "visit-{$schedule->id}@sicefa.local",
            'summary'     => 'Visita programada - ' . $summaryCompany,
            'description' => "Actividad: {$schedule->activity}",
            'location'    => optional($schedule->environment)->name ?? 'SENA',
            'start'       => "{$dateOnly} {$schedule->start_time}",
            'end'         => "{$dateOnly} {$schedule->end_time}",
            'organizer'   => config('mail.from.address'),
            'attendees'   => $recipients,
        ]);

        try {
            foreach ($recipients as $email) {
                $email = strtolower(trim($email));

                $isVisitor = (
                    $visit->contact_email &&
                    strtolower(trim($visit->contact_email)) === $email
                );

                Mail::to($email)->send(
                    (new VisitScheduledMail($visit, $schedule, $publicUrl, $isVisitor))
                        ->attachData($ics, "visita-{$schedule->id}.ics", ['mime' => 'text/calendar'])
                );
            }

            return back()->with(
                'success',
                'Notificación reenviada correctamente (' . implode(', ', $targets) . ').'
            );
        } catch (\Throwable $e) {
            Log::error('Error enviando correo (notify): ' . $e->getMessage(), [
                'visit_request_id' => $visit->id,
                'schedule_id'      => $schedule->id,
                'targets'          => $targets,
                'recipients'       => $recipients,
            ]);

            return back()->with('error', 'No se pudo enviar el correo. Detalle: ' . $e->getMessage());
        }
    }


    public function sendSecurityAuthorization(VisitSchedule $schedule)
    {
        $schedule->load(['visitRequest.company', 'environment', 'personInCharge']);

        // 1) Correos de seguridad configurados en config/sigac.php
        $securityRecipients = $this->securityEmails();

        if (empty($securityRecipients)) {
            return back()->with(
                'warning',
                'No hay correos configurados para portería/seguridad (config sigac.security_emails).'
            );
        }

        // 2) Generar o reutilizar el PDF de autorización
        $authPath = $schedule->authorization_path;
        if (!$authPath || !Storage::disk('public')->exists($authPath)) {
            $authPath = $this->buildAuthorizationPdf($schedule);
        }

        if (!$authPath || !Storage::disk('public')->exists($authPath)) {
            return back()->with(
                'error',
                'No se pudo generar el PDF de autorización de la visita.'
            );
        }

        $fullPath = Storage::disk('public')->path($authPath);

        try {
            foreach ($securityRecipients as $email) {
                Mail::to($email)->send(
                    (new SecurityVisitAuthorizationMail($schedule))
                        ->attach($fullPath, [
                            'as'   => 'autorizacion_visita_' . $schedule->id . '.pdf',
                            'mime' => 'application/pdf',
                        ])
                );
            }

            return back()->with(
                'success',
                'Autorización enviada a portería/seguridad correctamente.'
            );
        } catch (\Throwable $e) {
            Log::error('Error enviando autorización a seguridad: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id,
                'recipients'  => $securityRecipients,
            ]);

            return back()->with(
                'error',
                'La visita sigue agendada, pero falló el envío a portería. Detalle: ' . $e->getMessage()
            );
        }
    }
    private function scheduleSecurityReminder(VisitSchedule $schedule, ?string $authPath = null): void
    {
        $tz = 'America/Bogota';

        $dateOnly = \Carbon\Carbon::parse($schedule->date, $tz)->toDateString();

        // Ejemplo: 1 día antes, MISMA HORA
        $visitDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $dateOnly . ' ' . $schedule->start_time, $tz);

        $reminderAt = $visitDateTime->copy()->subDay(); // lunes 8:15 si la visita es martes 8:15

        if ($reminderAt->lt(\Carbon\Carbon::now($tz))) {
            $reminderAt = \Carbon\Carbon::now($tz)->addMinutes(5);
        }

        \App\Jobs\SIGAC\VISITAS\SendSecurityVisitReminder::dispatch($schedule->id, $authPath)
            ->delay($reminderAt);
    }


    public function authorizationPdf(VisitSchedule $schedule)
    {
        // Cargar relaciones necesarias
        $schedule->load(['visitRequest.company', 'environment', 'personInCharge']);

        // SIEMPRE regenerar el PDF con la info ACTUAL
        $path = $this->buildAuthorizationPdf($schedule);

        // Lo devolvemos inline en el navegador (no descarga directa)
        return Storage::disk('public')->response($path, basename($path), [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }


    public function securityAuthorize(VisitSchedule $schedule, Request $request)
    {
        $force = $request->boolean('force', false); // por si luego quieres permitir reenvío forzado

        // Si ya se envió y no estamos forzando, no enviar de nuevo
        if ($schedule->security_authorized_at && !$force) {
            return back()->with(
                'info',
                'Ya se envió la autorización a portería el ' .
                    $schedule->security_authorized_at->format('d/m/Y H:i') .
                    '.'
            );
        }

        // Asegúrate de tener cargadas relaciones para el PDF y correo
        $schedule->load(['visitRequest.company', 'environment', 'personInCharge']);

        // 1) Construir/generar PDF (si no existe aún)
        $authPath = $schedule->authorization_path;
        if (!$authPath) {
            $authPath = $this->buildAuthorizationPdf($schedule); // ya lo tienes en tu controlador
        }

        // 2) Correos de seguridad (desde config sigac.security_emails)
        $securityEmails = $this->securityEmails();
        if (empty($securityEmails)) {
            return back()->with('warning', 'No hay correos configurados para portería/seguridad.');
        }

        try {
            foreach ($securityEmails as $email) {
                Mail::to($email)->send(
                    new \App\Mail\SIGAC\VISITAS\SecurityVisitAuthorizationMail($schedule, $authPath)
                );
            }

            // 3) Marcar en BD que YA se envió
            $schedule->security_authorized_at      = now('America/Bogota');
            $schedule->security_authorized_by      = auth()->id();
            $schedule->security_authorization_source = 'manual';
            $schedule->save();

            return back()->with('success', 'Autorización enviada a portería correctamente.');
        } catch (\Throwable $e) {
            Log::error('Error enviando autorización a portería: ' . $e->getMessage(), [
                'schedule_id' => $schedule->id,
            ]);

            return back()->with(
                'error',
                'No se pudo enviar la autorización a portería. Detalle: ' . $e->getMessage()
            );
        }
    }
    private function recipientsByTargets(VisitSchedule $schedule, array $targets): array
    {
        $emails = [];
        $visit  = $schedule->visitRequest;

        // visitor
        if (in_array('visitor', $targets, true)) {
            if (!empty($visit->contact_email) && filter_var($visit->contact_email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower(trim($visit->contact_email));
            }
        }

        // assignee (encargado)
        if (in_array('assignee', $targets, true)) {
            $assignee = $schedule->notification_email
                ?: $this->bestEmailFromPerson($schedule->personInCharge);

            if (!empty($assignee) && filter_var($assignee, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower(trim($assignee));
            }
        }

        // security / portería (si quieres soportarlo desde config)
        if (in_array('security', $targets, true)) {
            $securityEmail = config('sigac.security_email'); // define esto en config/sigac.php
            if (!empty($securityEmail) && filter_var($securityEmail, FILTER_VALIDATE_EMAIL)) {
                $emails[] = strtolower(trim($securityEmail));
            }
        }

        // unique
        $emails = array_values(array_unique($emails));

        return $emails;
    }
}
