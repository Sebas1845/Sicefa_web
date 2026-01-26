<?php

namespace Modules\SIGAC\Http\Controllers;
use App\Models\User;
use Modules\SICA\Entities\Environment;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\SIGAC\Entities\InstructorProgram;
use Illuminate\Support\Facades\DB;
use Modules\SIGAC\Entities\AttendanceRecord;
use Carbon\Carbon;
use Modules\SICA\Entities\Person;

class AttendanceRecordController extends Controller
{public function index(Request $request)
{
    $personaID = Auth::user()->person->id;

    // FECHA Y HORA desde la vista
    //$inputFecha = $request->input('date') ?? '02/07/2025 08:30';

   $inputFecha = $request->input('date');

try {
    if ($inputFecha) {
        // formato real del datetime-local
        $fechaHora = Carbon::createFromFormat('Y-m-d\TH:i', $inputFecha);
    } else {
        $fechaHora = Carbon::now();
    }
} catch (\Exception $e) {
    $fechaHora = Carbon::now();
}


    // Franjas horarias
    $allowedTimeRanges = [
        ['start' => '07:15:00', 'end' => '12:30:00', 'label' => '07:15 - 12:30'],
        ['start' => '13:00:00', 'end' => '16:30:00', 'label' => '13:00 - 16:30'],
    ];

    // Determinar en qué franja cae la hora seleccionada
    $currentRange = null;
    foreach ($allowedTimeRanges as $range) {
        if ($fechaHora->format('H:i:s') >= $range['start'] && $fechaHora->format('H:i:s') <= $range['end']) {
            $currentRange = $range;
            break;
        }
    }

    // Si no cae en ninguna franja, se puede mostrar mensaje vacío
    if (!$currentRange) {
        $programs = collect(); // colección vacía
        return view('sigac::assists.instructors.index', [
            'programs' => $programs,
            'fecha' => $fechaHora,
            'personaID' => $personaID,
            'titleView' => 'Registro de Asistencia'
        ]);
    }

    // BUSCAR PROGRAMAS DEL INSTRUCTOR EN ESA FECHA
   $programs = InstructorProgram::whereDate('date', $fechaHora->toDateString())
    ->whereTime('start_time', '<', $currentRange['end'])   // intersecta con la franja
    ->whereTime('end_time', '>', $currentRange['start'])  // intersecta con la franja
    ->whereHas('instructor_program_people', function($query) use ($personaID) {
        $query->where('person_id', $personaID);
    })
    ->with([
        'course.apprentices',             
        'instructor_program_people.person',
        'environments'
    ])
    ->get();


    // Filtrar aprendices que ya tienen asistencia registrada en la franja
    foreach ($programs as $program) {
       // 🔒 GUARDAR TOTAL REAL (ESTÁTICO)
$program->total_apprentices = $program->course->apprentices->count();

// 🔁 FILTRAR SOLO PARA LA LISTA (NO AFECTA EL TOTAL)
$program->course->apprentices = $program->course->apprentices->filter(function($apprentice) use ($fechaHora, $currentRange) {

    $apprenticeId = $apprentice->person->id;
    $courseId = $apprentice->course_id ?? 0;

    // Verificar si ya existe registro en la misma fecha y franja
    $exists = AttendanceRecord::where('apprentice_id', $apprenticeId)
        ->where('course_id', $courseId)
        ->where('attendance_date', $fechaHora->toDateString())
        ->whereTime('attendance_time', '>=', $currentRange['start'])
        ->whereTime('attendance_time', '<=', $currentRange['end'])
        ->exists();

    // 2️⃣ Verificar si el aprendiz está retirado (withdrawn) en cualquier registro previo
    $isWithdrawn = AttendanceRecord::where('apprentice_id', $apprenticeId)
        ->where('course_id', $courseId)
        ->where('attendance_status', 'withdrawn')
        ->exists();

    // Mostrar solo si NO tiene registro en esta franja y NO está withdrawn
    return !$exists;
 // solo mostrar si NO tiene registro en esta franja
})->values(); // reindexar colección
   // 📋 REGISTRADOS (SOLO VISUALIZACIÓN)
    $program->attendance_records = AttendanceRecord::where('course_id', $program->course->id)
    ->where('attendance_date', $fechaHora->toDateString())
    ->whereTime('attendance_time', '>=', $currentRange['start'])
    ->whereTime('attendance_time', '<=', $currentRange['end'])
    ->with('apprentice')
    ->get()
    ->map(function($record) {
        $record->attendance_status = strtolower(trim($record->attendance_status));
        return $record;
    });

    }
    return view('sigac::assists.instructors.index', [
        'programs' => $programs,
        'fecha' => $fechaHora,
        'currentRange' => $currentRange,
        'personaID' => $personaID,
        'titleView' => 'Registro de Asistencia'
    ]);



}

public function store(Request $request)
{
    $request->validate([
        'course_id' => 'required|exists:courses,id',
        'attendance_date' => 'required|date',
        'attendance_time' => 'required',
        'records' => 'required|array|min:1',
        'records.*.apprentice_id' => 'required|exists:people,id',
        'records.*.status' => 'required|in:present,late,absent,withdrawn,excused',
        'records.*.observations' => 'nullable|string',
        'records.*.evidence' => 'nullable|file|max:2048',
    ]);

    $instructorId = Auth::user()->person->id;

    // 🔎 Validar evidencia antes de la transacción
    foreach ($request->records as $record) {
        if ($record['status'] === 'excused' && !isset($record['evidence'])) {
            $apprentice = Person::find($record['apprentice_id']);
            return response()->json([
                'message' => "El aprendiz {$apprentice->fullname} tiene estado 'excused' pero no proporcionó evidencia.",
            ], 422);
        }
    }

    DB::beginTransaction();

    try {
        $registrados = [];

        foreach ($request->records as $record) {

            $apprentice = Person::find($record['apprentice_id']);
            $apprenticeName = $apprentice->fullname;

            /**
             * 🔎 Buscar el último registro ANTERIOR a esta lista
             */
            $lastRecord = AttendanceRecord::where('apprentice_id', $record['apprentice_id'])
                ->where('course_id', $request->course_id)
                ->where(function ($q) use ($request) {
                    $q->where('attendance_date', '<', $request->attendance_date)
                      ->orWhere(function ($q2) use ($request) {
                          $q2->where('attendance_date', $request->attendance_date)
                             ->where('attendance_time', '<', $request->attendance_time);
                      });
                })
                ->orderBy('attendance_date', 'desc')
                ->orderBy('attendance_time', 'desc')
                ->first();

            /**
             * 🔐 REGLA CLAVE
             * Si el último estado fue withdrawn, se fuerza.
             * Si no, se respeta lo que el instructor marcó.
             */
            $finalStatus = $record['status'];

            if ($lastRecord && $lastRecord->attendance_status === 'withdrawn') {
                $finalStatus = 'withdrawn';
            }

            // 📎 Evidencia
            $evidencePath = isset($record['evidence'])
                ? $record['evidence']->store('attendance_evidence', 'public')
                : null;

            AttendanceRecord::updateOrCreate(
                [
                    'attendance_date' => $request->attendance_date,
                    'attendance_time' => $request->attendance_time,
                    'course_id'       => $request->course_id,
                    'apprentice_id'   => $record['apprentice_id'],
                ],
                [
                    'instructor_id'     => $instructorId,
                    'attendance_status' => $finalStatus,
                    'observations'      => $record['observations'] ?? null,
                    'evidence'          => $evidencePath,
                ]
            );

            $registrados[] = $apprenticeName;
        }

        DB::commit();

        return response()->json([
            'message' => '<strong>Asistencias registradas correctamente.</strong>',
            'registrados' => $registrados
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Error al registrar la asistencia',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function update(Request $request)
{
    $request->validate([
        'id' => 'required|exists:attendance_records,id',
        'attendance_status' => 'required|in:present,late,absent,withdrawn,excused',
        'attendance_date' => 'required|date',
        'attendance_time' => 'required',
        'observations' => 'nullable|string',
        'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    ]);

    $attendance = AttendanceRecord::findOrFail($request->id);

    // Evidencia obligatoria para excusa
    if ($request->attendance_status === 'excused' && !$request->hasFile('evidence')) {
        return back()->withErrors(['evidence' => 'La evidencia es obligatoria para excusa.'])->withInput();
    }

    $attendance->attendance_status = $request->attendance_status;
    $attendance->attendance_date = $request->attendance_date;
    $attendance->attendance_time = $request->attendance_time;
    $attendance->observations = $request->observations;

    if ($request->hasFile('evidence')) {
        $file = $request->file('evidence');
        $filename = time().'_'.$file->getClientOriginalName();
        $path = $file->storeAs('attendances', $filename, 'public');
        $attendance->evidence = $path;
    }

    $attendance->save();

    return redirect()->back()->with('success', 'Asistencia actualizada correctamente.');
}



public function listAttendanceForCoordination(Request $request)
{
    // Si es AJAX, devolvemos JSON
    if ($request->ajax()) {

        // 1) Inputs
        $instructorId = $request->input('instructor_id'); // people.id
        $inputFecha   = $request->input('date');          // datetime-local Y-m-d\TH:i

        // 2) Validación mínima
        if (!$instructorId) {
            return response()->json([
                'ok' => true,
                'range' => null,
                'records' => [],
                'message' => 'Selecciona un instructor.'
            ]);
        }

        // 3) Parse fecha/hora
        try {
            $fechaHora = $inputFecha
                ? Carbon::createFromFormat('Y-m-d\TH:i', $inputFecha)
                : Carbon::now();
        } catch (\Exception $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Formato de fecha/hora inválido.'
            ], 422);
        }

        // 4) Franjas
        $allowedTimeRanges = [
            ['start' => '07:15:00', 'end' => '12:30:00', 'label' => '07:15 - 12:30'],
            ['start' => '13:00:00', 'end' => '16:30:00', 'label' => '13:00 - 16:30'],
        ];

        $currentRange = null;
        foreach ($allowedTimeRanges as $range) {
            if ($fechaHora->format('H:i:s') >= $range['start'] && $fechaHora->format('H:i:s') <= $range['end']) {
                $currentRange = $range;
                break;
            }
        }

        if (!$currentRange) {
            return response()->json([
                'ok' => true,
                'range' => null,
                'records' => [],
                'message' => 'La hora no cae en franja válida (07:15-12:30 o 13:00-16:30).'
            ]);
        }

        $dateOnly = $fechaHora->toDateString();

        // 5) Traer los registros YA registrados en esa fecha/franja para ese instructor
    $recordsQ = AttendanceRecord::query()
  ->where('instructor_id', $instructorId)
  ->where('attendance_date', $dateOnly)
  ->whereTime('attendance_time', '>=', $currentRange['start'])
  ->whereTime('attendance_time', '<=', $currentRange['end']);

// Trae SOLO columnas necesarias
$records = (clone $recordsQ)
  ->select([
      'id','attendance_date','attendance_time',
      'apprentice_id','attendance_status','observations',
      'course_id'
  ])
  ->with(['apprentice:id,first_name,first_last_name,second_last_name,document_number'])
  ->orderBy('attendance_time')
  ->get();

// Meta (ficha + programa) SOLO una vez
$meta = [
  'ficha' => null,
  'program_name' => null,
  'course_label' => null,
];

if ($records->count() > 0) {
    // OJO: si en esa franja hay más de un course_id, puedes decidir:
    // - mostrar "Varios cursos" o
    // - obligar a filtrar por curso (si lo implementas después).
    $courseId = $records->first()->course_id;

    $course = \Modules\SICA\Entities\Course::query()
      ->select('id','code','program_id')
      ->with(['program:id,name'])
      ->find($courseId);

    $meta['ficha'] = $course->code ?? null;
    $meta['program_name'] = $course->program->name ?? null;
    $meta['course_label'] = $course->code_name ?? null;
}

return response()->json([
  'ok' => true,
  'range' => $currentRange,
  'meta' => $meta,
  'records' => $records->map(fn($r) => [
      'id' => $r->id,
      'attendance_date' => $r->attendance_date,
      'attendance_time' => $r->attendance_time,
      'attendance_status' => strtolower(trim($r->attendance_status)),
      'observations' => $r->observations,
      'apprentice' => [
          'id' => $r->apprentice_id,
          'name' => $r->apprentice?->full_name,
          'document' => $r->apprentice?->document_number,
      ],
  ]),
  'message' => ''
]);
    }

    // -------------------------
    // NO AJAX: carga vista normal
    // -------------------------

    // instructores SOLO desde attendance_records
    $instructorIds = AttendanceRecord::query()
        ->select('instructor_id')
        ->distinct()
        ->pluck('instructor_id')
        ->filter()
        ->values();

    $instructors = Person::query()
        ->whereIn('id', $instructorIds)
        ->orderBy('first_name')
        ->get();

    return view('sigac::assists.academic_coordination.edit_attendance', [
        'titleView' => 'Edición de Asistencia (Coordinación)',
        'instructors' => $instructors,
    ]);
}
public function updateAjaxAcademicCoordination(Request $request)
{
    $request->validate([
        'id' => 'required|exists:attendance_records,id',
        'attendance_status' => 'required|in:present,late,absent,withdrawn,excused',
        'observations' => 'nullable|string|max:2000',
        'attendance_time' => 'nullable|date_format:H:i',
        'evidence' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
    ]);

    try {
        $record = AttendanceRecord::findOrFail($request->id);

        // ✅ actualiza campos
        $record->attendance_status = $request->attendance_status;
        $record->observations = $request->observations;

        // ✅ hora (si la mandan)
        if ($request->filled('attendance_time')) {
            $record->attendance_time = $request->attendance_time . ':00';
        }

        // ✅ evidencia (si mandan una nueva)
        if ($request->hasFile('evidence')) {
            if ($record->evidence && Storage::disk('public')->exists($record->evidence)) {
                Storage::disk('public')->delete($record->evidence);
            }

            $path = $request->file('evidence')->store('attendance_evidence', 'public');
            $record->evidence = $path;
        }

        $record->save();

        $evidenceUrl = $record->evidence ? asset('storage/'.$record->evidence) : null;

        return response()->json([
            'ok' => true,
            'message' => 'Actualizado correctamente.',
            'record' => [
                'id' => $record->id,
                'attendance_status' => $record->attendance_status,
                'observations' => $record->observations,
                'evidence_url' => $evidenceUrl,
                'attendance_time' => $record->attendance_time ? substr($record->attendance_time, 0, 5) : null,
            ],
        ]);

    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'message' => 'Error al actualizar.',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
