<?php

namespace Modules\SIGAC\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\SICA\Entities\Employee;
use Modules\SICA\Entities\Contractor;
use Modules\SICA\Entities\Environment;
use Modules\SICA\Entities\Course;
use Modules\SICA\Entities\Country;
use Modules\SICA\Entities\Department;
use Modules\SICA\Entities\LearningOutcomePerson;
use Modules\SICA\Entities\Municipality;
use Modules\SICA\Entities\Holiday;
use Modules\SICA\Entities\Village;
use Modules\SIGAC\Entities\InstructorProgram;
use Modules\SIGAC\Entities\ExternalActivity;
use Modules\SIGAC\Entities\Profession;
use Modules\SIGAC\Entities\Quarterly;
use Modules\SIGAC\Entities\SpecialProgram;
use Modules\SIGAC\Entities\ProgramRequest;
use Modules\SIGAC\Entities\ProgramRequestDate;
use Modules\SIGAC\Entities\InstructorProgramNovelty;
use Modules\SIGAC\Entities\ProgramNovelty;
use Modules\SIGAC\Entities\InstructorProgramPerson;
use Modules\SIGAC\Entities\EnvironmentInstructorProgram;
use Modules\SIGAC\Entities\InstructorProgramOutcome;
use Modules\SIGAC\Entities\ProgramRequestDocument;
use Illuminate\Support\Facades\DB;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Program;
use Modules\SICA\Entities\Competencie;
use Modules\SICA\Entities\LearningOutcome;
use Modules\SICA\Entities\KnowledgeNetwork;
use Modules\SICA\Entities\Network;
use Modules\SICA\Entities\Line;
use Carbon\Carbon;
use Modules\SIGAC\Imports\ApprenticeLearningOutcomeImport;
use Modules\SIGAC\Imports\ProgramImport;
use Modules\SIGAC\Exports\ProgramCourseExport;
use Modules\SICA\Entities\App;
use Modules\SICA\Entities\Role;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use ZipArchive;
use Excel, Exception;
use Modules\SICA\Entities\Apprentice;
use Yajra\DataTables\Facades\DataTables;
use Modules\SICA\Entities\EPS;
use Modules\SICA\Entities\PopulationGroup;
use Modules\SICA\Entities\PensionEntity;
use hasRole;
use App\Mail\SIGAC\ProgramRequest\ProgramRequestStatusMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;




class ProgrammeController extends Controller
{
    // Programación de horarios
    public function programming()
    {
        $app = App::where('name', 'SIGAC')->orWhere('name', 'CEFAMAPS')->get();

        foreach ($app as $a) {
            $app_id[] = $a->id;
        }

        $user = Auth::user();
        $roles = '';
        if ($user) {
            $slug = Role::whereIn('app_id', $app_id)
                ->whereHas('users', function ($query) use ($user) {
                    $query->where('users.id', $user->id);
                })->pluck('slug')->first();

            if ($slug == 'sigac.academic_coordinator' || $slug == 'superadmin') {
                $roles = 'academic_coordination';
            } else {
                $roles = Str::replaceFirst('sigac.', '', $slug);
            }
        }


        $days = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miercoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo'
        ];

        $quarter = [
            '1' => '1',
            '2' => '2',
            '3' => '3',
            '4' => '4',
            '5' => '5',
            '6' => '6',
            '7' => '7',
        ];

        $holidays = Holiday::get();

        $view = [
            'titlePage' => trans('sigac::controllers.SIGAC_programming_schedules_title_page'),
            'titleView' => trans('sigac::controllers.SIGAC_programming_schedules_title_view'),
            'days' => $days,
            'quarter' => $quarter,
            'holidays' => json_encode($holidays),
            'role' => $roles
        ];


