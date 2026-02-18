<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Contractor;
use Modules\GTH\Entities\ContractDetail;
use Modules\SICA\Entities\ContractorType;
use Modules\SICA\Entities\EmployeeType;
use Modules\SICA\Entities\InsurerEntity;
use Modules\SICA\Entities\Person;
use Carbon\Carbon;

class ContractReportController extends Controller
{
    private $personId;

    /**
     * Vista principal de reportes de contrato
     */
    public function viewcontractreports()
    {
        try {
            $contractorTypes = ContractorType::all();
            $employeeTypes = EmployeeType::all();
            $insurerEntity = InsurerEntity::all();

            // Obtener una lista de contratos con la información de la persona y detalles
            $contracts = Contractor::with(['person', 'contractDetail'])
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'desc')
                ->get();

            return view('gth::contract_report.contractreports', compact(
                'contractorTypes', 
                'employeeTypes', 
                'insurerEntity',
                'contracts'
            ));

        } catch (\Exception $e) {
            Log::error('Error al cargar vista de contratos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error al cargar la página: ' . $e->getMessage());
        }
    }

    /**
     * Crear un nuevo contrato
     */
    public function create(Request $request)
    {
        // Log inicial para debugging
        Log::info('📋 Iniciando creación de contrato', [
            'datos_recibidos' => $request->all(),
            'ip' => $request->ip(),
            'usuario' => auth()->id() ?? 'No autenticado',
            'timestamp' => now()->toDateTimeString()
        ]);

        // VALIDACIÓN COMPLETA
        try {
            $validated = $request->validate([
                // Información Personal
                'person_id' => 'required|exists:people,id',
                'document_number' => 'required|numeric',
                
                // Información del Supervisor
                'supervisor_id' => 'required|exists:people,id',
                'document_number_supervisor' => 'required|numeric',
                
                // Detalles del Contrato - Fechas
                'contract_number' => 'required|string|max:255',
                'contract_date' => 'required|date',
                'contract_start_date' => 'required|date',
                'contract_end_date' => 'nullable|date|after_or_equal:contract_start_date',
                
                // Tipos y Valores
                'contractor_type_id' => 'required|exists:contractor_types,id',
                'employee_type_id' => 'required|exists:employee_types,id',
                'amount_hours' => 'required|numeric|min:1',
                'total_contract_value' => 'required|numeric|min:0',
                
                // Póliza
                'policy_issue_date' => 'required|date',
                'policy_approval_date' => 'required|date',
                'policy_effective_date' => 'required|date',
                'policy_expiration_date' => 'required|date|after:policy_effective_date',
                'policy_number' => 'nullable|string|max:255',
                'risk_type' => 'required|in:I,II,III,IV,V',
                
                // Estado y Asignación
                'state' => 'required|in:Activo,Inactivo',
                'SIIF_code' => 'required|numeric',
                'assigment_value' => 'required|numeric|min:0',
                'insurer_entity_id' => 'required|exists:insurer_entities,id',
                
                // Textos
                'contract_object' => 'required|string|min:10',
                'contract_obligations' => 'required|string|min:10',
            ], [
                // Mensajes de validación personalizados
                'person_id.required' => 'Debe buscar y seleccionar una persona válida',
                'person_id.exists' => 'La persona seleccionada no existe en el sistema',
                'supervisor_id.required' => 'Debe buscar y seleccionar un supervisor válido',
                'supervisor_id.exists' => 'El supervisor seleccionado no existe en el sistema',
                
                'contract_number.required' => 'El número de contrato es obligatorio',
                'contract_number.max' => 'El número de contrato no puede exceder 255 caracteres',
                
                'contract_date.required' => 'La fecha del contrato es obligatoria',
                'contract_date.date' => 'La fecha del contrato debe ser una fecha válida',
                
                'contract_start_date.required' => 'La fecha de inicio del contrato es obligatoria',
                'contract_start_date.date' => 'La fecha de inicio debe ser una fecha válida',
                
                'contract_end_date.date' => 'La fecha de fin debe ser una fecha válida',
                'contract_end_date.after_or_equal' => 'La fecha de fin debe ser posterior o igual a la fecha de inicio',
                
                'contractor_type_id.required' => 'Debe seleccionar el tipo de contrato',
                'contractor_type_id.exists' => 'El tipo de contrato seleccionado no existe',
                
                'employee_type_id.required' => 'Debe seleccionar el tipo de empleado',
                'employee_type_id.exists' => 'El tipo de empleado seleccionado no existe',
                
                'amount_hours.required' => 'Las horas de trabajo son obligatorias',
                'amount_hours.numeric' => 'Las horas deben ser un valor numérico',
                'amount_hours.min' => 'Las horas deben ser al menos 1',
                
                'total_contract_value.required' => 'El valor total del contrato es obligatorio',
                'total_contract_value.numeric' => 'El valor total debe ser numérico',
                'total_contract_value.min' => 'El valor total no puede ser negativo',
                
                'policy_issue_date.required' => 'La fecha de emisión de la póliza es obligatoria',
                'policy_approval_date.required' => 'La fecha de aprobación de la póliza es obligatoria',
                'policy_effective_date.required' => 'La fecha efectiva de la póliza es obligatoria',
                'policy_expiration_date.required' => 'La fecha de vencimiento de la póliza es obligatoria',
                'policy_expiration_date.after' => 'La fecha de vencimiento debe ser posterior a la fecha efectiva',
                
                'risk_type.required' => 'Debe seleccionar el tipo de riesgo',
                'risk_type.in' => 'El tipo de riesgo debe ser I, II, III, IV o V',
                
                'state.required' => 'Debe seleccionar el estado del contrato',
                'state.in' => 'El estado debe ser Activo o Inactivo',
                
                'SIIF_code.required' => 'El código SIIF es obligatorio',
                'SIIF_code.numeric' => 'El código SIIF debe ser numérico',
                
                'assigment_value.required' => 'El valor de asignación es obligatorio',
                'assigment_value.numeric' => 'El valor de asignación debe ser numérico',
                'assigment_value.min' => 'El valor de asignación no puede ser negativo',
                
                'insurer_entity_id.required' => 'Debe seleccionar la entidad aseguradora',
                'insurer_entity_id.exists' => 'La entidad aseguradora seleccionada no existe',
                
                'contract_object.required' => 'El objeto del contrato es obligatorio',
                'contract_object.min' => 'El objeto del contrato debe tener al menos 10 caracteres',
                
                'contract_obligations.required' => 'Las obligaciones del contrato son obligatorias',
                'contract_obligations.min' => 'Las obligaciones deben tener al menos 10 caracteres',
            ]);

            Log::info('✅ Validación exitosa', [
                'campos_validados' => array_keys($validated)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación', [
                'errores' => $e->errors(),
                'datos_enviados' => $request->all()
            ]);
            
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Por favor corrija los errores en el formulario');
        }

        // INICIAR TRANSACCIÓN
        DB::beginTransaction();
        
        try {
            // Extraer el año de la fecha de inicio del contrato
            $contractYear = Carbon::parse($validated['contract_start_date'])->year;

            Log::info('📅 Año del contrato extraído', ['año' => $contractYear]);

            // Verificar que no exista un contrato duplicado
            $existingContract = Contractor::where('contract_number', $validated['contract_number'])
                ->where('person_id', $validated['person_id'])
                ->whereNull('deleted_at')
                ->first();

            if ($existingContract) {
                DB::rollBack();
                
                Log::warning('⚠️ Contrato duplicado detectado', [
                    'contract_number' => $validated['contract_number'],
                    'person_id' => $validated['person_id'],
                    'existing_contract_id' => $existingContract->id
                ]);

                return redirect()->back()
                    ->withInput()
                    ->with('error', 'Ya existe un contrato con el número "' . $validated['contract_number'] . '" para esta persona');
            }

            // Crear el registro principal en contractors
            $contractorData = [
                'person_id' => $validated['person_id'],
                'supervisor_id' => $validated['supervisor_id'],
                'contract_number' => $validated['contract_number'],
                'contract_year' => $contractYear,
                'contract_start_date' => $validated['contract_start_date'],
                'contract_end_date' => $validated['contract_end_date'],
                'total_contract_value' => $validated['total_contract_value'],
                'contractor_type_id' => $validated['contractor_type_id'],
                'contract_object' => $validated['contract_object'],
                'contract_obligations' => $validated['contract_obligations'],
                'amount_hours' => $validated['amount_hours'],
                'assigment_value' => $validated['assigment_value'],
                'employee_type_id' => $validated['employee_type_id'],
                'SIIF_code' => $validated['SIIF_code'],
                'insurer_entity_id' => $validated['insurer_entity_id'],
                'policy_number' => $validated['policy_number'] ?? null,
                'policy_issue_date' => $validated['policy_issue_date'],
                'policy_approval_date' => $validated['policy_approval_date'],
                'policy_effective_date' => $validated['policy_effective_date'],
                'policy_expiration_date' => $validated['policy_expiration_date'],
                'risk_type' => $validated['risk_type'],
                'state' => $validated['state'],
            ];

            Log::info('💾 Intentando guardar Contractor', [
                'datos' => $contractorData
            ]);

            $contractor = Contractor::create($contractorData);

            Log::info('✅ Contractor guardado exitosamente', [
                'id' => $contractor->id,
                'contract_number' => $contractor->contract_number
            ]);

            // Crear el registro relacionado en contract_details
            $contractDetailData = [
                'contractor_id' => $contractor->id,
                'contract_date' => $validated['contract_date'],
                'contract_number_formatted' => $validated['contract_number'],
                'execution_start_date' => $validated['contract_start_date'],
                'execution_end_date' => $validated['contract_end_date'],
                'status' => 'issued',
            ];

            Log::info('💾 Intentando guardar ContractDetail', [
                'datos' => $contractDetailData
            ]);

            $contractDetail = ContractDetail::create($contractDetailData);

            Log::info('✅ ContractDetail guardado exitosamente', [
                'id' => $contractDetail->id
            ]);

            DB::commit();

            Log::info('🎉 TRANSACCIÓN COMPLETADA EXITOSAMENTE', [
                'contractor_id' => $contractor->id,
                'contract_detail_id' => $contractDetail->id,
                'contract_number' => $contractor->contract_number,
                'person_name' => $contractor->person->first_name . ' ' . $contractor->person->first_last_name
            ]);

            return redirect()->route('gth.admin.contractreports.index')
                ->with('success', '¡Contrato creado exitosamente! Número: ' . $contractor->contract_number);

        } catch (\Illuminate\Database\QueryException $e) {
            DB::rollBack();
            
            Log::error('❌ ERROR DE BASE DE DATOS al crear contrato', [
                'mensaje' => $e->getMessage(),
                'codigo' => $e->getCode(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'sql' => $e->getSql() ?? 'No disponible',
                'bindings' => $e->getBindings() ?? [],
            ]);

            // Mensaje más específico según el tipo de error
            $errorMessage = 'Error en la base de datos al guardar el contrato';
            
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $errorMessage = 'Ya existe un registro con estos datos en el sistema';
            } elseif (strpos($e->getMessage(), 'foreign key constraint') !== false) {
                $errorMessage = 'Error: Algunos de los datos relacionados no existen en el sistema';
            } elseif (strpos($e->getMessage(), 'Unknown column') !== false) {
                $errorMessage = 'Error: Hay campos que no existen en la base de datos. Contacte al administrador.';
            }

            return redirect()->back()
                ->withInput()
                ->with('error', $errorMessage . ' (Código: ' . $e->getCode() . ')');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ ERROR GENERAL al crear contrato', [
                'mensaje' => $e->getMessage(),
                'archivo' => $e->getFile(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'datos_validados' => $validated ?? []
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error inesperado al crear el contrato: ' . $e->getMessage() . ' (Línea: ' . $e->getLine() . ')');
        }
    }

    /**
     * Alias del método create (para compatibilidad con la ruta)
     */
    public function store(Request $request)
    {
        return $this->create($request);
    }

    /**
     * Actualizar un contrato existente
     */
    public function update(Request $request, $id)
    {
        Log::info('📝 Iniciando actualización de contrato', [
            'contractor_id' => $id,
            'datos' => $request->all(),
            'usuario' => auth()->id() ?? 'No autenticado'
        ]);

        DB::beginTransaction();
        
        try {
            $contractor = Contractor::findOrFail($id);
            
            Log::info('✅ Contrato encontrado', [
                'id' => $contractor->id,
                'contract_number' => $contractor->contract_number
            ]);
            
            // Extraer el año de la fecha de inicio del contrato
            $contractYear = Carbon::parse($request->input('contract_start_date'))->year;

            // Actualizar el contrato principal
            $contractor->update([
                'contract_number' => $request->input('contract_number'),
                'contract_year' => $contractYear,
                'contract_start_date' => $request->input('contract_start_date'),
                'contract_end_date' => $request->input('contract_end_date'),
                'total_contract_value' => $request->input('total_contract_value'),
                'contractor_type_id' => $request->input('contractor_type_id'),
                'contract_object' => $request->input('contract_object'),
                'contract_obligations' => $request->input('contract_obligations'),
                'amount_hours' => $request->input('amount_hours'),
                'assigment_value' => $request->input('assigment_value'),
                'employee_type_id' => $request->input('employee_type_id'),
                'SIIF_code' => $request->input('SIIF_code'),
                'insurer_entity_id' => $request->input('insurer_entity_id'),
                'policy_number' => $request->input('policy_number'),
                'policy_issue_date' => $request->input('policy_issue_date'),
                'policy_approval_date' => $request->input('policy_approval_date'),
                'policy_effective_date' => $request->input('policy_effective_date'),
                'policy_expiration_date' => $request->input('policy_expiration_date'),
                'risk_type' => $request->input('risk_type'),
                'state' => $request->input('state'),
            ]);

            Log::info('✅ Contractor actualizado');

            // Actualizar o crear los detalles del contrato
            $contractor->contractDetail()->updateOrCreate(
                ['contractor_id' => $contractor->id],
                [
                    'contract_date' => $request->input('contract_date'),
                    'contract_number_formatted' => $request->input('contract_number'),
                    'execution_start_date' => $request->input('contract_start_date'),
                    'execution_end_date' => $request->input('contract_end_date'),
                    'status' => 'issued',
                ]
            );

            Log::info('✅ ContractDetail actualizado/creado');

            DB::commit();

            Log::info('🎉 Contrato actualizado exitosamente', [
                'id' => $id,
                'contract_number' => $contractor->contract_number
            ]);

            return redirect()->route('gth.admin.contractreports.index')
                ->with('success', 'Contrato actualizado exitosamente');

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            
            Log::error('❌ Contrato no encontrado', [
                'id' => $id
            ]);

            return redirect()->route('gth.admin.contractreports.index')
                ->with('error', 'No se encontró el contrato especificado');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('❌ Error al actualizar contrato', [
                'id' => $id,
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Error al actualizar el contrato: ' . $e->getMessage());
        }
    }

    /**
     * Obtener datos de una persona por número de documento (AJAX)
     */
    public function getPersonData(Request $request)
    {
        try {
            $numeroDocumento = $request->input('document_number');

            if (!$numeroDocumento) {
                return response()->json([
                    'error' => 'Número de documento no proporcionado'
                ], 400);
            }

            Log::info('🔍 Buscando persona', [
                'documento' => $numeroDocumento
            ]);

            $person = Person::where('document_number', $numeroDocumento)->first();

            if ($person) {
                $this->personId = $person->id;
                
                Log::info('✅ Persona encontrada', [
                    'id' => $person->id,
                    'nombre' => $person->first_name . ' ' . $person->first_last_name
                ]);

                return response()->json([
                    'success' => true,
                    'id' => $person->id,
                    'first_name' => $person->first_name,
                    'first_last_name' => $person->first_last_name,
                    'second_last_name' => $person->second_last_name ?? '',
                ]);
            } else {
                Log::warning('⚠️ Persona no encontrada', [
                    'documento' => $numeroDocumento
                ]);

                return response()->json([
                    'error' => 'No se encontró ninguna persona con ese número de documento'
                ], 404);
            }
        } catch (\Exception $e) {
            Log::error('❌ Error al buscar persona', [
                'error' => $e->getMessage(),
                'documento' => $request->input('document_number'),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Error al buscar la persona en el sistema'
            ], 500);
        }
    }

    /**
     * Eliminar un contrato (soft delete)
     */
    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $contractor = Contractor::findOrFail($id);
            
            Log::info('🗑️ Eliminando contrato', [
                'id' => $id,
                'contract_number' => $contractor->contract_number
            ]);

            $contractor->delete();

            DB::commit();

            Log::info('✅ Contrato eliminado exitosamente', [
                'id' => $id
            ]);

            return redirect()->route('gth.admin.contractreports.index')
                ->with('success', 'Contrato eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('❌ Error al eliminar contrato', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return redirect()->back()
                ->with('error', 'Error al eliminar el contrato: ' . $e->getMessage());
        }
    }
}