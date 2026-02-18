<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

use Modules\SICA\Entities\Contractor;
use Modules\SICA\Entities\EmployeeType;
use Modules\SICA\Entities\ContractorType;
use Modules\SICA\Entities\InsurerEntity;

class ContractorsController extends Controller
{
    /**
     * Mostrar vista de contratistas
     */
    public function viewcontractor()
    {
        try {
            $contractor = Contractor::query()
                ->with([
                    'person',
                    'contractor_type',
                    'insurer_entity',
                    'warehouse',
                    'supervisor',

                    // ✅ ESTO ES LO QUE TE FALTA PARA LA VISTA
                    // Si existe la relación contractDetail() en el modelo Contractor
                    'contractDetail',
                ])
                ->orderBy('contract_start_date', 'desc')
                ->get();

            $contractorTypes = ContractorType::orderBy('name')->get();
            $insurerEntitys  = InsurerEntity::orderBy('name')->get();
            $employeeTypes   = EmployeeType::orderBy('name')->get();

            return view('gth::contracts.contractors', compact(
                'contractor',
                'contractorTypes',
                'insurerEntitys',
                'employeeTypes'
            ));
        } catch (\Exception $e) {
            Log::error('Error en viewcontractor: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()->with(
                'error',
                'Error al cargar los contratistas: ' . $e->getMessage()
            );
        }
    }

    /**
     * Crear nuevo contratista
     */
    public function postcreatecontractor(Request $request)
    {
        try {
            $request->validate([
                'person_id'            => 'required|exists:persons,id',
                'contract_number'      => 'required|string|max:255',
                'contract_date'        => 'required|date',
                'contract_start_date'  => 'required|date',
                'contract_end_date'    => 'required|date|after:contract_start_date',
                'contractor_type_id'   => 'required|exists:contractor_types,id',
                'employee_type_id'     => 'required|exists:employee_types,id',
            ]);

            $contractor = new Contractor();
            $contractor->person_id              = $request->input('person_id');
            $contractor->contract_number        = $request->input('contract_number');
            $contractor->contract_date          = $request->input('contract_date');
            $contractor->contract_start_date    = $request->input('contract_start_date');
            $contractor->contract_end_date      = $request->input('contract_end_date');
            $contractor->total_contract_value   = $request->input('total_contract_value');
            $contractor->contractor_type_id     = $request->input('contractor_type_id');
            $contractor->contract_object        = $request->input('contract_object');
            $contractor->contract_obligations   = $request->input('contract_obligations');
            $contractor->amount_hours           = $request->input('amount_hours');
            $contractor->assigment_value        = $request->input('assigment_value');
            $contractor->employee_type_id       = $request->input('employee_type_id');
            $contractor->SIIF_code              = $request->input('SIIF_code');
            $contractor->insurer_entity_id      = $request->input('insurer_entity_id');
            $contractor->policy_number          = $request->input('policy_number');
            $contractor->policy_issue_date      = $request->input('policy_issue_date');
            $contractor->policy_effective_date  = $request->input('policy_effective_date');
            $contractor->policy_expiration_date = $request->input('policy_expiration_date');
            $contractor->policy_approval_date   = $request->input('policy_approval_date');
            $contractor->risk_type              = $request->input('risk_type');
            $contractor->state                  = $request->input('state', 'Activo');
            $contractor->save();

            return redirect()
                ->route('gth.admin.contractors.index')
                ->with('success', trans('gth::menu.The contract has been successfully created.'));
        } catch (\Exception $e) {
            Log::error('Error en postcreatecontractor: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error al crear el contrato: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Actualizar contratista
     */
    public function updatecontractor(Request $request, $id)
    {
        try {
            Log::info('Actualizando contrato ID: ' . $id, $request->all());

            $request->validate([
                'contract_number'      => 'required|string|max:255',
                'contract_date'        => 'required|date',
                'contract_start_date'  => 'required|date',
                'contract_end_date'    => 'required|date|after:contract_start_date',
                'contractor_type_id'   => 'required|exists:contractor_types,id',
                'employee_type_id'     => 'required|exists:employee_types,id',
            ]);

            $contractor = Contractor::findOrFail($id);

            $contractor->contract_number        = $request->input('contract_number');
            $contractor->contract_date          = $request->input('contract_date');
            $contractor->contract_start_date    = $request->input('contract_start_date');
            $contractor->contract_end_date      = $request->input('contract_end_date');
            $contractor->total_contract_value   = $request->input('total_contract_value');
            $contractor->contractor_type_id     = $request->input('contractor_type_id');
            $contractor->contract_object        = $request->input('contract_object');
            $contractor->contract_obligations   = $request->input('contract_obligations');
            $contractor->amount_hours           = $request->input('amount_hours');
            $contractor->assigment_value        = $request->input('assigment_value');
            $contractor->employee_type_id       = $request->input('employee_type_id');
            $contractor->SIIF_code              = $request->input('SIIF_code');
            $contractor->insurer_entity_id      = $request->input('insurer_entity_id');
            $contractor->policy_number          = $request->input('policy_number');
            $contractor->policy_issue_date      = $request->input('policy_issue_date');
            $contractor->policy_approval_date   = $request->input('policy_approval_date');
            $contractor->policy_effective_date  = $request->input('policy_effective_date');
            $contractor->policy_expiration_date = $request->input('policy_expiration_date');
            $contractor->risk_type              = $request->input('risk_type');
            $contractor->state                  = $request->input('state');

            $contractor->save();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => trans('gth::menu.The contract has been successfully updated.')
                ]);
            }

            return redirect()
                ->route('gth.admin.contractors.index')
                ->with('success', trans('gth::menu.The contract has been successfully updated.'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Error de validación en updatecontractor: ' . json_encode($e->errors()));

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de validación',
                    'errors'  => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->with('error', 'Error de validación')
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Error en updatecontractor: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el contrato: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Error al actualizar el contrato: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Mostrar un contratista específico
     */
    public function showContractor($id)
    {
        try {
            $contractor = Contractor::with([
                'person',
                'contractor_type',
                'employee_type',
                'insurer_entity',
                'supervisor',
                'warehouse',

                // ✅ también acá
                'contractDetail',
            ])->findOrFail($id);

            if (request()->ajax()) {
                return response()->json($contractor);
            }

            return view('gth::contracts.show', compact('contractor'));
        } catch (\Exception $e) {
            Log::error('Error en showContractor: ' . $e->getMessage());

            if (request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al cargar el contrato'
                ], 404);
            }

            return redirect()
                ->route('gth.admin.contractors.index')
                ->with('error', 'Contrato no encontrado');
        }
    }

    /**
     * Eliminar contratista (soft delete)
     */
    public function deleteContractor($id)
    {
        try {
            $contractor = Contractor::findOrFail($id);
            $contractor->delete();

            return redirect()
                ->route('gth.admin.contractors.index')
                ->with('success', trans('gth::menu.Contractor type correctly eliminated.'));
        } catch (\Exception $e) {
            Log::error('Error en deleteContractor: ' . $e->getMessage());

            return redirect()
                ->route('gth.admin.contractors.index')
                ->with('error', trans('gth::menu.The contractor type could not be deleted.'));
        }
    }
}
