<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Warehouse;
use Modules\SICA\Entities\Program;
use Modules\SIGAC\Entities\Intern;
use Illuminate\Support\Facades\Log;

class IntersController extends Controller
{
    /**
     * Mostrar la lista de pasantes
     */
    public function viewInterns()
    {
        // Obtener pasantes desde la tabla interns con sus relaciones
        $interns = Intern::with([
            'person',
            'person.e_p_s',
            'person.population_group',
            'person.pension_entity',
            'supervisor',
        ])
        ->orderBy('start_date', 'desc')
        ->get();

        // Cargar datos para filtros
        $warehouses = Warehouse::select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $programs = Program::select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('gth::contractualcertificate.interns', [
            'interns' => $interns,
            'warehouses' => $warehouses,
            'programs' => $programs,
        ]);
    }

    /**
     * Mostrar detalles de un pasante específico
     */
    public function showIntern($id)
    {
        $intern = Intern::with([
            'person',
            'person.e_p_s',
            'person.population_group',
            'person.pension_entity',
            'supervisor',
        ])->findOrFail($id);

        return response()->json($intern);
    }

    /**
     * Actualizar información del pasante
     */
    public function updateIntern(Request $request, $id)
    {
        Log::info('Inicio de updateIntern para ID: ' . $id . ' - Datos recibidos: ', $request->all());

        if (!auth()->check() || (auth()->check() && !auth()->user()->can('gth.admin.interns.update'))) {
            Log::warning('Acceso no autorizado a updateIntern para el usuario ID: ' . (auth()->id() ?? 'No autenticado'));
            return response()->json(['message' => 'This action is unauthorized.'], 403);
        }

        $request->validate([
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
        ]);

        try {
            $intern = Intern::with('person')->findOrFail($id);
            $person = $intern->person;
            
            Log::info('Persona encontrada para actualización: ', [
                'id' => $person->id, 
                'current_telephone1' => $person->telephone1, 
                'current_address' => $person->address
            ]);

            if ($request->has('phone')) {
                $person->telephone1 = $request->input('phone');
                Log::info('Teléfono actualizado a: ' . $request->input('phone'));
            }
            
            if ($request->has('address')) {
                $person->address = $request->input('address');
                Log::info('Dirección actualizada a: ' . $request->input('address'));
            }

            $person->save();
            Log::info('Actualización exitosa para ID: ' . $id);

            return response()->json([
                'success' => true, 
                'message' => 'Información del pasante actualizada correctamente.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error en updateIntern para ID: ' . $id . ' - Mensaje: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Error al actualizar la información del pasante: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Asignar área productiva a un pasante
     */
    public function assignWarehouse(Request $request, $id)
    {
        Log::info('Inicio de assignWarehouse para ID: ' . $id . ' - Datos recibidos: ', $request->all());

        $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
        ]);

        try {
            $intern = Intern::findOrFail($id);
            Log::info('Pasante encontrado para asignar warehouse: ', ['id' => $intern->id]);

            $warehouse = Warehouse::findOrFail($request->warehouse_id);
            
            $intern->assigned_area = $warehouse->name;
            $intern->save();
            
            Log::info('Área productiva asignada exitosamente para ID: ' . $id . ' - Warehouse: ' . $warehouse->name);

            session()->flash('success', 'Área productiva asignada correctamente al pasante.');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true, 
                    'message' => 'Área productiva asignada correctamente al pasante.'
                ]);
            }

            return redirect()->route('gth.admin.interns.index');
                
        } catch (\Exception $e) {
            Log::error('Error en assignWarehouse para ID: ' . $id . ' - Mensaje: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al asignar el área productiva: ' . $e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Error al asignar el área productiva: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back();
        }
    }

    /**
     * Remover asignación de área productiva
     */
    public function removeWarehouse(Request $request, $id)
    {
        Log::info('Inicio de removeWarehouse para ID: ' . $id);

        try {
            $intern = Intern::findOrFail($id);
            Log::info('Pasante encontrado para remover warehouse: ', ['id' => $intern->id]);

            $intern->assigned_area = null;
            $intern->save();
            
            Log::info('Área productiva removida exitosamente para ID: ' . $id);

            session()->flash('success', 'Área productiva removida correctamente.');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true, 
                    'message' => 'Área productiva removida correctamente.'
                ]);
            }

            return redirect()->route('gth.admin.interns.index');
                
        } catch (\Exception $e) {
            Log::error('Error en removeWarehouse para ID: ' . $id . ' - Mensaje: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al remover el área productiva: ' . $e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Error al remover el área productiva: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back();
        }
    }



    /**
     * Buscar supervisor por número de documento (AJAX)
     */
    public function searchSupervisor(Request $request)
    {
        $documentNumber = $request->input('document_number');

        $supervisor = Person::where('document_number', $documentNumber)->first();

        if ($supervisor) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $supervisor->id,
                    'first_name' => $supervisor->first_name,
                    'first_last_name' => $supervisor->first_last_name,
                    'second_last_name' => $supervisor->second_last_name,
                    'full_name' => $supervisor->first_name . ' ' . 
                                  $supervisor->first_last_name . ' ' . 
                                  ($supervisor->second_last_name ?? ''),
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Supervisor no encontrado'
            ], 404);
        }
    }

    /**
     * Asignar supervisor a un pasante
     */
    public function assignSupervisor(Request $request, $id)
    {
        Log::info('Inicio de assignSupervisor para ID: ' . $id . ' - Datos recibidos: ', $request->all());

        $request->validate([
            'supervisor_id' => 'required|exists:people,id',
        ]);

        try {
            $intern = Intern::findOrFail($id);
            Log::info('Pasante encontrado para asignar supervisor: ', ['id' => $intern->id]);

            $intern->assigned_supervisor_id = $request->supervisor_id;
            $intern->save();
            
            Log::info('Supervisor asignado exitosamente para ID: ' . $id . ' - Supervisor ID: ' . $request->supervisor_id);

            session()->flash('success', 'Supervisor asignado correctamente al pasante.');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true, 
                    'message' => 'Supervisor asignado correctamente al pasante.'
                ]);
            }

            return redirect()->route('gth.admin.interns.index');
                
        } catch (\Exception $e) {
            Log::error('Error en assignSupervisor para ID: ' . $id . ' - Mensaje: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al asignar el supervisor: ' . $e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Error al asignar el supervisor: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back();
        }
    }

    /**
     * Remover asignación de supervisor
     */
    public function removeSupervisor(Request $request, $id)
    {
        Log::info('Inicio de removeSupervisor para ID: ' . $id);

        try {
            $intern = Intern::findOrFail($id);
            Log::info('Pasante encontrado para remover supervisor: ', ['id' => $intern->id]);

            $intern->assigned_supervisor_id = null;
            $intern->save();
            
            Log::info('Supervisor removido exitosamente para ID: ' . $id);

            session()->flash('success', 'Supervisor removido correctamente.');

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true, 
                    'message' => 'Supervisor removido correctamente.'
                ]);
            }

            return redirect()->route('gth.admin.interns.index');
                
        } catch (\Exception $e) {
            Log::error('Error en removeSupervisor para ID: ' . $id . ' - Mensaje: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString()
            ]);
            session()->flash('error', 'Error al remover el supervisor: ' . $e->getMessage());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Error al remover el supervisor: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back();
        }
    }
}