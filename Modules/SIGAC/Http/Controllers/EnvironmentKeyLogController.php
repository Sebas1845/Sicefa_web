<?php

namespace Modules\SIGAC\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\SIGAC\Entities\EnvironmentKeyLog;
use Modules\SIGAC\Entities\EnvironmentRoundEntry;

class EnvironmentKeyLogController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        $titlePage = "Control de Llaves";
        $titleView = "Control de Llaves";

        // PROGRAMACIÓN DEL DÍA
        $schedules = DB::table('instructor_programs AS ip')
            ->join('courses AS c', 'c.id', '=', 'ip.course_id')
            ->leftJoin('programs AS pr', 'pr.id', '=', 'c.program_id')
            ->leftJoin('instructor_program_people AS ipp', 'ipp.instructor_program_id', '=', 'ip.id')
            ->leftJoin('people AS p', 'p.id', '=', 'ipp.person_id')
            ->leftJoin('environment_instructor_programs AS eip', 'eip.instructor_program_id', '=', 'ip.id')
            ->leftJoin('environments AS env', 'env.id', '=', 'eip.environment_id')
            ->whereDate('ip.date', $date)
            ->select([
                'ip.id           AS schedule_id',
                'ip.date',
                'ip.start_time',
                'ip.end_time',
                'c.code          AS ficha',
                'pr.name         AS programa',
                'env.id          AS environment_id',
                'env.name        AS ambiente',
                'p.first_name',
                'p.first_last_name',
            ])
            ->orderBy('env.name')
            ->orderBy('ip.start_time')
            ->get();

        $scheduledCoursesCount      = $schedules->pluck('ficha')->filter()->unique()->count();
        $scheduledEnvironmentsCount = $schedules->pluck('environment_id')->filter()->unique()->count();

        $rawKeyLogs = EnvironmentKeyLog::whereDate('taken_at', $date)->get();

        $keyStates = $rawKeyLogs
            ->groupBy('schedule_id')
            ->map(function ($logs) {
                // último movimiento por horario
                $last = $logs->sortByDesc('taken_at')->first();

                return [
                    'delivered_at' => $last?->taken_at,
                    'returned_at'  => $last?->returned_at,
                ];
            });

        $keysGivenToday  = $keyStates->filter(fn($s) => $s['delivered_at'])->count();
        $keysNotReturned = $keyStates->filter(fn($s) => $s['delivered_at'] && !$s['returned_at'])->count();

        // RONDAS (solo para stats)
        $roundEntries = EnvironmentRoundEntry::with('round')
            ->whereHas('round', function ($q) use ($date) {
                $q->whereDate('date', $date);
            })
            ->get();

        $totalEntries = $roundEntries->count();
        $entriesWithIssuesCount = $roundEntries->filter(function ($e) {
            return $e->is_dirty
                || $e->ac_status === 'DAÑADO'
                || (is_string($e->other_issues) && trim($e->other_issues) !== '');
        })->count();
        $attendanceOk     = $roundEntries->where('attendance_status', 'OK')->count();
        $attendanceIssues = $totalEntries - $attendanceOk;

        // LISTA DE AMBIENTES PARA EL MODAL
        $environments = DB::table('environments')->orderBy('name')->get(['id', 'name']);

        return view('sigac::keylogs.index', [
            'titlePage'                 => $titlePage,
            'titleView'                 => $titleView,
            'date'                      => $date,
            'schedules'                 => $schedules,
            'keyStates'                 => $keyStates,
            'scheduledCoursesCount'     => $scheduledCoursesCount,
            'scheduledEnvironmentsCount' => $scheduledEnvironmentsCount,
            'keysGivenToday'            => $keysGivenToday,
            'keysNotReturned'           => $keysNotReturned,
            'totalEntries'              => $totalEntries,
            'entriesWithIssuesCount'    => $entriesWithIssuesCount,
            'attendanceOk'              => $attendanceOk,
            'attendanceIssues'          => $attendanceIssues,
            'environments'              => $environments,
        ]);
    }

    public function deliverKey(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer',
        ]);

        $scheduleId = $request->schedule_id;

        // 1) Validar que el schedule exista
        $schedule = DB::table('instructor_programs')->where('id', $scheduleId)->first();
        if (!$schedule) {
            return response()->json([
                'ok'      => false,
                'message' => 'La programación seleccionada no existe.',
            ], 404);
        }

        // 2) Validar que tenga ambiente asignado
        $envLink = DB::table('environment_instructor_programs')
            ->where('instructor_program_id', $scheduleId)
            ->first();

        if (!$envLink) {
            return response()->json([
                'ok'      => false,
                'message' => 'Primero debe asignar un ambiente antes de entregar la llave.',
            ], 422);
        }

        // 3) Validar que no haya una llave "abierta" (returned_at = null)
        $openLog = EnvironmentKeyLog::where('schedule_id', $scheduleId)
            ->whereNull('returned_at')
            ->orderBy('taken_at', 'desc')
            ->first();

        if ($openLog) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ya hay una llave entregada para esta programación y no ha sido registrada como devuelta.',
            ], 409);
        }

        // 4) Registrar entrega (usando el esquema viejo: given_by, taken_at, returned_at)
        EnvironmentKeyLog::create([
            'schedule_id'    => $scheduleId,
            'environment_id' => $envLink->environment_id,
            'instructor_id'  => null, // si quieres puedes inferirlo
            'given_by'       => auth()->id(),
            'taken_at'       => now(),
            'returned_at'    => null,
        ]);

        return response()->json(['ok' => true]);
    }


    public function returnKey(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer',
        ]);

        $scheduleId = $request->schedule_id;

        // Buscar el último préstamo abierto de ese horario
        $openLog = EnvironmentKeyLog::where('schedule_id', $scheduleId)
            ->whereNull('returned_at')
            ->orderBy('taken_at', 'desc')
            ->first();

        if (!$openLog) {
            return response()->json([
                'ok'      => false,
                'message' => 'No hay una llave entregada pendiente por devolver.',
            ], 409);
        }

        $openLog->update([
            'returned_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }


    public function changeEnvironment(Request $request)
    {
        $request->validate([
            'schedule_id'    => 'required|integer',
            'environment_id' => 'nullable|integer',
        ]);

        $scheduleId    = $request->schedule_id;
        $environmentId = $request->environment_id;

        // Si lo dejan POR DEFINIR, solo borramos la asignación
        if (!$environmentId) {
            DB::table('environment_instructor_programs')
                ->where('instructor_program_id', $scheduleId)
                ->delete();

            return response()->json(['ok' => true]);
        }

        // Tomar la clase base
        $schedule = DB::table('instructor_programs')
            ->where('id', $scheduleId)
            ->first();

        if (!$schedule) {
            return response()->json([
                'ok'      => false,
                'message' => 'La programación seleccionada no existe.',
            ], 404);
        }

        // Validar que el ambiente NO esté ocupado en ese mismo horario
        $conflict = DB::table('instructor_programs AS ip')
            ->join('environment_instructor_programs AS eip', 'eip.instructor_program_id', '=', 'ip.id')
            ->where('eip.environment_id', $environmentId)
            ->where('ip.date', $schedule->date)
            ->where('ip.id', '<>', $scheduleId)
            ->where(function ($q) use ($schedule) {
                $q->whereBetween('ip.start_time', [$schedule->start_time, $schedule->end_time])
                    ->orWhereBetween('ip.end_time', [$schedule->start_time, $schedule->end_time])
                    ->orWhere(function ($q2) use ($schedule) {
                        $q2->where('ip.start_time', '<=', $schedule->start_time)
                            ->where('ip.end_time', '>=', $schedule->end_time);
                    });
            })
            ->exists();

        if ($conflict) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ese ambiente ya está ocupado en ese horario.',
            ], 409);
        }

        // Guardar/actualizar la relación
        DB::table('environment_instructor_programs')->updateOrInsert(
            ['instructor_program_id' => $scheduleId],
            ['environment_id'        => $environmentId]
        );

        return response()->json(['ok' => true]);
    }
}
