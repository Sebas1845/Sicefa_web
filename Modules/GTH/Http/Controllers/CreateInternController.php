<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Warehouse;
use Modules\SICA\Entities\Program;
use Modules\SICA\Entities\ContractorType;
use Modules\SICA\Entities\EmployeeType;
use Modules\SICA\Entities\EPS;
use Modules\SICA\Entities\PopulationGroup;
use Modules\SICA\Entities\PensionEntity;
use Modules\SIGAC\Entities\Intern;
use Illuminate\Support\Facades\DB;

class CreateInternController extends Controller
{
    public function create()
    {
        $warehouses = Warehouse::select('id', 'name')
            ->orderBy('name')
            ->get();

        $programs = Program::select('id', 'name')
            ->orderBy('name')
            ->get();

        $contractorTypes = ContractorType::select('id', 'name')
            ->orderBy('name')
            ->get();

        $employeeTypes = EmployeeType::select('id', 'name')
            ->orderBy('name')
            ->get();

        $epsList = EPS::select('id', 'name')
            ->orderBy('name')
            ->get();

        $populationGroups = PopulationGroup::select('id', 'name')
            ->orderBy('name')
            ->get();

        $pensionEntities = PensionEntity::select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('gth::contractualcertificate.createinters', compact(
            'warehouses', 
            'programs', 
            'contractorTypes', 
            'employeeTypes',
            'epsList',
            'populationGroups',
            'pensionEntities'
        ));
    }

    public function store(Request $request)
    {
        $tableName = (new EPS())->getTable();
        
        $request->validate([
            'first_name' => 'required|string|max:255',
            'first_last_name' => 'required|string|max:255',
            'second_last_name' => 'nullable|string|max:255',
            'document_number' => 'required|string|max:20|unique:people,document_number',
            'telephone1' => 'required|string|max:20',
            'eps_id' => 'required|exists:' . $tableName . ',id',
            'population_group_id' => 'required|exists:population_groups,id',
            'pension_entity_id' => 'required|exists:pension_entities,id',
            'supervisor_id' => 'required|exists:people,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'program_id' => 'nullable|exists:programs,id',
            'contractor_type_id' => 'required|exists:contractor_types,id',
            'employee_type_id' => 'required|exists:employee_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'contract_number' => 'nullable|string|max:50',
        ]);

        DB::beginTransaction();

        $person = Person::create([
            'first_name' => $request->first_name,
            'first_last_name' => $request->first_last_name,
            'second_last_name' => $request->second_last_name,
            'document_number' => $request->document_number,
            'telephone1' => $request->telephone1,
            'eps_id' => $request->eps_id,
            'population_group_id' => $request->population_group_id,
            'pension_entity_id' => $request->pension_entity_id,
        ]);

        $warehouse = Warehouse::find($request->warehouse_id);
        
        Intern::create([
            'person_id' => $person->id,
            'assigned_supervisor_id' => $request->supervisor_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'assigned_area' => $warehouse->name,
        ]);

        DB::commit();

        return redirect()->route('gth.admin.interns.index')
            ->with('success', 'Pasante creado exitosamente');
    }
}