        return view('sigac::programming.index', $view);
    }


    // Gestion de la programacion
    public function management_programming()
    {
        $courses = Course::with('program')->get();

        return view('sigac::programming.create', [
            'courses' => $courses,
            'titlePage' => trans('Programación - Crear Programación'),
            'titleView' => trans('Crear Programación')

        ]);
    }

    public function management_programming_filterquarterlie(Request $request)
    {
        $course_id = $request->input('course_id');
        $quarter_number = $request->input('quarter_number');

        $executed_programming = InstructorProgramOutcome::whereHas('instructor_program', function ($query) use ($course_id) {
            $query->where('instructor_programs.course_id', $course_id);
        })
            ->select('learning_outcome_id', \DB::raw('hour as total_executed_hours'))
            ->groupBy('hour', 'learning_outcome_id')
            ->pluck('total_executed_hours', 'learning_outcome_id')
            ->toArray();



        $outcomes_not_programming = Quarterly::with('learning_outcome.competencie', 'learning_outcome.instructor_program_outcomes.instructor_program')
            ->whereHas('training_project.courses', function ($query) use ($course_id) {
                $query->where('courses.id', $course_id);
            })
            ->where('quarter_number', '<', $quarter_number)
            ->get() // Obtener todos los resultados relevantes
            ->filter(function ($quarterly) use ($executed_programming) {
                $learning_outcome_id = $quarterly->learning_outcome_id;
                $planned_hours = $quarterly->hour; // Horas planeadas en 'Quarterly'
                $executed_hours = $executed_programming[$learning_outcome_id] ?? 0; // Horas ejecutadas o 0 si no existe

                // Incluir si el resultado no ha sido ejecutado o si las horas ejecutadas son menores a las horas planeadas
                return $executed_hours < $planned_hours;
            })
            ->groupBy(function ($quarterly) use ($quarter_number) {
                $competencieName = $quarterly->learning_outcome->competencie->name;
                return str_replace('-' . $quarter_number, '', $competencieName); // Agrupar por nombre de competencia
            });

        debug($outcomes_not_programming);

        $quarterlie = Quarterly::with('learning_outcome.competencie')
            ->where('quarter_number', $quarter_number)
            ->whereHas('training_project.courses', function ($query) use ($course_id) {
                $query->where('courses.id', $course_id);
            })
            ->get()
            ->groupBy(function ($quarterly) use ($quarter_number) {
                $competencieName = $quarterly->learning_outcome->competencie->name;
                return str_replace('-' . $quarter_number, '', $competencieName);
            });


        return response()->json(['quarterlie' => $quarterlie, 'outcomes_not_programming' => $outcomes_not_programming]);
    }

    public function management_programming_filterlearning(Request $request)
    {
        $course_id = $request->input('course_id');

        $learning_outcome = LearningOutcome::whereHas('competencie.program.courses', function ($query) use ($course_id) {
            $query->where('courses.id', $course_id);
        })->pluck('name', 'id');

        return response()->json(['learning_outcome' => $learning_outcome->toArray()]);
    }

    public function management_programming_filterinstructor(Request $request)
    {
        $learning_outcome_id = $request->input('learning_outcome_id');
        $admin = $request->input('admin');


        if ($admin == 'true') {
            $getInstructor = DB::table('employees')
                ->join('employee_types', 'employees.employee_type_id', '=', 'employee_types.id')
                ->join('people', 'employees.person_id', '=', 'people.id')
                ->where('state', 'Activo')
                ->where('employee_types.name', 'Instructor')
                ->select('people.id', 'people.first_name', 'people.first_last_name', 'people.second_last_name', 'people.misena_email', 'people.telephone1', 'employee_types.name as employee_type_name')
                ->union(
                    DB::table('contractors')
                        ->join('employee_types', 'contractors.employee_type_id', '=', 'employee_types.id')
                        ->join('people', 'contractors.person_id', '=', 'people.id')
                        ->where('state', 'Activo')
                        ->where('employee_types.name', 'Instructor')
                        ->select('people.id', 'people.first_name', 'people.first_last_name', 'people.second_last_name', 'people.misena_email', 'people.telephone1', 'employee_types.name as employee_type_name')
                )->get();
            $instructors = $getInstructor->map(function ($i) {
                $id = $i->id;
                $name = $i->first_name . ' ' . $i->first_last_name . ' ' . $i->second_last_name;

                return [
                    'id' => $id,
                    'first_name' => $name
                ];
            });
        } else {
            $instructors = Person::join('learning_outcome_people', 'people.id', '=', 'learning_outcome_people.person_id')
                ->where('learning_outcome_people.learning_outcome_id', $learning_outcome_id)
                ->orderBy('learning_outcome_people.priority', 'asc')
                ->get(['people.id', 'people.first_name']);
        }

        return response()->json(['instructors' => $instructors]);
    }


    public function management_programming_filterenvironment(Request $request)
    {
        $admin = $request->input('admin');
        $learning_outcome = LearningOutcome::findOrfail($request->input('learning_outcome_id'));
        $competencie_id = $learning_outcome->competencie->id;

        if ($admin == 'true') {
            $environments = Environment::get()->pluck('name', 'id');
        } else {
            $environments = Environment::whereHas('class_environment.competencies', function ($query) use ($competencie_id) {
                $query->where('competencies.id', $competencie_id);
            })->pluck('name', 'id');
        }


        return response()->json(['environments' => $environments->toArray()]);
    }

    public function management_programming_filterstatelearning(Request $request)
    {
        $learning_outcome_id = $request->input('learning_outcome_id');
        $course_id = $request->input('course_id');

        // Obtener la lista de programas de instructor asociados al resultado de aprendizaje
        $instructor_programs = InstructorProgram::whereHas('instructor_program_outcomes', function ($query) use ($learning_outcome_id) {
            $query->where('learning_outcome_id', $learning_outcome_id);
        })
            ->where('course_id', $course_id)
            ->get();

        // Verificar si el resultado de aprendizaje está programado
        if ($instructor_programs->isEmpty()) {
            // El resultado de aprendizaje no está programado
            $message = 'No programado';
        } else {
            // El resultado de aprendizaje está programado
            $message = 'Programado';
            // Recorrer los programas de instructor para obtener la información de fecha y hora
            $scheduled_info = [];
            foreach ($instructor_programs as $program) {
                foreach ($program->instructor_program_outcomes as $outcome) {
                    // Verificamos que sea el resultado de aprendizaje seleccionado
                    if ($outcome->learning_outcome_id == $learning_outcome_id) {
                        // Sumamos las horas de este resultado de aprendizaje
                        $hours = $outcome->hour;

                        // Guardamos la información programada para este resultado de aprendizaje
                        $scheduled_info[] = [
                            'date' => $program->date,
                            'hours' => $hours,
                            'start_time' => $program->start_time,
                            'end_time' => $program->end_time
                        ];

                        // Rompemos el ciclo porque solo necesitamos registrar una vez el resultado de aprendizaje seleccionado
                        break;
                    }
                }
            }
        }

        return response()->json([
            'status' => $message,
            'scheduled_info' => $scheduled_info ?? null
        ]);
    }

    // Registrar programacion
    public function management_programming_store(Request $request)
    {
        $days = $request->days;
        $modality = $request->has('modality') ? 1 : 0;
        $fechas = [];
        $fechaActual = Carbon::parse($request->start_date);
        while ($fechaActual->lte(Carbon::parse($request->end_date))) {
            if (in_array($fechaActual->englishDayOfWeek, $days)) {
                $fechas[] = $fechaActual->toDateString();
            }
            $fechaActual->addDay();
        }

        $course_id = $request->course;
        $instructors = $request->instructor;
        $environments = $request->environment;
        $learning_outcomes = $request->learning_outcome;
        $hours = $request->hour;
        $querter_number = $request->querter_number;

        $c_modality = Course::findOrFail($course_id);

        foreach ($fechas as $f) {
            if ($modality == 1 || $c_modality->deschooling == 'Virtual') {
                $programming = InstructorProgram::where('date', $f)
                    ->where(function ($query) use ($request) {
                        $query->where(function ($q) use ($request) {
                            $q->where('start_time', '>=', $request->start_time)
                                ->where('start_time', '<=', $request->end_time);
                        })
                            ->orWhere(function ($q) use ($request) {
                                $q->where('end_time', '>=', $request->start_time)
                                    ->where('end_time', '<=', $request->end_time);
                            })
                            ->orWhere(function ($q) use ($request) {
                                $q->where('start_time', '<=', $request->start_time)
                                    ->where('end_time', '>=', $request->end_time);
                            });
                    })
                    ->whereHas('instructor_program_people', function ($query) use ($instructors) {
                        $query->whereIn('person_id', $instructors);
                    })
                    ->where('course_id', $course_id)
                    ->exists();
            } else {
                $programming = InstructorProgram::where('date', $f)
                    ->where(function ($query) use ($request) {
                        $query->where(function ($q) use ($request) {
                            $q->where('start_time', '>=', $request->start_time)
                                ->where('start_time', '<=', $request->end_time);
                        })
                            ->orWhere(function ($q) use ($request) {
                                $q->where('end_time', '>=', $request->start_time)
                                    ->where('end_time', '<=', $request->end_time);
                            })
                            ->orWhere(function ($q) use ($request) {
                                $q->where('start_time', '<=', $request->start_time)
                                    ->where('end_time', '>=', $request->end_time);
                            });
                    })
                    ->whereHas('instructor_program_people', function ($query) use ($instructors) {
                        $query->whereIn('person_id', $instructors);
                    })
                    ->whereHas('environment_instructor_programs', function ($query) use ($environments) {
                        $query->whereIn('environment_id', $environments);
                    })
                    ->where('course_id', $course_id)
                    ->exists();
            }

            $holidays = Holiday::where('date', $f)->exists();

            if ($programming || $holidays) {
                $fechas_no_registradas[] = $f;
                continue;
            }

            $quarterlies = Quarterly::with('learning_outcome.competencie', 'learning_outcome.people.professions')
                ->where('learning_outcome_id', $request->learning_outcome)
                ->whereHas('training_project.courses', function ($query) use ($course_id) {
                    $query->where('courses.id', $course_id);
                })->pluck('id')->first();

            try {
                DB::beginTransaction();

                if ($modality == 1 || $c_modality->deschooling == 'Virtual') {
                    $p = new InstructorProgram;
                    $p->date = $f;
                    $p->start_time = $request->start_time;
                    $p->end_time = $request->end_time;
                    $p->course_id = $request->course;
                    $p->quarter_number = $querter_number;
                    $p->state = 'Programado';
                    $p->modality = 'Medios Tecnologicos';
                    $p->save();
                    $instructor_program_id = $p->id;
                } else {
                    $p = new InstructorProgram;
                    $p->date = $f;
                    $p->start_time = $request->start_time;
                    $p->end_time = $request->end_time;
                    $p->course_id = $request->course;
                    $p->quarter_number = $querter_number;
                    $p->state = 'Programado';
                    $p->modality = 'Presencial';
                    $p->save();
                    $instructor_program_id = $p->id;

                    foreach ($environments as $index => $environment_id) {
                        $environment_instructor_programs = new EnvironmentInstructorProgram;
                        $environment_instructor_programs->instructor_program_id = $instructor_program_id;
                        $environment_instructor_programs->environment_id = $environment_id;
                        $environment_instructor_programs->save();
                    }
                }

                foreach ($instructors as $index => $instructor_id) {
                    $instructor_program_people = new InstructorProgramPerson;
                    $instructor_program_people->instructor_program_id = $instructor_program_id;
                    $instructor_program_people->person_id = $instructor_id;
                    $instructor_program_people->save();
                }

                foreach ($learning_outcomes as $index => $learning_outcome_id) {
                    $hour = $hours[$index];
                    $instructor_program_outcomes = new InstructorProgramOutcome;
                    $instructor_program_outcomes->instructor_program_id = $instructor_program_id;
                    $instructor_program_outcomes->learning_outcome_id = $learning_outcome_id;
                    $instructor_program_outcomes->hour = $hour;
                    $instructor_program_outcomes->state = 'Pendiente';
                    $instructor_program_outcomes->save();
                }

                DB::commit();
            } catch (\Exception $e) {
                // En caso de error, realiza un rollback de la transacción y maneja el error
                DB::rollBack();
                $mensaje = 'Ocurrio un error al registrar la programación.';
                return redirect()->back()->with(['error' => $mensaje]);

                \Log::error('Error en el registro: ' . $e->getMessage());
                \Log::error('Error en el registro: ' . $e->getTraceAsString());
            }
        }

        if (!empty($fechas_no_registradas)) {
            $hora_inicio = $request->start_time;
            $hora_fin = $request->end_time;
            $mensaje = 'No se pudieron registrar las siguientes fechas: ' . implode(', ', $fechas_no_registradas) . ', ya hay programación para estas fechas entre estas horas: ' . $hora_inicio . ' - ' . $hora_fin . '.';
            return redirect()->back()->with(['success' => $mensaje]);
        } else {
            $mensaje = 'Programación creada con éxito.';
            return redirect()->back()->with(['success' => $mensaje]);
        }
    }

    public function management_programming_search_course(Request $request)
    {
        $term = $request->get('code_course');
        $course = Course::where('code', 'LIKE', '%' . $term . '%')
            ->get();

        foreach ($course as $c) {
            $name = $c->program->name;
        }
        return response()->json([
            'program' => $name,
        ]);
    }

    public function management_programming_destroy(Request $request)
    {
        try {
            $instructor = $request->input('person_id');
            $code_course = $request->input('code_course');
            $quarter = $request->input('quarter');
            $daysSelected = $request->input('days');

            $year = Carbon::now()->year;

            $daysOfWeek = [
                'Sunday' => Carbon::SUNDAY,
                'Monday' => Carbon::MONDAY,
                'Tuesday' => Carbon::TUESDAY,
                'Wednesday' => Carbon::WEDNESDAY,
                'Thursday' => Carbon::THURSDAY,
                'Friday' => Carbon::FRIDAY,
                'Saturday' => Carbon::SATURDAY,
            ];


            $dayOfWeek = $daysOfWeek[$daysSelected] ?? null;

            // Verificar si el día es válido
            if ($dayOfWeek !== null) {
                $datesForDay = [];

                // Generar las fechas para el año actual directamente
                $startDate = Carbon::create($year, 1, 1); // Inicio del año
                $endDate = Carbon::create($year, 12, 31); // Fin del año

                $period = CarbonPeriod::create($startDate, $endDate);

                foreach ($period as $date) {
                    if ($date->dayOfWeek === $dayOfWeek) {
                        $datesForDay[] = $date->format('Y-m-d'); // Agregar la fecha al array
                    }
                }

                $instructor_program_ids = InstructorProgram::where('quarter_number', $quarter)
                    ->whereIn('date', $datesForDay)
                    ->whereHas('course', function ($query) use ($code_course) {
                        $query->where('code', $code_course);
                    })
                    ->whereHas('instructor_program_people.person', function ($query) use ($instructor) {
                        $query->where('id', $instructor);
                    })->pluck('id'); // Obtener solo los IDs

                if ($instructor_program_ids->isEmpty()) {
                    return redirect()->back()->with(['error' => 'No existe programación del trimestre ' . $quarter . ' para el día ' . $daysSelected . '.']);
                } else {
                    InstructorProgram::whereIn('id', $instructor_program_ids)->delete();
                    $mensaje = 'Programación eliminada con éxito.';
                    return redirect()->back()->with(['success' => $mensaje]);
                }

                // Eliminar los registros usando los IDs obtenidos
            } else {
                return redirect()->back()->with(['error' => 'Error al eliminar la programación']);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with(['error' => 'Error al eliminar la programación']);
        }
    }

    public function management_search_quarter_number(Request $request)
    {

        $course_id = $request->input('course_id');

        $course = Course::findOrFail($course_id);
        $quarter_number = $course->program->quarter_number;

        $modality = $course->deschooling;

        // Crear una colección con números desde 1 hasta quarter_number
        $results = collect(range(1, $quarter_number));


        return response()->json([
            'results' => $results,
            'modality' => $modality
        ]);
    }

    public function management_filter(Request $request)
    {

        $filter = $request->input('filter');

        if ($filter == 1) {
            $option = 1;
            // Nombres de los tipos de empleados
            $employeeTypeNames = ['Instructor'];

            // Obtener tanto empleados como contratistas que sean de los tipos especificados
            $results = DB::table('employees')
                ->join('employee_types', 'employees.employee_type_id', '=', 'employee_types.id')
                ->join('people', 'employees.person_id', '=', 'people.id')
                ->whereIn('employee_types.name', $employeeTypeNames)
                ->select('people.id', DB::raw('CONCAT(people.first_name, " ", people.first_last_name, " ", people.second_last_name) as name'))
                ->union(
                    DB::table('contractors')
                        ->join('employee_types', 'contractors.employee_type_id', '=', 'employee_types.id')
                        ->join('people', 'contractors.person_id', '=', 'people.id')
                        ->whereIn('employee_types.name', $employeeTypeNames)
                        ->select('people.id', DB::raw('CONCAT(people.first_name, " ", people.first_last_name) as name'))
                )
                ->get();
        } elseif ($filter == 2) {
            $option = 2;
            $results = Environment::get();
        } else {
            $option = 3;
            $results = Course::with('program')->get()->map(function ($course) {
                return [
                    'id' => $course->id,
                    'name' => $course->program->name . ' - ' . $course->code,
                ];
            });
        }

        return response()->json([
            'results' => $results,
            'option' => $option,
        ]);
    }

    public function programming_get(Request $request)
    {

        $programmingEvents = InstructorProgram::with('person', 'course.program', 'environment')->get();

        foreach ($programmingEvents as $programmingEvent) {
            $name = $programmingEvent->person->fullname;
            $parts = explode(' ', $name); // Dividir el nombre completo en palabras individuales
            $initials = '';

            foreach ($parts as $part) {
                $initials .= strtoupper(substr($part, 0, 1)); // Tomar la primera letra de cada palabra y convertirla a mayúsculas
            }

            $programmingEvent->person->initials = $initials; // Agregar las iniciales al objeto de persona en el evento de programación
        }

        // Construir una cadena de texto que contenga todas las iniciales
        $allInitials = '';
        foreach ($programmingEvents as $programmingEvent) {
            $allInitials .= $programmingEvent->person->initials;
        }
        return response()->json([
            'programmingEvents' => $programmingEvents,
            'initials' => $allInitials,

        ]);
    }

    public function management_search(Request $request)
    {

        $filter = $request->input('search');
        $option = $request->input('option');

        if ($option == 1) {

            $programmingEvents = InstructorProgram::with('instructor_program_people.person', 'course.program', 'course.municipality.department', 'environment_instructor_programs.environment', 'instructor_program_outcomes.learning_outcome', 'instructor_program_novelties')->whereHas('instructor_program_people.person', function ($query) use ($filter) {
                $query->where('id', $filter);
            })
                ->where('state', '=', 'Programado')
                ->get();
        } elseif ($option == 2) {
            $programmingEvents = InstructorProgram::with('instructor_program_people.person', 'course.program', 'course.municipality.department', 'environment_instructor_programs.environment', 'instructor_program_outcomes.learning_outcome')->whereHas('environment_instructor_programs.environment', function ($query) use ($filter) {
                $query->where('id', $filter);
            })
                ->where('state', '=', 'Programado')
                ->get();
        } else {
            $programmingEvents = InstructorProgram::with('instructor_program_people.person', 'course.program', 'course.municipality.department', 'environment_instructor_programs.environment', 'instructor_program_outcomes.learning_outcome')->whereHas('course', function ($query) use ($filter) {
                $query->where('id', $filter);
            })
                ->where('state', '=', 'Programado')
                ->get();
        }


        foreach ($programmingEvents as $programmingEvent) {
            foreach ($programmingEvent->instructor_program_people as $asociacion) {
                $name = $asociacion->person->fullname;
            }

            $parts = explode(' ', $name); // Dividir el nombre completo en palabras individuales
            $initials = '';

            foreach ($parts as $part) {
                $initials .= strtoupper(substr($part, 0, 1)); // Tomar la primera letra de cada palabra y convertirla a mayúsculas
            }

            foreach ($programmingEvent->instructor_program_people as $asociacion) {
                $asociacion->person->initials = $initials; // Agregar las iniciales al objeto de persona en el evento de programación
            }
        }

        // Construir una cadena de texto que contenga todas las iniciales
        $allInitials = '';
        foreach ($programmingEvents as $programmingEvent) {
            foreach ($programmingEvent->instructor_program_people as $asociacion) {
                $allInitials .= $asociacion->person->initials;
            }
        }

        // Devolver las iniciales junto con la respuesta JSON
        return response()->json([
            'programmingEvents' => $programmingEvents,
            'option' => $option,
            'initials' => $allInitials,
        ]);
    }

    /* Vista principal para la programación de eventos de instructor */
    public function event_programming()
    {
        $view = ['titlePage' => trans('sigac::controllers.SIGAC_event_programming_title_page'), 'titleView' => trans('sigac::controllers.SIGAC_event_programming_title_view')];
        return view('sigac::programming.event_programming', $view);
    }


    // ||----------------- Parametros --------------------||

    // Parametros de programacion
    public function parameter()
    {
        $external_activities = ExternalActivity::get();
        $special_programs = SpecialProgram::get();

        $titlePage = 'Parametros';
        $titleView = 'Parametros';
        return view('sigac::programming.parameters.index')->with([
            'titlePage' => $titlePage,
            'titleView' => $titleView,
            'external_activities' => $external_activities,
            'professions' => Profession::all(),
            'special_programs' => $special_programs
        ]);
    }

    /* Consultar programas de manera asincrónica*/
    public function program_search()
    {
        $data = Program::with('knowledge_network')->latest()->get();
        $programsselect = $data->map(function ($p) {
            $id = $p->id;
            $name = $p->name;
            return [
                'id' => $id,
                'name' => $name
            ];
        })->prepend(['id' => null, 'name' => 'Seleccione un programa'])->pluck('name', 'id');
        Session::put('programs', $programsselect);
        return Datatables::of($data)->addIndexColumn()
            ->addColumn('action', function ($row) {
                $id = $row->id;
                $actionBtn = '
                            <a class="btn btn-primary" href="' . route('sigac.academic_coordination.programming.competence.index', ['program_id' => $id]) . '" data-toggle="tooltip" data-placement="top" title="Ver competencias">
                            <i class="fa-solid fa-outdent"></i>
                            </a>
                        ';
                return $actionBtn;
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function parameter_competencies($program_id)
    {
        $programs = Session::get('programs');
        $program = Program::findOrFail($program_id);
        $name = $program->name;
        $competencies = Competencie::where('program_id', $program_id)->get();
        $titlePage = 'Parametros - Competencia';
        $titleView = 'Parametros - Competencia';
        return view('sigac::programming.parameters.competences.table')->with([
            'titlePage' => $titlePage,
            'titleView' => $titleView,
            'program_id' => $program_id,
            'programs' => $programs,
            'nameprogram' => $name,
            'competencies' => $competencies
        ]);
    }

    public function parameter_learning_outcomes($competencie_id, $program_id)
    {
        $Comps = Competencie::all();
        $competencies = $Comps->map(function ($c) {
            $id = $c->id;
            $name = $c->name;
            return [
                'id' => $id,
                'name' => $name
            ];
        })->prepend(['id' => null, 'name' => 'Seleccione una competencia'])->pluck('name', 'id');
        $competencie = Competencie::findOrFail($competencie_id);
        $name_competencia = $competencie->name;
        $learning_outcomes = LearningOutcome::where('competencie_id', $competencie_id)->get();
        $titlePage = 'Parametros - Resultado de aprendizaje';
        $titleView = 'Parametros - Resultado de aprendizaje';
        return view('sigac::programming.parameters.learning_outcomes.table')->with([
            'titlePage' => $titlePage,
            'titleView' => $titleView,
            'competencie_id' => $competencie_id,
            'learning_outcomes' => $learning_outcomes,
            'program_id' => $program_id,
            'namecompetencie' => $name_competencia,
            'competencies' => $competencies
        ]);
    }

    // Registrar profesion
    public function profession_store(Request $request)
    {
        $rules = [
            'name' => 'required',
            'level' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);


        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with(['message' => trans('sigac::profession.An_Error_Occurred_With_Form'), 'typealert' => 'danger']);
        }
        // Realizar registro
        if (Profession::create($request->all())) {
            return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['success' => trans('sigac::profession.The_Profession_Was_Added_Correctly')]);
        } else {
            return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['error' => trans('sigac::profession.Error_When_Adding_Profession')]);
        }
    }

    // Actualizar profesion
    public function profession_update(Request $request)
    {
        $p = Profession::find($request->input('id'));
        $p->name = e($request->input('name'));
        $p->level = e($request->input('level'));
        if ($p->save()) {
            return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['success' => trans('sigac::profession.Profession_Edited_Successfully')]);
        } else {
            return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['error' => trans('sigac::profession.Error_Editing_Profession')]);
        }
    }

    public function profession_destroy($id)
    {
        $p = Profession::find($id);
        if ($p->delete()) {
            return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['success' => trans('sigac::profession.Profession_Successfully_Eliminated')]);
        } else {
            return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['error' => trans('sigac::profession.Error_Deleting_Profession')]);
        }
    }

    // Registrar actividad externa
    public function external_activity_store(Request $request)
    {
        $rules = [
            'name' => 'required',
            'description' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
        }
        // Realizar registro
        if (ExternalActivity::create($request->all())) {
            return redirect()->back()->with(['success' => 'Actividad externa registrada exitosamente']);
        } else {
            return redirect()->back()->with(['error' => 'Error al registrar la actividad externa']);
        }
        return redirect()->back()->with(['error' => 'Ocurrio algun error']);
    }

    // Actualizar actividad externa
    public function external_activity_update(Request $request)
    {
        $e = ExternalActivity::find($request->input('id'));
        $e->name = e($request->input('name'));
        $e->description = e($request->input('description'));
        if ($e->save()) {
            return redirect()->back()->with(['success' => 'Actividad externa actualizada exitosamente']);
        } else {
            return redirect()->back()->with(['error' => 'Error al actualizar la actividad externa']);
        }
        return redirect()->back()->with(['error' => 'Ocurrio algun error']);
    }

    // Eliminar actividad externa
    public function external_activity_destroy($id)
    {
        // Obtener la actividad por su ID
        $e = ExternalActivity::findOrFail($id);

        // Realizar la eliminación
        $e->delete();

        return redirect()->back()->with('success', 'Actividad externa eliminada exitosamente');
    }

    // Registrar programa especial
    public function special_program_store(Request $request)
    {
        $rules = [
            'name' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
        }
        // Realizar registro
        if (SpecialProgram::create($request->all())) {
            return redirect()->back()->with(['success' => 'Programa especial registrado exitosamente']);
        } else {
            return redirect()->back()->with(['error' => 'Error al registrar el Programa especial']);
        }
        return redirect()->back()->with(['error' => 'Ocurrio algun error']);
    }

    // Actualizar programa especial
    public function special_program_update(Request $request)
    {
        $special = SpecialProgram::find($request->input('id'));
        $special->name = e($request->input('name'));
        if ($special->save()) {
            return redirect()->back()->with(['success' => 'Programa especial actualizado exitosamente']);
        } else {
            return redirect()->back()->with(['error' => 'Error al actualizar el Programa especial']);
        }
        return redirect()->back()->with(['error' => 'Ocurrio algun error']);
    }

    // Eliminar programa especial
    public function special_program_destroy($id)
    {
        // Obtener el programa especial por su ID
        $special = SpecialProgram::findOrFail($id);

        // Realizar la eliminación
        $special->delete();

        return redirect()->back()->with('success', 'Programa especial eliminado exitosamente');
    }

    // Registrar competencia
    public function competence_store(Request $request)
    {
        $rules = [
            'program_id' => 'required',
            'name' => 'required|string',
            'hour' => 'required|numeric',
            'type' => 'required|string',
            'code' => 'required|numeric',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
        }

        if (Competencie::create($request->all())) {
            $icon = 'success';
            $message_profession = 'Se agrego la competencia correctamente.';
        } else {
            $icon = 'error';
            $message_profession = 'Error al añadir la competencia.';
        }
        return redirect(route('sigac.academic_coordination.programming.parameters.index'))->with(['icon' => $icon, 'message_profession' => $message_profession]);
    }

    // Actualizar competencia
    public function competence_update(Request $request)
    {
        $c = Competencie::find($request->input('id'));
        $c->program_id = e($request->input('program_id'));
        $c->name = e($request->input('name'));
        $c->hour = e($request->input('hour'));
        $c->type = e($request->input('type'));
        $c->code = e($request->input('code'));

        if ($c->save()) {
            return redirect()->back()->with('success', 'Registro actualizado correctamente');
        } else {
            return redirect()->back()->with('error', 'Ocurrio un error al actualizar el registro');
        }
    }

    // Eliminar competencia
    public function competence_destroy($id)
    {
        $c = Competencie::find($id);
        if ($c->delete()) {
            return redirect()->back()->with('success', 'Registro eliminado correctamente');
        } else {
            return redirect()->back()->with('error', 'Error al elimminar el registro');
        }
    }

    // Registrar resultado de aprendizaje
    public function learning_outcome_store(Request $request)
    {
        $rules = [
            'competencie_id' => 'required',
            'name' => 'required|string',
            'hour' => 'required|numeric',
        ];
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
        }

        if (LearningOutcome::create($request->all())) {
            $icon = 'success';
            $message_profession = 'Se agrego el resultado de aprendizaje correctamente.';
        } else {
            $icon = 'error';
            $message_profession = 'Error al añadir el resultado de aprendizaje.';
        }
        return redirect()->back()->with(['icon' => $icon, 'message_profession' => $message_profession]);
    }

    // Actualizar resultado de aprendizaje
    public function learning_outcome_update(Request $request)
    {
        $l = LearningOutcome::find($request->input('id'));
        $l->competencie_id = e($request->input('competencie_id'));
        $l->name = e($request->input('name'));
        $l->hour = e($request->input('hour'));

        if ($l->save()) {
            return redirect()->back()->with('success', 'Registro actualizado correctamente');
        } else {
            return redirect()->back()->with('error', 'Ocurrio un error al actualizar el registro');
        }
    }

    // Eliminar Resultado de aprendizaje
    public function learning_outcome_destroy($id)
    {
        $l = LearningOutcome::find($id);
        if ($l->delete()) {
            return redirect()->back()->with('success', 'Registro eliminado correctamente');
        } else {
            return redirect()->back()->with('error', 'Error al elimminar el registro');
        }
    }

    public function learning_outcome_load_create($program_id)
    {
        $nameprogram = Program::where('id', '=', $program_id)->pluck('name')->first();
        return view('sigac::programming.parameters.learning_outcomes.load')->with([
            'titlePage' => 'Cargar Resultados de Aprendizaje',
            'titleView' => 'Cargar Resultados de Aprendizaje',
            'program_id' => $program_id,
            'nameprogram' => $nameprogram
        ]);
    }

    /* Registrar aprendices a partir de un archivo */
    public function learning_outcome_load_store(Request $request)
    {
        ini_set('max_execution_time', 3000); // Ampliar el tiempo máximo de la ejecución del proceso en el servidor
        $validator = Validator::make(
            $request->all(),
            ['archivo' => 'required'],
            ['archivo.required' => 'El archivo es requerido.']
        );
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
        } else {
            $path = $request->file('archivo'); // Obtener ubicación temporal del archivo en el servidor
            $array = Excel::toArray(new ApprenticeLearningOutcomeImport, $path); // Convertir el contenido del archivo excel en una arreglo de arreglos
            $program_name = $array[0][4][2]; // Obtener la ficha del curso y el nombre del programa en un arreglo
            $course_code = $array[0][1][2];
            $datas = array_splice($array[0], 12, count($array[0])); // Obtener solo los registros de los datos de los aprendices
            try {
                $count = 0;

                DB::beginTransaction();
                // Recorrer datos y relizar registros
                $program_id = $request->program_id;
                $program = Program::findOrFail($program_id);
                $nameprogramselected = $program->name;

                if ($nameprogramselected != $program_name) {
                    DB::rollBack(); // Devolver cambios realizados durante la transacción
                    return back()->with('error', 'El programa ingresado (' . $program_name . ') para el registro de los resultados no coincide con el seleccionado (' . $nameprogramselected . ').')->with('typealert', 'danger');
                }

                foreach ($datas as $data) {
                    $competencie = explode(" - ", $data[5]);
                    if ($competencie) {
                        if (count($competencie) > 1) {
                            $code_competencie = $competencie[0];
                            // Si hay más de una parte después de dividir por el guión
                            $name_competencia = trim(preg_replace('/^[0-9\s\-\x{2022}\x{0095}\t]+/u', '', $competencie[1])); // Eliminar números y espacios al principio de la cadena

                        } else {
                            // Si no hay un guión, entonces tomar el nombre completo sin modificar
                            $name_competencia = trim($competencie[0]);
                        }
                    }
                    $learning_outcome = explode(" - ", $data[6]); // Dividir la cadena por el guión ('-')
                    if ($learning_outcome) {
                        if (count($learning_outcome) > 1) {
                            // Si hay más de una parte después de dividir por el guión
                            $name_learning = trim(preg_replace('/^[0-9\s\-\x{2022}\x{0095}\t]+/u', '', $learning_outcome[1]));
                        } else {
                            // Si no hay un guión, entonces tomar el nombre completo sin modificar
                            $name_learning = trim($learning_outcome[0]);
                        }
                        $competenciefind = Competencie::where('name', '=', $name_competencia)->where('program_id', $program_id)->first();

                        if ($competenciefind) {
                            $competencie_id = $competenciefind->id;
                        } else {
                            $competencies = new Competencie;
                            $competencies->program_id = $program_id;
                            $competencies->code = $code_competencie;
                            // Convierte la frase a minúsculas
                            $name_competencia = mb_strtolower($name_competencia, 'UTF-8');
                            // Capitaliza la primera letra
                            $name_competencia = mb_strtoupper(mb_substr($name_competencia, 0, 1), 'UTF-8') . mb_substr($name_competencia, 1);
                            $competencies->name = $name_competencia;
                            $competencies->hour = 0;
                            $competencies->type = 'Técnico';
                            $competencies->save();
                            $competencie_id = $competencies->id;
                        }

                        $learning_outcome = LearningOutcome::where('name', '=', $name_learning)->whereHas('competencie', function ($query) use ($program_id) {
                            $query->where('program_id', $program_id);
                        })->first();

                        if ($learning_outcome) {
                            $learning_outcome_id = $learning_outcome->id;
                        } else {
                            $learning_outcomes = new LearningOutcome;
                            $learning_outcomes->competencie_id = $competencie_id;

                            // Convierte la frase a minúsculas
                            $name_learning = mb_strtolower($name_learning, 'UTF-8');

                            // Capitaliza la primera letra
                            $name_learning = mb_strtoupper(mb_substr($name_learning, 0, 1), 'UTF-8') . mb_substr($name_learning, 1);

                            // Asigna el nombre convertido al campo correspondiente
                            $learning_outcomes->name = $name_learning;

                            $learning_outcomes->hour = 0;
                            $learning_outcomes->save();
                            $count++;
                        }
                    }
                }

                DB::commit();

                return back()->with('success', 'Archivo excel escaneado coerrectamente. ' . $count . ' Resultados registrados exitosamente.')->with('typealert', 'success');
            } catch (Exception $e) {
                DB::rollBack(); // Devolver cambios realizados durante la transacción
                return back()->with('error', 'Ocurrio un error en la importación y/o registro de datos del archivo excel cargado. <hr> <strong>Error: </strong> (' . $e->getMessage() . ').')->with('typealert', 'danger');
            }
        }
    }

    public function program_load_create()
    {

        return view('sigac::programming.parameters.competences.load')->with([
            'titlePage' => 'Cargar Programas',
            'titleView' => 'Cargar Programas'
        ]);
    }


    public function program_export()
    {

        return Excel::download(new ProgramCourseExport, 'programs.xlsx');
    }

    /* Registrar aprendices a partir de un archivo */
    public function program_load_store(Request $request)
    {
        ini_set('max_execution_time', 3000); // Ampliar el tiempo máximo de la ejecución del proceso en el servidor
        $validator = Validator::make(
            $request->all(),
            ['archivo' => 'required'],
            ['archivo.required' => 'El archivo es requerido.']
        );
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
        } else {
            $path = $request->file('archivo'); // Obtener ubicación temporal del archivo en el servidor
            // Usar el importador personalizado para obtener los datos
            $import = new ProgramImport();
            $array = Excel::toArray($import, $path);
            $datas = array_splice($array[0], 1, count($array[0]));
            try {
                $count = 0;
                // Recorrer datos y relizar registros

                DB::beginTransaction();

                foreach ($datas as $data) {
                    $sofia_code = $data[0];
                    $version = $data[1];
                    $training_type = $data[3];
                    $name = str_replace('.', '', $data[4]);
                    $maximum_duration = $data[6];
                    $program_type = $data[5];
                    $name_line = $data[19];
                    $name_network = $data[20];
                    $name_knowledge_network = $data[21];
                    $modality = $data[22];
                    $priority_bets = $data[23];
                    $fic = $data[24];
                    $file_meses_lectiva = explode(".", $data[25]);
                    $meses_lectiva = $file_meses_lectiva[0];
                    $file_meses_productiva = explode(".", $data[26]);
                    $meses_productiva = $file_meses_productiva[0];
                    $file_quarter_number = explode(".", $data[27]);
                    $quarter_number = $file_quarter_number[0];
                    $knowledge_network = KnowledgeNetwork::where('name', '=', $name_knowledge_network)->first();



                    if ($knowledge_network) {
                        $knowledge_network_id = $knowledge_network->id;
                    } else {

                        $network = Network::where('name', '=', $name_network)->first();

                        if ($network) {
                            $network_id = $network->id;
                        } else {
                            $line = Line::where('name', '=', $name_line)->first();

                            if ($line) {
                                $line_id = $line->id;
                            } else {
                                $l = new Line;
                                $l->name = $name_line;
                                $l->save();
                                $line_id = $l->id;
                            }
                            $networ = new Network;
                            $networ->name = $name_network;
                            $networ->line_id = $line_id;
                            $networ->save();
                            $network_id = $networ->id;
                        }

                        $knowledge = new KnowledgeNetwork;
                        $knowledge->name = $name_knowledge_network;
                        $knowledge->network_id = $network_id;
                        $knowledge->save();
                        $knowledge_network_id = $knowledge->id;
                    }

                    $program = Program::where('name', '=', $name)->where('program_type', '=', $program_type)->first();

                    if ($program) {
                        $program->sofia_code = $sofia_code;
                        $program->version = $version;
                        $program->training_type = $training_type;
                        $program->maximum_duration = $maximum_duration;
                        $program->modality = $modality;
                        $program->priority_bets = $priority_bets;
                        $program->fic = $fic;
                        $program->knowledge_network_id = $knowledge_network_id;
                        $program->months_lectiva = $meses_lectiva;
                        $program->months_productiva = $meses_productiva;
                        $program->quarter_number = $quarter_number;
                        $program->save();
                    } else {
                        $program = new Program;
                        $program->sofia_code = $sofia_code;
                        $program->version = $version;
                        $program->training_type = $training_type;
                        $program->name = $name;
                        $program->quarter_number = $quarter_number;
                        $program->knowledge_network_id = $knowledge_network_id;
                        $program->program_type = $program_type;
                        $program->maximum_duration = $maximum_duration;
                        $program->modality = $modality;
                        $program->priority_bets = $priority_bets;
                        $program->fic = $fic;
                        $program->months_lectiva = $meses_lectiva;
                        $program->months_productiva = $meses_productiva;
                        $program->save();
                        $count++;
                    }
                }

                DB::commit();

                return back()->with('success', 'Archivo excel escaneado coerrectamente. ' . $count . ' Programas registrados exitosamente.')->with('typealert', 'success');
            } catch (Exception $e) {
                DB::rollBack(); // Devolver cambios realizados durante la transacción
                \Log::error('Error en el registro: ' . $e->getMessage());
                return back()->with('error', 'Ocurrio un error en la importación y/o registro de datos del archivo excel cargado. <hr> <strong>Error: </strong> (' . $e->getMessage() . ').')->with('typealert', 'danger');
            }
        }
    }

    public function program_request_table(Request $request)
    {
        $user = auth()->user();
        if (!$user) abort(403);

        $roleRoute = getRoleRouteName(\Illuminate\Support\Facades\Route::currentRouteName()); // instructor|academic_coordination|campesena|support|etc

        // Roles base
        $isInstructor = function_exists('checkRol') ? checkRol('sigac.instructor') : false;
        $isCoordAcad  = function_exists('checkRol') ? (checkRol('sigac.academic_coordinator') || checkRol('superadmin')) : false;
        $isCampesena  = function_exists('checkRol') ? checkRol('sigac.campesena') : false;
        $isSupport    = function_exists('checkRol') ? (checkRol('gdf.academic_support') || checkRol('gdf.campesena_support')) : false;

        $query = ProgramRequest::query()
            ->with([
                'person',
                'program',
                'special_program',
                'municipality.department',
                'village',
                'dates',       // ✅ relación correcta
                'documents',   // ✅ relación correcta
                'area',
                'budgetItem',
            ]);

        // =========================
        // Filtros opcionales
        // =========================
        $state = trim((string) $request->get('state', ''));
        if ($state !== '') {
            $query->where('state', $state);
        }

        $area_id = (int) $request->input('area_id', 0);
        if ($area_id > 0) {
            $query->where('area_id', $area_id);
        }

        // =========================
        // Scope por rol
        // =========================

        // 1) Instructor: SOLO sus solicitudes (todas)
        if ($isInstructor) {
            $query->where('person_id', $user->person_id);
        }

        // 2) Coordinación Académica: SOLO área 2
        elseif ($isCoordAcad) {
            $query->where('area_id', 2);

            // ✅ Importante: si NO envían state, mostrar también Preconfirmado
            // para que, al aprobar, pueda caracterizar desde la misma bandeja.
            if ($state === '') {
                $query->whereIn('state', ['Pendiente', 'Preconfirmado']);
            }
        }

        // 3) Campesena: SOLO área 1
        elseif ($isCampesena) {
            $query->where('area_id', 1);

            // ✅ Igual que coordinación
            if ($state === '') {
                $query->whereIn('state', ['Pendiente', 'Preconfirmado']);
            }
        }

        // 4) Apoyo (si también quieres que vea bandeja general)
        //    - si NO quieres que Apoyo use esta tabla, quita este bloque y deja 403.
        elseif ($isSupport) {
            // Apoyo puede ver Preconfirmadas (y opcionalmente Devueltas)
            if ($state === '') {
                $query->whereIn('state', ['Preconfirmado']);
            } else {
                // si mandan state=..., lo respetamos (ya lo filtró arriba)
            }

            // Opcional: filtrar por áreas del apoyo
            $areas = [];
            if (checkRol('gdf.campesena_support')) $areas[] = 1;
            if (checkRol('gdf.academic_support'))  $areas[] = 2;
            if (!empty($areas)) $query->whereIn('area_id', $areas);
        } else {
            abort(403);
        }

        // =========================
        // Listado
        // =========================
        $program_requests = $query->orderByDesc('id')->get();

        $titlePage = 'Solicitudes de Programación';
        $titleView = 'Solicitudes de Programación';

        return view('sigac::programming.program_request.table', compact(
            'program_requests',
            'titlePage',
            'titleView',
            'roleRoute'
        ));
    }


    // Buscar instructor
    public function program_request_searchperson(Request $request)
    {
        $term = $request->input('name');

        $persons = Person::whereRaw("CONCAT(first_name, ' ', first_last_name, ' ', second_last_name) LIKE ?", ['%' . $term . '%'])->get();

        $results = [];
        foreach ($persons as $person) {
            $results[] = [
                'id' => $person->id,
                'text' => $person->first_name . ' ' . $person->first_last_name,
            ];
        }

        return response()->json($results);
    }

    public function program_request_searchprofession(Request $request)
    {
        try {
            $person_id = $request->input('person_id');

            $professions = Profession::whereHas('people', function ($query) use ($person_id) {
                $query->where('people.id', $person_id);
            })->first();

            $response = [
                'professions' => $professions ?? null,
            ];

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function program_request_searchempresa(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        $items = ProgramRequest::query()
            ->selectRaw('empresa as text, MAX(address) as address')
            ->when($q !== '', fn($qq) => $qq->where('empresa', 'like', "%{$q}%"))
            ->whereNotNull('empresa')
            ->where('empresa', '!=', '')
            ->groupBy('empresa')
            ->orderBy('empresa')
            ->limit(20)
            ->get();

        return response()->json($items);
    }


    public function program_request_searchvillages(Request $request)
    {
        $municipalityId = (int) $request->query('municipality_id');

        if ($municipalityId <= 0) {
            return response()->json([], 200);
        }

        // (Opcional) Validar que el municipio exista
        $existsMun = DB::table('municipalities')->where('id', $municipalityId)->exists();
        if (!$existsMun) {
            return response()->json([], 200);
        }

        $rows = DB::table('villages')
            ->select('id', 'name')
            ->where('municipality_id', $municipalityId)
            ->orderBy('name')
            ->limit(2000) // evita respuestas gigantes si un municipio tiene demasiadas
            ->get();

        return response()->json(
            $rows->map(fn($r) => ['id' => $r->id, 'text' => (string) $r->name])->values()
        );
    }





    public function program_request_storevillages(Request $request)
    {
        $request->validate([
            'municipality_id' => 'required|integer|exists:municipalities,id',
            'name' => 'required|string|max:255'
        ]);

        $v = Village::firstOrCreate([
            'municipality_id' => (int) $request->municipality_id,
            'name' => strtoupper(trim($request->name)),
        ]);

        return response()->json(['id' => $v->id, 'text' => $v->name]);
    }

    public function program_request_searchapplicant(Request $request)
    {
        $q = trim((string)$request->get('q', ''));

        // AJUSTA nombres de columnas si en tu tabla son applicant_name/applicant_email, etc.
        // Aquí uso applicant, email, telephone porque en tu método viejo así estaban.
        $items = ProgramRequest::query()
            ->select([
                DB::raw('MAX(applicant) as text'),
                DB::raw('MIN(email) as email'),
                DB::raw('MIN(telephone) as telephone'),
            ])
            ->when($q !== '', fn($qq) => $qq->where('applicant', 'like', "%{$q}%"))
            ->whereNotNull('applicant')
            ->where('applicant', '!=', '')
            ->groupBy('applicant')
            ->orderBy('text')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function program_request_searchschedule(Request $request)
    {
        $date = $request->input('date');
        $start_time = $request->input('start_time');
        $end_time = $request->input('end_time');
        $instructor = checkRol('sigac.academic_coordinator')
            ? $request->input('instructor')
            : Auth::user()->person->id;

        $exists = InstructorProgram::where('date', $date)
            ->where(function ($q) use ($start_time, $end_time) {
                $q->where(function ($q2) use ($start_time) {
                    $q2->where('start_time', '<=', $start_time)
                        ->where('end_time', '>=', $start_time);
                })->orWhere(function ($q2) use ($start_time, $end_time) {
                    $q2->where('start_time', '<=', $end_time)
                        ->where('end_time', '>=', $end_time);
                })->orWhere(function ($q2) use ($start_time, $end_time) {
                    $q2->where('start_time', '>=', $start_time)
                        ->where('end_time', '<=', $end_time);
                });
            })
            ->whereHas('instructor_program_people', function ($q) use ($instructor) {
                $q->where('person_id', $instructor);
            })
            ->exists();

        return response()->json(['conflict' => $exists]);
    }



    public function program_request_document_store(Request $request, $id)
    {
        // 0) Permisos (ajusta si quieres)
        if (!function_exists('checkRol') || !(
            checkRol('sigac.instructor') ||
            checkRol('sigac.academic_coordinator') ||
            checkRol('sigac.campesena') ||
            checkRol('gdf.academic_support') ||
            checkRol('gdf.campesena_support') ||
            checkRol('superadmin')
        )) {
            abort(403);
        }

        // 1) Cargar solicitud
        $pr = ProgramRequest::with('documents')->findOrFail($id);

        // Instructor solo puede subir a lo suyo
        if (function_exists('checkRol') && checkRol('sigac.instructor')) {
            $personId = (int) optional(auth()->user()->person)->id;
            if (!$personId || (int)$pr->person_id !== $personId) abort(403);
        }

        // 2) Validación
        // IMPORTANTE: documents puede venir como array o como file único.
        $request->validate([
            'documents'   => ['required'],
            'documents.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'], // 10MB
        ], [
            'documents.required' => 'Debes adjuntar al menos un archivo.',
        ]);

        // 3) Verificar que realmente llegaron archivos
        if (!$request->hasFile('documents')) {
            return back()->with('error', 'No se recibió ningún archivo. Revisa que el formulario tenga enctype="multipart/form-data" y que el input se llame documents[].');
        }

        DB::beginTransaction();
        try {
            $files = $request->file('documents');

            // Normalizar: si llega 1 archivo, lo convertimos a array
            if (!is_array($files)) $files = [$files];

            $baseDir = "sigac/program_requests/{$pr->id}/documents";

            $saved = 0;

            foreach ($files as $file) {
                if (!$file || !$file->isValid()) {
                    Log::warning('SIGAC upload invalid file', [
                        'program_request_id' => $pr->id,
                        'error' => $file?->getError(),
                        'error_msg' => $file?->getErrorMessage(),
                    ]);
                    continue;
                }

                $original = $file->getClientOriginalName();

                // Nombre seguro
                $safeBase = Str::slug(pathinfo($original, PATHINFO_FILENAME));
                $ext      = strtolower($file->getClientOriginalExtension());
                $filename = $safeBase . '-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.' . $ext;

                // Guardar en disco public (storage/app/public/...)
                $path = $file->storeAs($baseDir, $filename, 'public');

                if (!$path) {
                    Log::error('SIGAC storeAs returned null', [
                        'program_request_id' => $pr->id,
                        'original' => $original,
                        'disk' => 'public',
                        'baseDir' => $baseDir
                    ]);
                    continue;
                }

                // Registrar en BD (esto es CLAVE para que luego aparezca en tabla)
                ProgramRequestDocument::create([
                    'program_request_id' => $pr->id,
                    'name' => $original,
                    'path' => $path, // relativo al disk public
                ]);

                $saved++;
            }

            if ($saved === 0) {
                DB::rollBack();
                return back()->with('error', 'No se pudo guardar ningún archivo. Revisa logs y permisos del storage.');
            }

            DB::commit();

            return back()->with('success', "Listo. Se guardaron {$saved} archivo(s).");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('program_request_document_store exception', [
                'program_request_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->with('error', 'Error subiendo documentos: ' . $e->getMessage());
        }
    }



    public function program_request_approve($id)
    {
        $pr = ProgramRequest::findOrFail($id);

        // valida rol + área
        $this->authorizeCoordinatorArea($pr);

        // valida estado
        if ($pr->state !== 'Pendiente') {
            return back()->with('warning', 'Esta solicitud ya no está en estado Pendiente.');
        }

        $pr->state = 'Preconfirmado';
        $pr->save();

        return back()->with('success', 'Solicitud aprobada correctamente.');
    }


    // Caracterizar programa
    public function program_request_confirmation(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $program_request = ProgramRequest::findOrFail($id);

            // Si ya no está pendiente, evita reprocesos
            if ($program_request->state !== 'Pendiente') {
                DB::rollBack();
                return back()->with('warning', 'La solicitud ya no está en estado Pendiente.');
            }

            $program_request->state = 'Preconfirmado';
            $program_request->save();

            DB::commit();

            if (Route::is('sigac.campesena.*')) {
                return redirect()
                    ->route('sigac.campesena.programming.program_request.characterization.index')
                    ->with('success', 'Solicitud Confirmada');
            }

            return redirect()
                ->route('sigac.academic_coordination.programming.program_request.characterization.index')
                ->with('success', 'Solicitud Confirmada');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('program_request_confirmation error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Error confirmando: ' . $e->getMessage());
        }
    }


    public function program_request_dismiss(Request $request, $id)
    {
        $request->validate([
            'observation' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();
        try {
            // 🔴 IMPORTANTE: cargar SIEMPRE 'dates'
            $pr = ProgramRequest::with(['person', 'dates'])->findOrFail($id);

            $pr->observation = $request->observation;
            $pr->state = 'Desestimado';
            $pr->save();

            DB::commit();

            // 📧 Correo (incluye fechas)
            $this->notifyProgramRequest(
                $pr,
                'dismissed',
                $pr->observation
            );

            return back()->with('success', 'Solicitud desestimada y notificada por correo.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', 'Error al desestimar: ' . $e->getMessage());
        }
    }




    public function program_request_characterization_store(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // 🔴 IMPORTANTE: cargar SIEMPRE 'dates'
            $program_request = ProgramRequest::with(['dates', 'person', 'program'])->findOrFail($id);
            $dates = $program_request->dates ?? collect();

            if ($dates->isEmpty()) {
                DB::rollBack();
                return back()->with('error', 'No se puede caracterizar: la solicitud no tiene fechas registradas.');
            }

            // Inicio/fin desde fechas reales
            $startDate = optional($dates->sortBy('date')->first())->date;
            $endDate   = optional($dates->sortByDesc('date')->first())->date;

            if (!$startDate || !$endDate) {
                DB::rollBack();
                return back()->with('error', 'No se pudo determinar fecha inicio/fin desde las fechas registradas.');
            }

            $code_course       = trim((string) $request->input('code_course'));
            $code_empresa      = trim((string) $request->input('code_empresa'));
            $date_inscription  = $request->input('date_inscription');
            $date_characterization = Carbon::now()->toDateString();

            // Si no envían ficha, generar una
            if ($code_course === '') {
                $code_course = 'CUR-' . $program_request->id . '-' . Carbon::now()->format('Ymd') . '-' . Str::upper(Str::random(4));
            }

            // 1) Persistir datos en solicitud
            $program_request->date_inscription      = $date_inscription;
            $program_request->code_empresa          = $code_empresa;
            $program_request->code_course           = $code_course;
            $program_request->date_characterization = $date_characterization;
            $program_request->state                 = 'Confirmado';

            // Si tu tabla tiene start/end
            if (\Schema::hasColumn('program_requests', 'start_date')) $program_request->start_date = $startDate;
            if (\Schema::hasColumn('program_requests', 'end_date'))   $program_request->end_date   = $endDate;

            $program_request->save();

            // 2) Course: NO duplicar. Buscar por ficha (code)
            $course = Course::where('code', $code_course)->first();

            if (!$course) {
                $course = new Course();
                $course->code            = $code_course;
                $course->start_date      = $startDate;
                $course->end_date        = $endDate;
                $course->status          = 'Activo';
                $course->program_id      = $program_request->program_id;
                $course->municipality_id = $program_request->municipality_id;
                $course->save();
            } else {
                // (Opcional) sincroniza para coherencia
                $course->program_id      = $course->program_id ?: $program_request->program_id;
                $course->municipality_id = $course->municipality_id ?: $program_request->municipality_id;
                $course->start_date      = $startDate;
                $course->end_date        = $endDate;
                if (empty($course->status)) $course->status = 'Activo';
                $course->save();
            }

            // 3) Crear instructor_program por cada fecha (sin duplicar)
            foreach ($dates as $d) {
                $ip = InstructorProgram::where('course_id', $course->id)
                    ->whereDate('date', $d->date)
                    ->where('start_time', $d->start_time)
                    ->where('end_time', $d->end_time)
                    ->first();

                if (!$ip) {
                    $ip = new InstructorProgram();
                    $ip->date       = $d->date;
                    $ip->start_time = $d->start_time;
                    $ip->end_time   = $d->end_time;
                    $ip->course_id  = $course->id;
                    $ip->state      = 'Programado';
                    $ip->modality   = 'Complementaria';
                    $ip->save();
                } else {
                    if ($ip->state !== 'Programado') {
                        $ip->state = 'Programado';
                        $ip->save();
                    }
                }

                // Vincular instructor (sin duplicar)
                InstructorProgramPerson::firstOrCreate([
                    'instructor_program_id' => $ip->id,
                    'person_id'             => $program_request->person_id,
                ]);
            }

            // 4) Antes del import: contar aprendices actuales del curso
            $beforeCount = Apprentice::where('course_id', $course->id)->count();

            // 5) Buscar doc de cargue masivo (el nombre guardado suele ser "Cargue Masivo - original.xlsx")
            $bulkDoc = ProgramRequestDocument::where('program_request_id', $program_request->id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%Cargue Masivo%')
                        ->orWhere('name', 'like', '%cargue masivo%')
                        ->orWhere('name', 'like', '%CARGUE MASIVO%');
                })
                ->orderByDesc('id')
                ->first();

            $imported = false;
            if ($bulkDoc) {
                // 🔴 IMPORTANTE: el doc está en disk public
                $filePath = Storage::disk('public')->path($bulkDoc->path);

                if (is_file($filePath)) {
                    $this->importApprenticesExcelToCourse($filePath, $course->id);
                    $imported = true;
                } else {
                    Log::warning('Cargue masivo no existe en ruta', [
                        'pr_id' => $program_request->id,
                        'path' => $bulkDoc->path,
                        'abs' => $filePath
                    ]);
                }
            }

            // 6) Después del import: contar aprendices
            $afterCount = Apprentice::where('course_id', $course->id)->count();

            DB::commit();

            // ✅ Enviar correo a instructor + solicitante (con fechas, conteo y link público)
            $this->notifyProgramRequest($program_request, 'confirmed', null, [
                'apprentices_count' => $afterCount,
                'course_code'       => $course->code,
            ]);

            $msg = $afterCount > 0
                ? "Caracterización confirmada. Curso {$course->code}: {$afterCount} aprendices registrados."
                : "Caracterización confirmada. Curso {$course->code}: no tiene aprendices registrados.";

            if ($imported) {
                $delta = $afterCount - $beforeCount;
                $msg .= " (Import: " . ($delta >= 0 ? "+{$delta}" : (string)$delta) . ")";
            }

            return redirect()
                ->route('sigac.support.programming.program_request.characterization.index')
                ->with('success', $msg);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('program_request_characterization_store error', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'Error en caracterización: ' . $e->getMessage());
        }
    }



    // Devolver solicitud
    public function program_request_characterization(Request $request)
    {
        $query = ProgramRequest::query()
            ->with([
                'person',
                'program',
                'special_program',
                'municipality.department',
                'village',
                'program_request_dates',
                'documents',
                'area',
                'budgetItem',
            ])
            ->where('state', 'Preconfirmado');

        // Apoyo puede tener uno o ambos roles
        $areas = [];
        if (function_exists('checkRol') && checkRol('gdf.campesena_support')) $areas[] = 1;
        if (function_exists('checkRol') && checkRol('gdf.academic_support'))  $areas[] = 2;

        if (!empty($areas)) {
            $query->whereIn('area_id', $areas);
        }

        $program_requests = $query->orderByDesc('id')->get();

        $titlePage = 'Caracterización';
        $titleView = 'Bandeja de Caracterización (Preconfirmadas)';

        return view('sigac::programming.program_request.characterization_index', compact(
            'program_requests',
            'titlePage',
            'titleView'
        ));
    }
    public function program_request_characterization_dismiss(Request $request, $id)
    {
        $request->validate([
            'observation' => 'required|string|max:5000',
        ]);

        try {
            DB::beginTransaction();

            $program_request = ProgramRequest::with(['person', 'program_request_dates'])->findOrFail($id);

            $program_request->observation = $request->input('observation');
            $program_request->state = 'Desestimado';
            $program_request->save();

            DB::commit();

            $this->notifyProgramRequest($program_request, 'dismissed', $program_request->observation);

            return redirect()
                ->route('sigac.support.programming.program_request.characterization.index')
                ->with('success', 'Solicitud desestimada y notificada por correo.');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error desestimando solicitud: ' . $e->getMessage());
            return back()->with('error', 'Error interno: ' . $e->getMessage());
        }
    }


    public function program_request_characterization_devolution(Request $request, $id)
    {
        $request->validate([
            'observation' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();
        try {
            // 🔴 IMPORTANTE: usa 'dates', NO 'program_request_dates'
            $program_request = ProgramRequest::with(['person', 'dates'])->findOrFail($id);

            $program_request->observation = $request->input('observation');
            $program_request->state = 'Devuelto';
            $program_request->save();

            DB::commit();

            // 📧 Correo a instructor y solicitante (con fechas)
            $this->notifyProgramRequest(
                $program_request,
                'returned',
                $program_request->observation
            );

            return redirect()
                ->route('sigac.support.programming.program_request.characterization.index')
                ->with('success', 'Solicitud devuelta y notificada por correo.');
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Error devolviendo solicitud', [
                'program_request_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Error interno: ' . $e->getMessage());
        }
    }


    // Novedad de programación
    public function management_programming_novelty(Request $request)
    {
        try {
            $instructor_program_id = $request->input('instructor_program_id');
            $activity = $request->input('activity');
            $observation = $request->input('observation');
            $option = $request->input('option');

            $date = InstructorProgram::where('id', $instructor_program_id)->pluck('date')->first();

            DB::beginTransaction();
            $instructor_program_novelty = new InstructorProgramNovelty;
            $instructor_program_novelty->instructor_program_id = $instructor_program_id;
            $instructor_program_novelty->date = $date;
            $instructor_program_novelty->activity = $activity;
            $instructor_program_novelty->observation = $observation;
            $instructor_program_novelty->save();

            if ($option == 'yes') {
                $instructor_program = InstructorProgram::findOrFail($instructor_program_id);
                $instructor_program->state = 'Cancelado';
                $instructor_program->save();
            }
            DB::commit();

            return redirect()->route('sigac.programming.index')->with('success', 'Novedad Enviada');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en el registro: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor', $e], 500);
        }
    }

    public function external_activities_index()
    {
        $external_activities = InstructorProgram::with('instructor_program_people.person', 'course')
            ->whereNotNull('activity_name')
            ->where('state', 'Pendiente')
            ->get()
            ->groupBy('activity_name');

        return view('sigac::programming.external_activities.index', [
            'titlePage' => 'Actividades externas',
            'titleView' => 'Actividades externas',
            'external_activities' => $external_activities
        ]);
    }

    public function external_activities_create()
    {
        $courses = Course::where('status', 'Activo')->where('deschooling', 'Presencial')->get();

        $course = $courses->map(function ($c) {
            $id = $c->id;
            $name = $c->code . ' - ' . $c->program->name;

            return [
                'id' => $id,
                'name' => $name
            ];
        });

        return view('sigac::programming.external_activities.create', [
            'titlePage' => 'Crear actividades externas',
            'titleView' => 'Crear actividades externas',
            'course' => $course
        ]);
    }

    public function external_activities_search_course(Request $request)
    {
        $name = $request->input('name');

        $courses = Course::where('status', 'Activo')
            ->where('deschooling', 'Presencial')
            ->whereHas('program', function ($query) use ($name) {
                $query->where('name', 'LIKE', '%' . $name . '%');
            })->get();

        $output = '';
        foreach ($courses as $course) {
            $output .= '<div class="form-check">';
            $output .= '<input type="checkbox" class="form-check-input courses" name="courses[]" value="' . $course->id . '">';
            $output .= '<label class="form-check-label">' . $course->code . ' - ' . $course->program->name . '</label>';
            $output .= '</div>';
        }

        return response()->json($output);
    }

    public function external_activities_search_person(Request $request)
    {
        $term = $request->get('term');
        $persons = Person::whereRaw("CONCAT(first_name, ' ', first_last_name, ' ', second_last_name) LIKE ?", ['%' . $term . '%'])->get();

        $results = [];
        foreach ($persons as $person) {
            $results[] = [
                'id' => $person->id,
                'text' => $person->full_name,
            ];
        }

        return response()->json($results);
    }

    public function external_activities_store(Request $request)
    {
        $courses = $request->courses;
        $date = $request->date;
        $start_time = $request->start_time;
        $end_time = $request->end_time;
        $activity_description = $request->description;
        $responsible = $request->responsible;
        $app_id = App::where('name', 'SIGAC')->pluck('id')->first();

        $user = Auth::user();

        $slug = Role::where('app_id', $app_id)
            ->whereHas('users', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->pluck('slug')->first();

        $roles = Str::replaceFirst('sigac.', '', $slug);

        $route = getRoleRouteName(Route::currentRouteName());
        try {

            DB::beginTransaction();

            if ($route == 'academic_coordination' || $roles == 'academic_coordinator') {
                foreach ($courses as $c) {
                    $instructor_program = new InstructorProgram;
                    $instructor_program->course_id = $c;
                    $instructor_program->activity_name = 'Coordinación Académica';
                    $instructor_program->activity_description = $activity_description;
                    $instructor_program->date = $date;
                    $instructor_program->start_time = $start_time;
                    $instructor_program->end_time = $end_time;
                    $instructor_program->state = 'Pendiente';
                    $instructor_program->save();

                    $instructor_program_people = new InstructorProgramPerson;
                    $instructor_program_people->instructor_program_id = $instructor_program->id;
                    $instructor_program_people->person_id = $responsible;
                    $instructor_program_people->save();
                }
            } else if ($route == 'wellness' || $roles == 'wellness') {
                foreach ($courses as $c) {
                    $instructor_program = new InstructorProgram;
                    $instructor_program->course_id = $c;
                    $instructor_program->activity_name = 'Bienestar';
                    $instructor_program->activity_description = $activity_description;
                    $instructor_program->date = $date;
                    $instructor_program->start_time = $start_time;
                    $instructor_program->end_time = $end_time;
                    $instructor_program->state = 'Pendiente';
                    $instructor_program->save();

                    $instructor_program_people = new InstructorProgramPerson;
                    $instructor_program_people->instructor_program_id = $instructor_program->id;
                    $instructor_program_people->person_id = $responsible;
                    $instructor_program_people->save();
                }
            }
            DB::commit();


            return redirect()->route('sigac.' . $route . '.programming.external_activities.index')->with('success', 'Actividad externa registrada exitosamente')->with('typealert', 'success');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with(['error' => 'Error interno del servidor.'], 500);
        }
    }

    public function approved_external_activities(Request $request)
    {
        $ids = $request->input('id'); // Puede ser un único ID o un array de IDs

        // Si es un solo ID, convertirlo en un array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        // Encontrar todas las actividades con esos IDs
        $instructorPrograms = InstructorProgram::whereIn('id', $ids)->get();

        foreach ($instructorPrograms as $i) {
            $i->state = 'Programado';
            $i->save(); // Guardar cada actividad actualizada
        }

        return redirect()->route('sigac.academic_coordination.programming.external_activities.index')->with('success', 'Actividad externa aprobada exitosamente')->with('typealert', 'success');
    }

    public function cancel_external_activities(Request $request)
    {
        $ids = $request->input('id'); // Puede ser un único ID o un array de IDs

        // Si es un solo ID, convertirlo en un array
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        // Encontrar todas las actividades con esos IDs
        $instructorPrograms = InstructorProgram::whereIn('id', $ids)->get();

        foreach ($instructorPrograms as $i) {
            $i->state = 'Cancelado';
            $i->save(); // Guardar cada actividad actualizada
        }

        return redirect()->route('sigac.academic_coordination.programming.external_activities.index')->with('success', 'Actividad externa no aprobada exitosamente')->with('typealert', 'success');
    }

    private function instructorBelongsToArea(int $personId, string $areaKey): bool
    {
        $areaId = $areaKey === 'campesena' ? 1 : 2; // ajusta si cambia

        return \DB::table('person_area_budget_assignments')
            ->where('person_id', $personId)
            ->where('area_id', $areaId)
            ->where('is_active', 1)
            ->exists();
    }


    private function huilaDepartmentId(): ?int
    {
        return Department::where('name', 'Huila')->value('id');
    }


    private function allowedSpecialProgramsFor(string $areaKey, int $personId = null)
    {
        // Base por área
        $q = SpecialProgram::query()->orderBy('name', 'asc');

        if ($areaKey === 'campesena') {
            $q->where('name', 'CAMPESENA');
        } else {
            $q->where('name', '!=', 'CAMPESENA');
        }

        // Hook: filtrar por instructor cuando exista tabla real (más tarde)
        // Ejemplo futuro: $q->whereExists(...) o join a tabla instructor_rubros

        return $q->get();
    }


    public function program_request_searchmunicipalities(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = Municipality::query()
            ->where('department_id', 421)
            ->when($q !== '', fn($qq) => $qq->where('name', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json([
            'results' => $rows->map(fn($r) => ['id' => $r->id, 'text' => $r->name]),
        ]);
    }

    /* ============================
    |  NUEVO: AJAX rubros/convenios por instructor + área
    |============================ */
    public function program_request_special_programs_for_instructor(Request $request)
    {
        $areaKey = $this->resolveAreaKeyFromRoute();


        $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
        ]);

        $personId = (int) $request->person_id;

        if (!$this->instructorBelongsToArea($personId, $areaKey)) {
            return response()->json(['special_programs' => [], 'message' => 'Instructor no habilitado en el área'], 200);
        }

        $specialPrograms = $this->allowedSpecialProgramsFor($areaKey, $personId)
            ->map(fn($sp) => ['id' => $sp->id, 'text' => $sp->name])
            ->values();

        return response()->json(['special_programs' => $specialPrograms]);
    }

    private function programsForInstructor(int $personId)
    {
        return Program::query()
            ->select('programs.id', 'programs.name', 'programs.sofia_code')
            ->join('competencies', 'competencies.program_id', '=', 'programs.id')
            ->join('learning_outcomes', 'learning_outcomes.competencie_id', '=', 'competencies.id')
            ->join('learning_outcome_people', 'learning_outcome_people.learning_outcome_id', '=', 'learning_outcomes.id')
            ->where('learning_outcome_people.person_id', $personId)
            ->distinct()
            ->orderBy('programs.sofia_code', 'asc')
            ->get()
            ->mapWithKeys(fn($p) => [$p->id => "{$p->name} - {$p->sofia_code}"]);
    }
    public function program_request_context(Request $request)
    {
        $request->validate([
            'person_id' => ['required', 'integer', 'exists:people,id'],
        ]);

        $areaKey = $this->resolveAreaKeyFromRoute();
        $areaId  = $this->resolveAreaIdOrFail($areaKey);

        $personId = (int) $request->person_id;

        if (!$this->isInstructorVigente($personId)) {
            return response()->json(['ok' => false, 'message' => 'Instructor no vigente'], 422);
        }

        if (!$this->instructorHasActiveAreaAssignment($personId, $areaId)) {
            return response()->json(['ok' => false, 'message' => 'Sin parametrización de área/rubro activa'], 422);
        }

        return response()->json([
            'ok' => true,
            'programs' => $this->programsForInstructor($personId),
            'rubros'   => $this->budgetItemsForInstructorArea($personId, $areaId),
        ]);
    }

    private function importApprenticesExcelToCourse(string $filePath, int $courseId): void
    {
        $sheet = Excel::toArray([], $filePath);
        $rows  = $sheet[0] ?? [];

        // Ajusta según tu plantilla: en tu import usas datos desde fila 4
        $dataRows = array_slice($rows, 4);

        $eps              = EPS::firstOrCreate(['name' => 'NO REGISTRA']);
        $population_group = PopulationGroup::firstOrCreate(['name' => 'NINGUNA']);
        $pension_entity   = PensionEntity::firstOrCreate(['name' => 'NO REGISTRA']);

        // ✅ Valores permitidos típicos para ENUM (ajusta si tu ENUM difiere)
        $allowedEnum = [
            'EN FORMACIÓN',
            'CERTIFICADO',
            'RETIRO VOLUNTARIO',
            'CANCELADO',
            'TRASLADADO',
            'APLAZADO',
            'INDUCCIÓN',
            'CONDICIONADO',
            'NO REGISTRA',
        ];

        foreach ($dataRows as $r) {
            // [0]=tipo_doc, [1]=documento, [2]=nombres, [3]=apellidos, [4]=tel, [5]=email, [6]=estado
            $doc = isset($r[1]) ? trim((string)$r[1]) : '';
            if ($doc === '') continue;

            // ✅ Si el doc trae puntos/espacios, límpialo
            $docClean = preg_replace('/\D+/', '', $doc);
            if (!$docClean) continue;

            $document_type_raw = strtoupper(trim((string)($r[0] ?? 'CC')));
            $document_type = match ($document_type_raw) {
                'CC' => 'Cédula de ciudadanía',
                'TI' => 'Tarjeta de identidad',
                'CE' => 'Cédula de extranjería',
                default => 'Cédula de ciudadanía'
            };

            $names     = strtoupper(trim((string)($r[2] ?? '')));
            $surnames  = strtoupper(trim((string)($r[3] ?? '')));
            $telephone = trim((string)($r[4] ?? ''));
            $email     = strtolower(trim((string)($r[5] ?? '')));

            // ✅ Estado crudo (puede venir vacío o mal)
            $rawStatus = trim((string)($r[6] ?? ''));

            $surnameParts = preg_split('/\s+/', $surnames) ?: [];
            $firstLast    = $surnameParts[0] ?? '';
            $secondLast   = trim(str_replace($firstLast, '', $surnames));

            // email attribute
            $attribute = 'personal_email';
            if ($email && str_contains($email, '@misena')) $attribute = 'misena_email';
            elseif ($email && str_contains($email, '@sena')) $attribute = 'sena_email';

            $person = Person::firstOrCreate(
                ['document_number' => (int)$docClean],
                [
                    'document_type'         => $document_type,
                    'first_name'            => $names ?: 'NO REGISTRA',
                    'first_last_name'       => $firstLast ?: 'NO REGISTRA',
                    'second_last_name'      => $secondLast ?: 'NO REGISTRA',
                    'telephone1'            => (int)(preg_replace('/\D+/', '', $telephone) ?: 0),
                    $attribute              => $email ?: null,
                    'eps_id'                => $eps->id,
                    'population_group_id'   => $population_group->id,
                    'pension_entity_id'     => $pension_entity->id,
                ]
            );

            // si ya existe, actualiza mínimos
            if ($email && empty($person->{$attribute})) {
                $person->{$attribute} = $email;
            }
            if ($telephone && empty($person->telephone1)) {
                $person->telephone1 = (int)(preg_replace('/\D+/', '', $telephone) ?: 0);
            }
            $person->save();

            // ✅ Normalizar y mapear estado a ENUM válido
            $status = $this->normalizeApprenticeStatus($rawStatus, $allowedEnum);

            Apprentice::firstOrCreate(
                ['person_id' => $person->id, 'course_id' => $courseId],
                ['apprentice_status' => $status]
            );
        }
    }


    private function normalizeApprenticeStatus(string $rawStatus, array $allowedEnum): string
    {
        $raw = trim($rawStatus);

        // si por error viene un email o algo con @
        if ($raw === '' || str_contains($raw, '@')) {
            return 'EN FORMACIÓN';
        }

        // normaliza sin tildes y en minúscula para comparar
        $norm = mb_strtolower($raw);
        $norm = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'n'],
            $norm
        );
        $norm = preg_replace('/\s+/', ' ', trim($norm));

        // mapeo de variantes comunes
        $map = [
            'activo'            => 'EN FORMACIÓN',
            'en formacion'      => 'EN FORMACIÓN',
            'en formación'      => 'EN FORMACIÓN',
            'formacion'         => 'EN FORMACIÓN',
            'formación'         => 'EN FORMACIÓN',
            'certificado'       => 'CERTIFICADO',
            'retirado'          => 'RETIRO VOLUNTARIO',
            'retiro'            => 'RETIRO VOLUNTARIO',
            'retiro voluntario' => 'RETIRO VOLUNTARIO',
            'cancelado'         => 'CANCELADO',
            'trasladado'        => 'TRASLADADO',
            'aplazado'          => 'APLAZADO',
            'induccion'         => 'INDUCCIÓN',
            'inducción'         => 'INDUCCIÓN',
            'condicionado'      => 'CONDICIONADO',
            'no registra'       => 'NO REGISTRA',
        ];

        $candidate = $map[$norm] ?? null;

        if ($candidate && in_array($candidate, $allowedEnum, true)) {
            return $candidate;
        }

        // Si el Excel ya trae algo igual al ENUM (ej: EN FORMACIÓN) respétalo
        $upper = mb_strtoupper($raw);
        if (in_array($upper, $allowedEnum, true)) {
            return $upper;
        }

        // fallback seguro
        return 'EN FORMACIÓN';
    }



    private function budgetItemsForInstructorArea(int $personId, int $areaId)
    {
        $today = Carbon::today()->toDateString();

        return DB::table('person_area_budget_assignments as pa')
            ->join('budget_items as bi', 'bi.id', '=', 'pa.budget_item_id')
            ->where('pa.person_id', $personId)
            ->where('pa.area_id', $areaId)
            ->where('pa.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('pa.start_date')->orWhereDate('pa.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('pa.end_date')->orWhereDate('pa.end_date', '>=', $today);
            })
            ->select([
                'bi.id',
                DB::raw("CONCAT(bi.code,' - ',bi.name) as name")
            ])
            ->orderBy('bi.name')
            ->get();
    }
    public function program_request_searchmunicipality(Request $request)
    {
        $term = trim((string) $request->get('q', ''));
        $departmentId = 421;

        $rows = Municipality::query()
            ->where('department_id', $departmentId)
            ->when($term !== '', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name']);

        return response()->json(
            $rows->map(fn($m) => ['id' => $m->id, 'text' => $m->name])->values()
        );
    }
    public function program_request_index(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);
        $personId = (int) $user->person->id;

        // 0) Guardas fuertes
        $canCreate   = true;
        $blockReason = null;

        if (!$this->isInstructorVigente($personId)) {
            $canCreate   = false;
            $blockReason = 'Tu usuario no tiene contrato vigente. No puedes realizar solicitudes.';
        }

        // 1) Áreas disponibles (NORMALIZA)
        $areaOptionsRaw = $this->availableAreasForInstructor($personId);

        // Normaliza opciones a keys en minúscula y sin espacios
        $areaOptions = array_values(array_filter(array_map(function ($opt) {
            // soporta opt como array o como objeto
            $key = null;
            if (is_array($opt) && isset($opt['key'])) $key = $opt['key'];
            if (is_object($opt) && isset($opt->key)) $key = $opt->key;

            $key = $key !== null ? trim(mb_strtolower((string)$key)) : null;
            if (!$key) return null;

            return ['key' => $key];
        }, $areaOptionsRaw)));

        if ($canCreate && empty($areaOptions)) {
            $canCreate   = false;
            $blockReason = 'No tienes un área asignada. Solicita a Coordinación/Apoyo que te asocien a un área y rubro.';
        }

        // Si está bloqueado, no revienta la vista
        if (!$canCreate) {
            $titlePage = 'Solicitud de programas';
            $titleView = 'Solicitud de programas';

            return view('sigac::programming.program_request.index', [
                'canCreate'            => $canCreate,
                'blockReason'          => $blockReason,
                'areaKey'              => null,
                'areaId'               => null,
                'areaOptions'          => [],
                'programs'             => collect(),
                'specialPrograms'      => collect(),
                'municipalities'       => collect(),
                'villageIds'           => collect(),
                'rubros'               => collect(),
                'companySuggestions'   => collect(),
                'applicantSuggestions' => collect(),
                'titlePage'            => $titlePage,
                'titleView'            => $titleView,
            ]);
        }

        // 2) Resolver área permitida (prioridad: query > ruta > fallback)
        $allowedKeys = array_values(array_unique(array_column($areaOptions, 'key')));

        $areaKey = null;

        $candidate = $request->filled('area')
            ? trim(mb_strtolower((string)$request->get('area')))
            : null;

        if ($candidate && in_array($candidate, $allowedKeys, true)) {
            $areaKey = $candidate;
        } else {
            $fromRoute = $this->resolveAreaKeyFromRoute(); // academic|campesena|null
            $fromRoute = $fromRoute ? trim(mb_strtolower((string)$fromRoute)) : null;

            if ($fromRoute && in_array($fromRoute, $allowedKeys, true)) {
                $areaKey = $fromRoute;
            } else {
                $areaKey = $allowedKeys[0] ?? null; // fallback real
            }
        }

        $areaId = $areaKey ? $this->resolveAreaIdOrFail($areaKey) : null; // 1 campesena / 2 academic

        // 3) Data vista
        $programs = DB::table('programs')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $specialPrograms = DB::table('special_programs')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $DEPT_ID = 421; // Huila (ajusta si aplica)
        $municipalities = DB::table('municipalities')
            ->select('id', 'name')
            ->where('department_id', $DEPT_ID)
            ->orderBy('name')
            ->get();

        $villageIds = DB::table('villages')
            ->select('id', 'name', 'municipality_id')
            ->whereIn('municipality_id', $municipalities->pluck('id'))
            ->orderBy('name')
            ->get();

        $today = now()->toDateString();

        $rubros = DB::table('person_area_budget_assignments as paba')
            ->join('budget_items as bi', 'bi.id', '=', 'paba.budget_item_id')
            ->where('paba.person_id', $personId)
            ->where('paba.area_id', (int)$areaId)   
            ->where('paba.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.start_date')
                    ->orWhereDate('paba.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('paba.end_date')
                    ->orWhereDate('paba.end_date', '>=', $today);
            })
            ->select('bi.id', DB::raw("COALESCE(bi.code,'') as code"), 'bi.name')
            ->distinct()
            ->orderBy('bi.name')
            ->get();


        $companySuggestions = DB::table('companies')
            ->select('id', 'name', 'nit')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $applicantSuggestions = DB::table('program_requests')
            ->whereNotNull('applicant')->where('applicant', '!=', '')
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('applicant')
            ->unique()
            ->values();

        $titlePage = 'Solicitud de programas';
        $titleView = 'Solicitud de programas';

        return view('sigac::programming.program_request.index', compact(
            'canCreate',
            'blockReason',
            'areaKey',
            'areaId',
            'areaOptions',
            'programs',
            'specialPrograms',
            'municipalities',
            'villageIds',
            'rubros',
            'companySuggestions',
            'applicantSuggestions',
            'titlePage',
            'titleView'
        ));
    }


    public function program_request_store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->person) abort(403);
        $personId = (int) $user->person->id;

        // Guardas fuertes
        if (!$this->isInstructorVigente($personId)) {
            return back()->withInput()->with('error', 'No tienes contrato vigente. No puedes crear solicitudes.');
        }

        $areaOptions = $this->availableAreasForInstructor($personId);
        if (empty($areaOptions)) {
            return back()->withInput()->with('error', 'No tienes un área asignada. No puedes crear solicitudes.');
        }

        // Resolver área SOLO desde opciones permitidas
        $allowedKeys = array_values(array_unique(array_column($areaOptions, 'key')));

        $areaKey = $this->resolveAreaKeyFromRoute();
        if (!in_array($areaKey, $allowedKeys, true)) {
            $areaKey = $allowedKeys[0];
        }

        if ($request->filled('area')) {
            $candidate = (string) $request->get('area');
            if (in_array($candidate, $allowedKeys, true)) {
                $areaKey = $candidate;
            }
        }

        $areaId = $this->resolveAreaIdOrFail($areaKey);

        // ✅ VALIDACIÓN MEJORADA
        $validated = $request->validate([
            'program_id'          => 'required|integer|exists:programs,id',
            'special_program_id'  => 'required|integer|exists:special_programs,id',
            'budget_item_id'      => 'required|integer|exists:budget_items,id',

            'hours'               => 'required|integer|min:1',
            'quotas'              => 'required|integer|min:1',

            'start_date'          => 'required|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',

            'municipality_id'     => 'required|integer|exists:municipalities,id',
            'place_type'          => 'required|in:municipio,vereda',
            'village_id'          => 'nullable|integer|exists:villages,id',

            'company_id'          => 'nullable|integer|exists:companies,id',
            'company_name'        => 'nullable|string|max:255',

            'address'             => 'nullable|string|max:255',
            'observation'         => 'nullable|string|max:5000',

            'applicant'           => 'nullable|string|max:255',
            'email'               => 'nullable|email|max:255',
            'telephone'           => 'nullable|string|max:50',

            // ✅ VALIDACIÓN CORRECTA para documents[]
            'documents'           => 'nullable|array',
            'documents.*'         => 'nullable|file|mimes:pdf,xlsx,xls,jpg,jpeg,png|max:10240',
            'document_types'      => 'nullable|array',
            'document_types.*'    => 'nullable|string|in:cedula,cargue_masivo,carta',

            'dates'               => 'required|array|min:1',
            'dates.*'             => 'required|date',
            'start_time'          => 'required|array|min:1',
            'start_time.*'        => 'required|date_format:H:i',
            'end_time'            => 'required|array|min:1',
            'end_time.*'          => 'required|date_format:H:i',
        ]);

        // 1) village_id
        $villageId = null;
        if ($validated['place_type'] === 'vereda') {
            $villageId = (int) ($validated['village_id'] ?? 0);

            if (!$villageId) {
                return back()->withInput()->with('error', 'Si el destino es vereda, debes escoger una vereda.');
            }

            $okVillage = DB::table('villages')
                ->where('id', $villageId)
                ->where('municipality_id', (int) $validated['municipality_id'])
                ->exists();

            if (!$okVillage) {
                return back()->withInput()->with('error', 'La vereda seleccionada no pertenece al municipio seleccionado.');
            }
        }

        // 2) rango fechas / horario
        $sd = Carbon::parse($validated['start_date'])->startOfDay();
        $ed = Carbon::parse($validated['end_date'] ?? $validated['start_date'])->endOfDay();

        $dates  = $validated['dates'];
        $starts = $validated['start_time'];
        $ends   = $validated['end_time'];

        if (count($dates) !== count($starts) || count($dates) !== count($ends)) {
            return back()->withInput()->with('error', 'Horario inválido: fechas y horas no coinciden en cantidad.');
        }

        foreach ($dates as $i => $d) {
            $day = Carbon::parse($d)->startOfDay();

            if ($day->lt($sd) || $day->gt($ed)) {
                return back()->withInput()->with('error', "La fecha {$d} está por fuera del rango Inicio/Fin.");
            }

            $st = $starts[$i];
            $et = $ends[$i];

            if ($et <= $st) {
                return back()->withInput()->with('error', "En {$d}: la hora fin debe ser mayor que la hora inicio.");
            }

            $conflict = DB::table('instructor_program_people as ipp')
                ->join('instructor_programs as ip', 'ip.id', '=', 'ipp.instructor_program_id')
                ->where('ipp.person_id', $personId)
                ->whereDate('ip.date', $d)
                ->where(function ($q) {
                    $q->whereNull('ip.state')
                        ->orWhereIn('ip.state', ['Programado', 'Pendiente']);
                })
                ->where(function ($q) use ($st, $et) {
                    $q->whereRaw('? < ip.end_time AND ? > ip.start_time', [$st, $et]);
                })
                ->exists();

            if ($conflict) {
                return back()->withInput()->with('error', "Choque de horario el {$d} ({$st}-{$et}) con la programación del instructor.");
            }
        }

        // 3) company_id
        $companyId = !empty($validated['company_id']) ? (int) $validated['company_id'] : null;
        $companyName = trim((string) ($validated['company_name'] ?? ''));

        if (!$companyId && $companyName !== '') {
            $existing = DB::table('companies')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($companyName)])
                ->value('id');

            $companyId = $existing ? (int) $existing : (int) DB::table('companies')->insertGetId([
                'name'       => $companyName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::beginTransaction();
        try {
            // Crear solicitud
            $prId = DB::table('program_requests')->insertGetId([
                'person_id'          => $personId,
                'area_id'            => $areaId,
                'budget_item_id'     => (int) $validated['budget_item_id'],
                'program_id'         => (int) $validated['program_id'],
                'special_program_id' => (int) $validated['special_program_id'],
                'company_id'         => $companyId,

                'municipality_id'    => (int) $validated['municipality_id'],
                'village_id'         => $villageId,
                'place_type'         => $validated['place_type'],

                'hours'              => (int) $validated['hours'],
                'start_date'         => $validated['start_date'],
                'end_date'           => $validated['end_date'] ?? null,
                'quotas'             => (int) $validated['quotas'],

                'address'            => $validated['address'] ?? null,
                'observation'        => $validated['observation'] ?? null,

                'applicant'          => $validated['applicant'] ?? null,
                'email'              => isset($validated['email']) ? strtolower(trim($validated['email'])) : null,
                'telephone'          => $validated['telephone'] ?? null,

                'state'              => 'Pendiente',
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);

            // Guardar fechas
            foreach ($validated['dates'] as $i => $date) {
                DB::table('program_request_dates')->insert([
                    'program_request_id' => $prId,
                    'date'       => $date,
                    'start_time' => $validated['start_time'][$i],
                    'end_time'   => $validated['end_time'][$i],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // ✅ GUARDAR DOCUMENTOS CORRECTAMENTE
            if ($request->hasFile('documents')) {
                $baseDir = "sigac/program_requests/{$prId}/documents";
                $files = $request->file('documents');
                $types = $request->input('document_types', []);

                foreach ($files as $index => $file) {
                    if (!$file || !$file->isValid()) {
                        Log::warning('SIGAC: archivo inválido en índice ' . $index, [
                            'program_request_id' => $prId,
                            'error' => $file?->getError(),
                        ]);
                        continue;
                    }

                    $original = $file->getClientOriginalName();
                    $safeBase = Str::slug(pathinfo($original, PATHINFO_FILENAME));
                    $ext = strtolower($file->getClientOriginalExtension());
                    $filename = $safeBase . '-' . now()->format('Ymd_His') . '-' . Str::random(6) . '.' . $ext;

                    $path = $file->storeAs($baseDir, $filename, 'public');

                    if (!$path) {
                        Log::error('SIGAC: no se pudo guardar archivo', [
                            'program_request_id' => $prId,
                            'original' => $original,
                            'disk' => 'public',
                            'baseDir' => $baseDir
                        ]);
                        continue;
                    }

                    // Determinar el tipo de documento
                    $docType = $types[$index] ?? 'documento';
                    $displayName = match ($docType) {
                        'cedula' => 'Cédula',
                        'cargue_masivo' => 'Cargue Masivo',
                        'carta' => 'Carta',
                        default => 'Documento'
                    };

                    // Guardar en BD
                    DB::table('program_request_documents')->insert([
                        'program_request_id' => $prId,
                        'name' => $displayName . ' - ' . $original,
                        'path' => $path,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    Log::info('SIGAC: documento guardado', [
                        'program_request_id' => $prId,
                        'file' => $filename,
                        'type' => $displayName,
                        'path' => $path
                    ]);
                }
            }

            DB::commit();

            $routeRole = getRoleRouteName(Route::currentRouteName());

            return redirect()
                ->route("sigac.{$routeRole}.programming.program_request.table")
                ->with('success', 'Solicitud creada correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('SIGAC: error guardando solicitud', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return back()->withInput()->with('error', 'Error guardando la solicitud: ' . $e->getMessage());
        }
    }



    private function availableAreasForInstructor(int $personId): array
    {
        $today = Carbon::today()->toDateString();

        $areaIds = DB::table('person_area_budget_assignments as pa')
            ->where('pa.person_id', $personId)
            ->where('pa.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('pa.start_date')->orWhereDate('pa.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('pa.end_date')->orWhereDate('pa.end_date', '>=', $today);
            })
            ->distinct()
            ->pluck('pa.area_id')
            ->map(fn($id) => (int)$id)
            ->values()
            ->all();

        $opts = [];
        foreach ($areaIds as $id) {
            // Key estable por ID (no por name)
            $opts[] = [
                'id'  => $id,
                'key' => ($id === 1) ? 'campesena' : 'academic', // ajusta si cambian IDs
            ];
        }

        // únicos por key
        $unique = [];
        foreach ($opts as $o) $unique[$o['key']] = $o;

        return array_values($unique);
    }

    private function resolveAreaKeyFromRoute(): string
    {
        return Route::is('sigac.campesena.*') ? 'campesena' : 'academic';
    }

    private function resolveAreaIdOrFail(string $areaKey): int
    {
        $map = [
            'campesena' => 1,
            'academic'  => 2,
        ];

        if (!isset($map[$areaKey])) {
            abort(403, "Área inválida: {$areaKey}");
        }
        return (int) $map[$areaKey];
    }


    private function isInstructorVigente(int $personId): bool
    {
        $today = Carbon::today()->toDateString();

        $isEmployee = Employee::query()
            ->where('person_id', $personId)
            ->where('state', 'Activo')
            ->exists();

        $isContractor = Contractor::query()
            ->where('person_id', $personId)
            ->where('state', 'Activo')
            ->whereDate('contract_start_date', '<=', $today)
            ->whereDate('contract_end_date', '>=', $today)
            ->exists();

        return $isEmployee || $isContractor;
    }

    private function instructorHasActiveAreaAssignment(int $personId, int $areaId): bool
    {
        $today = Carbon::today()->toDateString();

        return DB::table('person_area_budget_assignments as pa')
            ->where('pa.person_id', $personId)
            ->where('pa.area_id', $areaId)
            ->where('pa.is_active', 1)
            ->where(function ($q) use ($today) {
                $q->whereNull('pa.start_date')->orWhereDate('pa.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('pa.end_date')->orWhereDate('pa.end_date', '>=', $today);
            })
            ->exists();
    }
    public function program_request_dates_json($id)
    {
        $pr = ProgramRequest::with('dates')->findOrFail($id);

        $dates = $pr->dates->map(function ($d) {
            return [
                'date'       => (string) $d->date,
                'start_time' => (string) $d->start_time,
                'end_time'   => (string) $d->end_time,
            ];
        })->values();

        return response()->json([
            'ok'    => true,
            'id'    => $pr->id,
            'dates' => $dates,
        ]);
    }



    private function allowedAreaIdsForProgramRequestInbox(): array
    {
        $ids = [];

        // Coordinadores SIGAC (aval)
        if (checkRol('sigac.campesena')) {
            $ids[] = 1;
        }
        if (checkRol('sigac.academic_coordinator')) {
            $ids[] = 2;
        }

        // Apoyo (caracterización) basado en roles GDF
        if (checkRol('gdf.campesena_support')) {
            $ids[] = 1;
        }
        if (checkRol('gdf.academic_support')) {
            $ids[] = 2;
        }

        // Unificar
        $ids = array_values(array_unique($ids));

        return $ids;
    }

    private function authorizeCoordinatorArea(\Modules\SIGAC\Entities\ProgramRequest $pr): void
    {
        // Coordinación Académica solo área 2
        if (checkRol('sigac.academic_coordinator') && (int)$pr->area_id !== 2) {
            abort(403, 'No puedes aprobar solicitudes de otra área.');
        }

        // Campesena solo área 1
        if (checkRol('sigac.campesena') && (int)$pr->area_id !== 1) {
            abort(403, 'No puedes aprobar solicitudes de otra área.');
        }

        // Si es superadmin, lo dejamos pasar
        if (checkRol('superadmin')) {
            return;
        }

        // Si no es ninguno, no aprueba
        if (!checkRol('sigac.academic_coordinator') && !checkRol('sigac.campesena')) {
            abort(403);
        }
    }

    public function course_apprentices_count(Request $request)
    {
        $code = trim((string) $request->get('code_course', ''));
        if ($code === '') {
            return response()->json(['ok' => false, 'message' => 'code_course requerido'], 422);
        }

        $course = Course::where('code', $code)->first();
        if (!$course) {
            return response()->json([
                'ok' => true,
                'exists' => false,
                'message' => 'La ficha no existe como Course.',
                'count' => 0,
            ]);
        }

        $count = Apprentice::where('course_id', $course->id)->count();

        return response()->json([
            'ok' => true,
            'exists' => true,
            'course_id' => $course->id,
            'count' => $count,
            'message' => $count > 0 ? "Tiene {$count} aprendices registrados." : "No tiene aprendices registrados.",
        ]);
    }
    public function program_request_document_view(Request $request, $prId, $documentId)
    {
        if (!function_exists('checkRol') || !(
            checkRol('sigac.instructor') ||
            checkRol('sigac.academic_coordinator') ||
            checkRol('sigac.campesena') ||
            checkRol('gdf.academic_support') ||
            checkRol('gdf.campesena_support') ||
            checkRol('superadmin')
        )) abort(403);

        $pr  = ProgramRequest::findOrFail($prId);

        $doc = ProgramRequestDocument::where('id', $documentId)
            ->where('program_request_id', $pr->id)
            ->firstOrFail();

        if (function_exists('checkRol') && checkRol('sigac.instructor')) {
            $personId = (int) optional(auth()->user()->person)->id;
            if (!$personId || (int)$pr->person_id !== $personId) abort(403);
        }

        $disk = Storage::disk('public');

        if (!$doc->path || !$disk->exists($doc->path)) {
            return back()->with('error', 'El archivo no existe o fue movido.');
        }

        $absPath = $disk->path($doc->path);

        $filename = $doc->name ?: basename($doc->path);
        $filename = (string) Str::of($filename)->replace(['"', "\n", "\r"], '');

        $mime = function_exists('mime_content_type') ? @mime_content_type($absPath) : null;
        if (!$mime) $mime = $disk->mimeType($doc->path) ?: 'application/octet-stream';

        return response()->file($absPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }


    public function program_request_document_download(Request $request, $prId, $documentId)
    {
        if (!function_exists('checkRol') || !(
            checkRol('sigac.instructor') ||
            checkRol('sigac.academic_coordinator') ||
            checkRol('sigac.campesena') ||
            checkRol('gdf.academic_support') ||
            checkRol('gdf.campesena_support') ||
            checkRol('superadmin')
        )) abort(403);

        $pr  = ProgramRequest::findOrFail($prId);

        $doc = ProgramRequestDocument::where('id', $documentId)
            ->where('program_request_id', $pr->id)
            ->firstOrFail();

        if (function_exists('checkRol') && checkRol('sigac.instructor')) {
            $personId = (int) optional(auth()->user()->person)->id;
            if (!$personId || (int)$pr->person_id !== $personId) abort(403);
        }

        $disk = Storage::disk('public');

        if (!$doc->path || !$disk->exists($doc->path)) {
            return back()->with('error', 'El archivo no existe o fue movido.');
        }

        $absPath = $disk->path($doc->path);

        $filename = $doc->name ?: basename($doc->path);
        $filename = (string) Str::of($filename)->replace(['"', "\n", "\r"], '');

        return response()->download($absPath, $filename);
    }



    public function uploadDocuments(Request $request, $programRequestId)
    {
        return $this->program_request_document_store($request, $programRequestId);
    }
    public function program_request_download($id)
    {
        try {
            if (!function_exists('checkRol') || !(
                checkRol('gdf.academic_support') ||
                checkRol('gdf.campesena_support') ||
                checkRol('sigac.academic_coordinator') ||
                checkRol('sigac.campesena') ||
                checkRol('superadmin')
            )) {
                abort(403);
            }

            $pr = ProgramRequest::with('documents')->findOrFail($id);
            $docs = $pr->documents ?? collect();

            if ($docs->isEmpty()) {
                return back()->with('error', 'Esta solicitud no tiene documentos cargados.');
            }

            Storage::makeDirectory('tmp');
            $zipName = 'program_request_' . $pr->id . '_' . Str::random(8) . '.zip';
            $zipRelPath = 'tmp/' . $zipName;
            $zipAbsPath = storage_path('app/' . $zipRelPath);

            $zip = new \ZipArchive();
            if ($zip->open($zipAbsPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                return back()->with('error', 'No se pudo crear el ZIP.');
            }

            $added = 0;

            foreach ($docs as $doc) {
                $relPath = $doc->path; // relativo a disk public
                if (!$relPath || !Storage::disk('public')->exists($relPath)) continue;

                $absPath = Storage::disk('public')->path($relPath);
                if (!is_file($absPath)) continue;

                $nameInZip = $doc->name ?: basename($relPath);

                // Evitar duplicados en ZIP
                if ($zip->locateName($nameInZip) !== false) {
                    $nameInZip = pathinfo($nameInZip, PATHINFO_FILENAME)
                        . '_' . Str::random(4)
                        . '.' . pathinfo($nameInZip, PATHINFO_EXTENSION);
                }

                $zip->addFile($absPath, $nameInZip);
                $added++;
            }

            $zip->close();

            if ($added === 0 || !file_exists($zipAbsPath)) {
                @unlink($zipAbsPath);
                return back()->with('error', 'No se generó el ZIP (no hay archivos válidos para empaquetar).');
            }

            return response()->download($zipAbsPath)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            Log::error('program_request_download zip error', [
                'program_request_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', 'No fue posible generar el ZIP: ' . $e->getMessage());
        }
    }
    private function notifyProgramRequest(ProgramRequest $pr, string $type, ?string $note = null, array $extra = []): void
    {
        // Asegurar relaciones
        if (! $pr->relationLoaded('dates')) {
            $pr->load('dates', 'person', 'program');
        }

        // Instructor email
        $instructorEmail =
            $pr->person?->misena_email
            ?? $pr->person?->sena_email
            ?? $pr->person?->personal_email;

        // Solicitante (del formulario)
        $applicantEmail = $pr->email ? strtolower(trim($pr->email)) : null;

        // Link login
        $loginUrl = url('/login');

        // Link público (lista de inscritos)
        $publicUrl = null;
        if (!empty($pr->code_course)) {
            try {
                $publicUrl = route('sigac.public.course.apprentices', ['code' => $pr->code_course]);
            } catch (\Throwable $e) {
                // Si la ruta no existe aún, no rompemos el envío
                $publicUrl = null;
            }
        }

        // Fechas + horas
        $dates = $pr->dates
            ? $pr->dates->map(fn($d) => [
                'date'       => (string) $d->date,
                'start_time' => (string) $d->start_time,
                'end_time'   => (string) $d->end_time,
            ])->values()->all()
            : [];

        $payload = array_merge([
            'note'       => $note,
            'login_url'  => $loginUrl,
            'public_url' => $publicUrl,
            'dates'      => $dates,
            'type'       => $type,
        ], $extra);

        $bcc = $extra['bcc'] ?? [];

        // Enviar al instructor
        if ($instructorEmail && filter_var($instructorEmail, FILTER_VALIDATE_EMAIL)) {
            Mail::to($instructorEmail)
                ->bcc($bcc)
                ->send(new ProgramRequestStatusMail($pr, $type, $payload));
        }

        // Enviar al solicitante
        if ($applicantEmail && filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
            Mail::to($applicantEmail)
                ->bcc($bcc)
                ->send(new ProgramRequestStatusMail($pr, $type, $payload));
        }
    }

    public function publicCourseApprentices($code)
    {
        $course = Course::where('code', $code)->firstOrFail();

        $apprentices = Apprentice::with('person')
            ->where('course_id', $course->id)
            ->orderBy('id')
            ->get();

        return view('sigac::programming.program_request.course_apprentices', compact('course', 'apprentices'));
    }


    public function program_request_excel_template()
    {
        // Roles permitidos (ajusta si quieres)
        if (!function_exists('checkRol') || !(
            checkRol('sigac.instructor') ||
            checkRol('sigac.academic_coordinator') ||
            checkRol('sigac.campesena') ||
            checkRol('sigac.wellness') ||
            checkRol('sigac.apprentice') ||
            checkRol('gdf.academic_support') ||
            checkRol('gdf.campesena_support') ||
            checkRol('superadmin')
        )) {
            abort(403);
        }

        // En storage/app/templates/...
        $disk = 'local';
        $path = 'templates/program_request_template.xlsx';

        if (!Storage::disk($disk)->exists($path)) {
            return back()->with('error', 'La plantilla Excel no existe en storage/app/templates (templates/program_request_template.xlsx).');
        }

        $filename = 'plantilla_solicitud_programacion.xlsx';

        // ✅ Más robusto que response()->download(path...)
        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function downloadApprenticesTemplate()
    {
        // Roles permitidos (ajusta)
        if (!function_exists('checkRol') || !(
            checkRol('sigac.instructor') ||
            checkRol('sigac.academic_coordinator') ||
            checkRol('sigac.campesena') ||
            checkRol('gdf.academic_support') ||
            checkRol('gdf.campesena_support') ||
            checkRol('superadmin')
        )) {
            abort(403);
        }

        // En storage/app/templates/...
        $disk = 'local';
        $path = 'templates/apprentices_template.xlsx';

        if (!Storage::disk($disk)->exists($path)) {
            return back()->with('error', 'La plantilla de aprendices no existe en storage/app/templates (templates/apprentices_template.xlsx).');
        }

        $filename = 'plantilla_cargue_masivo_aprendices.xlsx';

        return Storage::disk($disk)->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
