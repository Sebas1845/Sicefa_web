<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Course;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function viewattendance()
{
    $courses = Course::with('program')->get();

    // Inicializa un array para almacenar los nombres y códigos de los programas
    $selectData = [];

    // Recorre la colección de cursos
    foreach ($courses as $course) {
        // Obtén el nombre y código del curso y programa relacionado
        $name = $course->name . ' ' . $course->code;
        $programName = $course->program->name;

        // Concatena el nombre del curso con el nombre del programa
        $fullName = $name . ' - ' . $programName;

        // Agrega un array asociativo con el ID y el nombre completo al array de datos
        $selectData[$course->id] =  $fullName;
    }

    return view('gth::attendances', [
        'selectprogram' => $selectData,
    ]);
}
public function search(Request $request)
{
    $courseid = $request->input('courseid');

    // Obtén la información del curso
    $course = Course::with('apprentices.person')->findOrFail($courseid);
    

    // Obtén la fecha y hora actual
    $currentDateOnly = Carbon::today()->toDateString();

    // Obtén las asistencias de los aprendices para la fecha actual
    $attendances = $course->apprentices->map(function ($apprentice) use ($currentDateOnly) {
        return [
            'person' => $apprentice->person,
            'attendance' => $apprentice->person->attendances->firstWhere('date', $currentDateOnly),
        ];
    });
   

    return view('gth::resultattendance', [
        'attendances' => $attendances,
    ]);
}
}





//otro controlador 

<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Modules\SIGAC\Entities\Attendance;
use Modules\SICA\Entities\Person;

class AttendanceReportController extends Controller
{
    public function viewattendancereport()
    {
        $currentDateOnly = Carbon::today()->toDateString();

        $attendances = Attendance::with('person.employees.employee_type')->where('date', $currentDateOnly)->get();

        return view('gth::attendance_report.attendancereport', ['attendances' => $attendances, 'date', $currentDateOnly]);

    }
}



//otro controlador 
<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Carbon\Carbon;
use Modules\SIGAC\Entities\Attendance;
use Modules\SICA\Entities\Person;

class AttendanceReportController extends Controller
{
    public function viewattendancereport()
    {
        $currentDateOnly = Carbon::today()->toDateString();

        $attendances = Attendance::with('person.employees.employee_type')->where('date', $currentDateOnly)->get();

        return view('gth::attendance_report.attendancereport', ['attendances' => $attendances, 'date', $currentDateOnly]);

    }
}



//otro controlador 
<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Contractor;
use Modules\SICA\Entities\EmployeeType;
use Modules\SICA\Entities\ContractorType;
use Modules\SICA\Entities\InsurerEntity;

class ContractorsController extends Controller
{
    //Funcion mostrar vista tipo de empleado
    public function viewcontractor()
    {
        $contractor = Contractor::get();
        $employeeTypes = EmployeeType::all();
        $contractorTypes = ContractorType::all();
        $insurerEntitys = InsurerEntity::all();
        return view('gth::contracts.contractors', ['contractor' => $contractor, 'employeeTypes' => $employeeTypes, 'contractorTypes' => $contractorTypes, 'insurerEntitys' => $insurerEntitys]);
    }


    public function postcreatecontractor(Request $request)
    {
        $contractor = new Contractor;
        $contractor->name = $request->input('name');
        $contractor->save();

        return redirect()->route('gth.admin.contractors.index');
    }


    public function updatecontractor(Request $request, $id)
    {
        $contractor = Contractor::findOrFail($id);
        $contractor->contract_number = $request->input('contract_number');
        $contractor->contract_year = $request->input('contract_year');
        $contractor->contract_start_date = $request->input('contract_start_date');
        $contractor->contract_end_date = $request->input('contract_end_date');
        $contractor->total_contract_value = $request->input('total_contract_value');
        $contractor->contractor_type_id = $request->input('contractor_type_id');
        $contractor->contract_object = $request->input('contract_object');
        $contractor->contract_obligations = $request->input('contract_obligations');
        $contractor->amount_hours = $request->input('amount_hours');
        $contractor->assigment_value = $request->input('assigment_value');
        $contractor->sesion = $request->input('sesion');
        $contractor->sesion_date = $request->input('sesion_date');
        $contractor->employee_type_id = $request->input('employee_type_id');
        $contractor->SIIF_code = $request->input('SIIF_code');
        $contractor->insurer_entity_id = $request->input('insurer_entity_id');
        $contractor->policy_number = $request->input('policy_number');
        $contractor->policy_issue_date = $request->input('policy_issue_date');
        $contractor->policy_effective_date = $request->input('policy_effective_date');
        $contractor->policy_expiration_date = $request->input('policy_expiration_date');
        $contractor->risk_type = $request->input('risk_type');
        $contractor->state = $request->input('state');

        if ($contractor->save()) {
            return redirect()->route('gth.admin.contractors.index')->with('success', trans('gth::menu.The contract has been successfully updated.'));
        } else {
            return redirect()->black()->with('error', trans('gth::menu.Error updating vacancy'));
        }
        ;


    }

    public function showContractor($id)
    {
        $contractor = Contractor::find($id);
        return view('contracts.contractors', ['contractor' => $contractor]);
    }


    public function deleteContractor($id)
    {
        try {
            $contractor = Contractor::findOrFail($id);
            $contractor->delete();

            return redirect()->route('gth.admin.contractors.index')->with('success', trans('gth::menu.Contractor type correctly eliminated.'));
        } catch (\Exception $e) {
            return redirect()->route('gth.admin.contractors.index')->with('error', trans('gth::menu.The contractor type could not be deleted.'));
        }
    }
}


//otro controlador
<?php

namespace Modules\GTH\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Person;
use Modules\SIGAC\Entities\Attendance;

class RegisterAttendanceController extends Controller
{
    public function registerattendance(Request $request)
    {
        $documentNumber = $request->input('document_number');
        $date = Carbon::now()->toDateString();

        $person = Person::where('document_number', $documentNumber)->first();

        if (!$person) {
            return redirect()->back()->with('error', 'Persona no encontrada');
        }

        $attendance = Attendance::where('person_id', $person->id)->where('date', $date)->first();

        if (!$attendance) {
            $attendanceNew = new Attendance;

            $attendanceNew->date = $date;
            $attendanceNew->person_id = $person->id;
            $attendanceNew->entry_time = Carbon::now(); // Agrega el tiempo de entrada
            $attendanceNew->exit_time = null; // Inicializa el tiempo de salida como nulo
            $attendanceNew->save();

            return redirect()->back()->with('success', 'Entrada registrada correctamente');
        } elseif ($attendance->entry_time && !$attendance->exit_time) {
            // Verificar si ya se registró la entrada pero no la salida
            $attendance->exit_time = Carbon::now(); // Agrega el tiempo de salida
            $attendance->save();

            return redirect()->back()->with('success', 'Salida registrada correctamente');
        } else {
            return redirect()->back()->with('error', 'Ya tiene asistencia completa');
        }
    }
}







