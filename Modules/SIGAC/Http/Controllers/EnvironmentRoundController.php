<?php

namespace Modules\SIGAC\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\SIGAC\Entities\EnvironmentRound;
use Modules\SIGAC\Entities\EnvironmentRoundEntry;
use Illuminate\Support\Facades\Schema;
use Modules\SIGAC\Entities\EnvironmentKeyLog;


class EnvironmentRoundController extends Controller
{
    /**
     * Lista de rondas (filtro fecha + jornada)
     */
    public function index(Request $request)
    {
        $date  = $request->input('date', now()->toDateString());
        $shift = $request->input('shift', 'MANANA');

        $rounds = EnvironmentRound::forDate($date)
            ->when($shift, function ($q) use ($shift) {
                $q->forShift($shift);
            })
            ->withCount('entries')
            ->orderBy('shift')
            ->get();

        // Títulos para el layout
        $titlePage = 'Rondas de Ambientes';
        $titleView = 'Rondas de Ambientes';

        return view('sigac::rounds.index', [
            'rounds'     => $rounds,
            'date'       => $date,
            'shift'      => $shift,
            'titlePage'  => $titlePage,
            'titleView'  => $titleView,
        ]);
    }

    /**
     * Crear (o devolver) la ronda de una fecha + jornada
     * y poblar sus entries desde el cronograma.
     */
    public function createOrShow(Request $request)
    {
        $request->validate([
            'date'  => ['required', 'date'],
            'shift' => ['required', 'in:MANANA,TARDE,NOCHE'],
        ]);

        $date  = $request->input('date');
        $shift = $request->input('shift');

        // 1. Obtener o crear la cabecera de la ronda
        $round = EnvironmentRound::firstOrCreate(
            [
                'date'  => $date,
                'shift' => $shift,
            ],
            [
                'created_by' => auth()->id(),
                'started_at' => Carbon::now(),
            ]
        );

        // 2. Cargar programación real del día/jornada
        $schedules = $this->getScheduleFor($date, $shift);

        // 3. Crear entries para cada horario si no existen
        foreach ($schedules as $s) {
            EnvironmentRoundEntry::firstOrCreate(
                [
                    'round_id'    => $round->id,
                    'schedule_id' => $s->schedule_id,
                ],
                [
                    'present_in_environment' => null,
                    'attendance_status'      => null,
                    'is_dirty'               => false,
                    'ac_status'              => 'NO_APLICA',
                ]
            );
        }

        return redirect()->route('sigac.coordinador.environment_rounds.show', $round->id);
    }

    /**
     * Mostrar una ronda con sus entries.
     */
    public function show($id)
    {
        $round = EnvironmentRound::findOrFail($id);

        $entries = EnvironmentRoundEntry::with([
            'schedule',
            'suggestedEnvironment',
            'updatedBy',
        ])
            ->where('round_id', $round->id)
            ->get();

        // Estadísticas básicas
        $totalEntries = $entries->count();

        // Ambientes con algún tipo de novedad
        $entriesWithIssues = $entries->filter(function ($e) {
            return $e->is_dirty
                || $e->ac_status === 'DANADO'
                || (is_string($e->other_issues) && trim($e->other_issues) !== '');
        });

        // Fichas distintas (usando course_id del schedule; ajusta según tu relación)
        $distinctCourses = $entries->pluck('schedule.course_id')->filter()->unique()->count();

        // Llaves: cuántas entregadas hoy / sin devolver
        $keysGivenToday = EnvironmentKeyLog::whereDate('created_at', $round->date)->count();
        $keysNotReturned = EnvironmentKeyLog::whereDate('created_at', $round->date)
            ->whereNull('returned_at')
            ->count();

        $titlePage = 'Detalle de Ronda';
        $titleView = 'Detalle de Ronda';

        return view('sigac::rounds.show', [
            'round'           => $round,
            'entries'         => $entries,
            'titlePage'       => $titlePage,
            'titleView'       => $titleView,
            'totalEntries'    => $totalEntries,
            'entriesWithIssuesCount' => $entriesWithIssues->count(),
            'distinctCourses' => $distinctCourses,
            'keysGivenToday'  => $keysGivenToday,
            'keysNotReturned' => $keysNotReturned,
        ]);
    }

    /**
     * Cerrar / bloquear una ronda.
     */
    public function lock($id)
    {
        $round = EnvironmentRound::findOrFail($id);

        $round->update([
            'is_locked'   => true,
            'finished_at' => Carbon::now(),
        ]);

        return redirect()
            ->route('sigac.coordinador.environment_rounds.show', $round->id)
            ->with('success', 'La ronda ha sido cerrada.');
    }

    /**
     * Query del cronograma real para fecha + jornada.
     * Ajusta nombres de tablas/columnas según tu BD si es necesario.
     */
    private function getScheduleFor($date, $shift)
    {
        // 1. Verificar que existan las tablas necesarias en esta BD
        $requiredTables = [
            'instructor_programs',
            'courses',
            'instructor_program_people',
            'people',
            'instructors',
            'programs',
            'environment_instructor_programs',
            'environments',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                // Si falta alguna, no intentamos leer el cronograma
                return collect();
            }
        }

        // 2. Rango de horas según la jornada
        $ranges = [
            'MANANA' => ['07:00:00', '12:30:00'],
            'TARDE'  => ['12:31:00', '18:00:00'],
            'NOCHE'  => ['18:01:00', '23:00:00'],
        ];

        [$start, $end] = $ranges[$shift];

        // 3. Query del cronograma real
        return DB::table('instructor_programs AS ip')
            ->join('courses AS c', 'c.id', '=', 'ip.course_id')
            ->leftJoin('instructor_program_people AS ipp', 'ipp.instructor_program_id', '=', 'ip.id')
            ->leftJoin('instructors AS ins', 'ins.person_id', '=', 'ipp.person_id')
            ->leftJoin('people AS p', 'p.id', '=', 'ipp.person_id')
            ->leftJoin('programs AS pr', 'pr.id', '=', 'c.program_id')
            ->leftJoin('environment_instructor_programs AS eip', 'eip.instructor_program_id', '=', 'ip.id')
            ->leftJoin('environments AS env', 'env.id', '=', 'eip.environment_id')
            ->whereDate('ip.date', $date)
            ->whereTime('ip.start_time', '>=', $start)
            ->whereTime('ip.start_time', '<=', $end)
            ->where(function ($q) {
                $q->where('env.exclude_from_rounds', 0)
                    ->orWhereNull('env.exclude_from_rounds');
            })
            ->select([
                'ip.id AS schedule_id',    // usaremos este id como schedule_id en las rondas
                'ip.date',
                'ip.start_time',
                'ip.end_time',
                'c.code AS ficha',
                'ins.id AS instructor_id',
                DB::raw("CONCAT(p.first_name, ' ', p.first_last_name) AS instructor"),
                'pr.name AS programa',
                'env.id AS environment_id',
                'env.name AS ambiente',
            ])
            ->orderBy('env.name')
            ->orderBy('ip.start_time')
            ->get();
    }
}
