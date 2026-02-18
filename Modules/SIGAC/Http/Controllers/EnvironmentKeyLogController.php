<?php

namespace Modules\SIGAC\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\SIGAC\Entities\EnvironmentKeyLog;

class EnvironmentKeyLogController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());

        $titlePage = "Control de Llaves";
        $titleView = "Control de Llaves";

        // ✅ PROGRAMACIÓN DEL DÍA (sale de instructor_programs)
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
            ->orderByRaw('env.name IS NULL') // primero los que sí tienen ambiente
            ->orderBy('env.name')
            ->orderBy('ip.start_time')
            ->get();

        $scheduledCoursesCount      = $schedules->pluck('ficha')->filter()->unique()->count();
        $scheduledEnvironmentsCount = $schedules->pluck('environment_id')->filter()->unique()->count();

        // ✅ ESTADOS DE LLAVES (si usas la tabla environment_key_logs)
        $rawKeyLogs = EnvironmentKeyLog::whereDate('taken_at', $date)->get();

        $keyStates = $rawKeyLogs
            ->groupBy('schedule_id')
            ->map(function ($logs) {
                $last = $logs->sortByDesc('taken_at')->first();
                return [
                    'delivered_at' => $last?->taken_at,
                    'returned_at'  => $last?->returned_at,
                ];
            });

        $keysGivenToday  = $keyStates->filter(fn($s) => $s['delivered_at'])->count();
        $keysNotReturned = $keyStates->filter(fn($s) => $s['delivered_at'] && !$s['returned_at'])->count();

        // ✅ LISTA DE AMBIENTES PARA EL MODAL
        $environments = DB::table('environments')->orderBy('name')->get(['id', 'name']);

        return view('sigac::keylogs.index', [
            'titlePage'                  => $titlePage,
            'titleView'                  => $titleView,
            'date'                       => $date,
            'schedules'                  => $schedules,
            'keyStates'                  => $keyStates,
            'scheduledCoursesCount'      => $scheduledCoursesCount,
            'scheduledEnvironmentsCount' => $scheduledEnvironmentsCount,
            'keysGivenToday'             => $keysGivenToday,
            'keysNotReturned'            => $keysNotReturned,

            // ✅ si tu vista mostraba stats de rondas, las dejamos en 0 para no romperla
            'totalEntries'               => 0,
            'entriesWithIssuesCount'     => 0,
            'attendanceOk'               => 0,
            'attendanceIssues'           => 0,

            'environments'               => $environments,
        ]);
    }

    public function deliverKey(Request $request)
    {
        $request->validate([
            'schedule_id' => 'required|integer',
        ]);

        $scheduleId = (int) $request->schedule_id;

        $schedule = DB::table('instructor_programs')->where('id', $scheduleId)->first();
        if (!$schedule) {
            return response()->json(['ok' => false, 'message' => 'La programación seleccionada no existe.'], 404);
        }

        $envLink = DB::table('environment_instructor_programs')
            ->where('instructor_program_id', $scheduleId)
            ->first();

        if (!$envLink) {
            return response()->json(['ok' => false, 'message' => 'Primero debe asignar un ambiente antes de entregar la llave.'], 422);
        }

        $openLog = EnvironmentKeyLog::where('schedule_id', $scheduleId)
            ->whereNull('returned_at')
            ->orderBy('taken_at', 'desc')
            ->first();

        if ($openLog) {
            return response()->json(['ok' => false, 'message' => 'Ya hay una llave entregada para esta programación y no ha sido registrada como devuelta.'], 409);
        }

        EnvironmentKeyLog::create([
            'schedule_id'    => $scheduleId,
            'environment_id' => $envLink->environment_id,
            'instructor_id'  => null,
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

        $scheduleId = (int) $request->schedule_id;

        $openLog = EnvironmentKeyLog::where('schedule_id', $scheduleId)
            ->whereNull('returned_at')
            ->orderBy('taken_at', 'desc')
            ->first();

        if (!$openLog) {
            return response()->json(['ok' => false, 'message' => 'No hay una llave entregada pendiente por devolver.'], 409);
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

        $scheduleId    = (int) $request->schedule_id;
        $environmentId = $request->environment_id ? (int) $request->environment_id : null;

        // ✅ quitar asignación
        if (!$environmentId) {
            DB::table('environment_instructor_programs')
                ->where('instructor_program_id', $scheduleId)
                ->delete();

            return response()->json(['ok' => true]);
        }

        $schedule = DB::table('instructor_programs')->where('id', $scheduleId)->first();
        if (!$schedule) {
            return response()->json(['ok' => false, 'message' => 'La programación seleccionada no existe.'], 404);
        }

        // ✅ validar ocupado (cruce de horarios en el mismo día)
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
            return response()->json(['ok' => false, 'message' => 'Ese ambiente ya está ocupado en ese horario.'], 409);
        }

        DB::table('environment_instructor_programs')->updateOrInsert(
            ['instructor_program_id' => $scheduleId],
            ['environment_id' => $environmentId]
        );

        return response()->json(['ok' => true]);
    }
}
