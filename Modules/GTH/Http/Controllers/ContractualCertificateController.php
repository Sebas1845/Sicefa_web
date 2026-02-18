<?php

namespace Modules\GTH\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\SICA\Entities\Contractor;
use Modules\SICA\Entities\Person;
use Carbon\Carbon;
use Modules\SICA\Entities\ContractualCertificate;
use Modules\GTH\Entities\CertificateRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Modules\GTH\Entities\CertificateConfiguration;
use Illuminate\Support\Str;
use Money\Money;
use Money\Currency;
use NumberToWords\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;

class ContractualCertificateController extends Controller
{
    /**
     * Ver solicitudes pendientes de certificados
     */
    /**
 * Eliminar solicitud de certificado
 */
public function destroyRequest($id)
{
    $certificateRequest = CertificateRequest::findOrFail($id);

    // Solo permite eliminar si está pendiente
    if (!in_array($certificateRequest->status, ['solicitado', 'en_proceso'])) {
        return redirect()
            ->route('cefa.contractualcertificate.pending')
            ->with('error', 'No se puede eliminar una solicitud que ya fue gestionada.');
    }

    $personName = $certificateRequest->person->full_name ?? 'Usuario desconocido';
    
    $certificateRequest->delete();

    return redirect()
        ->route('cefa.contractualcertificate.pending')
        ->with('success', "Solicitud de {$personName} eliminada correctamente.");
}


    
    public function pendingRequests()
    {
        $pendingCertificates = CertificateRequest::with([
                'person',
                'contractor.contractor_type'
            ])
            ->whereIn('status', ['solicitado', 'en_proceso'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('gth::contractualcertificate.pending-requests', compact('pendingCertificates'));
    }

    /**
     * Aprobar solicitud (poner en proceso y redirigir a generar)
     */
    public function approveRequest($requestId)
    {
        $certificateRequest = CertificateRequest::findOrFail($requestId);
        
        $certificateRequest->update([
            'status' => 'en_proceso'
        ]);

        return redirect()
            ->route('cefa.contractualcertificate.view')
            ->with('certificate_request_id', $certificateRequest->id)
            ->with('info', 'Procesa el certificado para: ' . $certificateRequest->person->full_name);
    }

    /**
     * Rechazar solicitud (flujo antiguo, si aún lo usas)
     */
    public function rejectRequest(Request $request, $requestId)
    {
        $certificateRequest = CertificateRequest::findOrFail($requestId);
        
        $certificateRequest->update([
            'status' => 'rechazado',
            'rejection_reason' => $request->rejection_reason ?? 'Solicitud rechazada'
        ]);

        return redirect()
            ->route('gth.certificate.pending')
            ->with('success', 'Solicitud rechazada correctamente');
    }

    /**
     * NUEVO: marcar como certificado emitido
     */
    public function markAsIssued($requestId)
    {
        $certificateRequest = CertificateRequest::findOrFail($requestId);

        $certificateRequest->update([
            'status' => 'certificado_emitido',
            'issued_at' => now(),
        ]);

        return redirect()
            ->route('gth.certificate.pending')
            ->with('success', 'La solicitud fue marcada como CERTIFICADO EMITIDO.');
    }

    /**
     * NUEVO: marcar como no emitido (no se hizo certificado)
     */
    public function markAsNotIssued(Request $request, $requestId)
    {
        $certificateRequest = CertificateRequest::findOrFail($requestId);

        $certificateRequest->update([
            'status' => 'no_emitido',
            'rejection_reason' => $request->rejection_reason ?? 'No se generó el certificado',
        ]);

        return redirect()
            ->route('gth.certificate.pending')
            ->with('success', 'La solicitud fue marcada como NO EMITIDA.');
    }

    /**
     * Solicitar certificado (versión corregida)
     */
    public function requestCertificate(Request $request)
    {
        Log::info('========== NUEVA SOLICITUD DE CERTIFICADO ==========', [
            'timestamp' => now()->toDateTimeString(),
            'user_ip' => $request->ip(),
            'document_number' => $request->document_number,
            'contract_year' => $request->contract_year,
            'email' => $request->personal_email,
            'phone' => $request->telephone1,
            'all_input' => $request->except(['_token'])
        ]);

        try {
            $validated = $request->validate([
                'document_type'   => 'required|string|in:Cédula de ciudadanía,Tarjeta de identidad,Cédula de extranjería,Pasaporte,Documento nacional de identidad',
                'document_number' => 'required|numeric',
                'place_of_issue'  => 'nullable|string|max:100',
                'contract_year'   => 'required|integer|min:1995|max:' . (date('Y') + 5),

                'first_name'      => 'required|string|max:100',
                'second_name'     => 'nullable|string|max:100',
                'first_last_name' => 'required|string|max:100',
                'second_last_name'=> 'nullable|string|max:100',

                'personal_email'  => 'required|email|max:100',
                'telephone1'      => 'required|numeric|digits:10',
            ], [
                'document_type.required'   => 'Debe seleccionar el tipo de documento',
                'document_number.required' => 'El número de documento es obligatorio',
                'document_number.numeric'  => 'El documento debe contener solo números',
                'place_of_issue.required'  => 'El lugar de expedición es obligatorio',
                'contract_year.required'   => 'Debe seleccionar el año del contrato',
                'first_name.required'      => 'El primer nombre es obligatorio',
                'first_last_name.required' => 'El primer apellido es obligatorio',
                'personal_email.required'  => 'El correo electrónico es obligatorio',
                'personal_email.email'     => 'El correo electrónico no tiene un formato válido',
                'telephone1.required'      => 'El número de celular es obligatorio',
                'telephone1.digits'        => 'El celular debe tener exactamente 10 dígitos',
            ]);

            Log::info('✅ Validación exitosa', ['validated_fields' => array_keys($validated)]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Error de validación', [
                'errors' => $e->errors(),
                'input'  => $request->except(['_token'])
            ]);
            throw $e;
        }

        DB::beginTransaction();

        try {
            // PASO 1: buscar o crear persona
            $person = Person::where('document_number', $request->document_number)->first();

            if (!$person) {
                Log::info('🆕 CREANDO NUEVA PERSONA', [
                    'document_number' => $request->document_number,
                    'first_name'      => $request->first_name,
                    'second_name'     => $request->second_name,
                    'first_last_name' => $request->first_last_name,
                    'second_last_name'=> $request->second_last_name,
                    'email'           => $request->personal_email,
                    'phone'           => $request->telephone1
                ]);

                $nameParts = [];
                if (!empty($request->first_name))       { $nameParts[] = strtoupper(trim($request->first_name)); }
                if (!empty($request->second_name))      { $nameParts[] = strtoupper(trim($request->second_name)); }
                if (!empty($request->first_last_name))  { $nameParts[] = strtoupper(trim($request->first_last_name)); }
                if (!empty($request->second_last_name)) { $nameParts[] = strtoupper(trim($request->second_last_name)); }
                $fullName = implode(' ', $nameParts);

                Log::info('📝 Nombre completo generado:', ['full_name' => $fullName]);

                $personData = [
                    'document_type'   => $request->document_type,
                    'document_number' => $request->document_number,
                    'first_name'      => strtoupper(trim($request->first_name)),
                    'first_last_name' => strtoupper(trim($request->first_last_name)),
                    'second_name'     => !empty($request->second_name)      ? strtoupper(trim($request->second_name))      : null,
                    'second_last_name'=> !empty($request->second_last_name) ? strtoupper(trim($request->second_last_name)) : null,
                    'full_name'       => $fullName,
                    'place_of_issue'  => ucfirst(trim($request->place_of_issue)),
                    'personal_email'  => strtolower(trim($request->personal_email)),
                    'telephone1'      => $request->telephone1,

                    'eps_id'              => null,
                    'population_group_id' => null,
                    'pension_entity_id'   => null,

                    'gender'               => 'No registra',
                    'blood_type'           => 'No registra',
                    'marital_status'       => 'No registra',
                    'socioeconomical_status'=> 'No registra',

                    'telephone2'   => null,
                    'telephone3'   => null,
                    'address'      => null,
                    'date_of_birth'=> null,
                    'date_of_issue'=> null,
                    'military_card'=> null,
                    'sisben_level' => null,
                    'misena_email' => null,
                    'sena_email'   => null,
                ];

                Log::info('📝 Datos para crear persona:', ['person_data' => $personData]);

                $person = Person::create($personData);

                Log::info('✅ PERSONA CREADA EXITOSAMENTE', [
                    'person_id'       => $person->id,
                    'document_number' => $person->document_number,
                    'first_name'      => $person->first_name,
                    'second_name'     => $person->second_name,
                    'first_last_name' => $person->first_last_name,
                    'second_last_name'=> $person->second_last_name,
                    'full_name'       => $person->full_name,
                    'email'           => $person->personal_email,
                    'phone'           => $person->telephone1
                ]);

            } else {
                Log::info('✅ PERSONA EXISTENTE EN EL SISTEMA', [
                    'person_id'       => $person->id,
                    'document_number' => $person->document_number,
                    'current_full_name' => $person->full_name
                ]);

                $updateData = [
                    'personal_email' => strtolower(trim($request->personal_email)),
                    'telephone1'     => $request->telephone1,
                ];

                if (!empty($request->first_name))      { $updateData['first_name']      = strtoupper(trim($request->first_name)); }
                if (!empty($request->first_last_name)) { $updateData['first_last_name'] = strtoupper(trim($request->first_last_name)); }

                $updateData['second_name']      = !empty($request->second_name)      ? strtoupper(trim($request->second_name))      : $person->second_name;
                $updateData['second_last_name'] = !empty($request->second_last_name) ? strtoupper(trim($request->second_last_name)) : $person->second_last_name;

                $nameParts = [];
                if (!empty($updateData['first_name']))      { $nameParts[] = $updateData['first_name']; }
                if (!empty($updateData['second_name']))     { $nameParts[] = $updateData['second_name']; }
                if (!empty($updateData['first_last_name'])) { $nameParts[] = $updateData['first_last_name']; }
                if (!empty($updateData['second_last_name'])){ $nameParts[] = $updateData['second_last_name']; }
                $updateData['full_name'] = implode(' ', $nameParts);

                $person->update($updateData);

                Log::info('🔄 Datos actualizados', [
                    'updated_full_name' => $person->full_name,
                    'email'             => $person->personal_email,
                    'phone'             => $person->telephone1
                ]);
            }

            // PASO 2: evitar duplicados
            $existingRequest = CertificateRequest::where('person_id', $person->id)
                ->where('contract_year', $request->contract_year)
                ->whereIn('status', ['solicitado', 'en_proceso'])
                ->first();

            if ($existingRequest) {
                DB::rollBack();

                Log::warning('⚠️ SOLICITUD DUPLICADA', [
                    'existing_request_id' => $existingRequest->id,
                    'status'              => $existingRequest->status,
                    'person_id'           => $person->id,
                    'year'                => $request->contract_year
                ]);

                $statusText = $existingRequest->status === 'solicitado'
                    ? 'pendiente de revisión'
                    : 'en proceso de generación';

                return back()->with('warning',
                    'Ya existe una solicitud ' . $statusText . ' para ' .
                    $person->full_name .
                    ' en el año ' . $request->contract_year . '. ' .
                    'Por favor espere la respuesta de Gestión Humana.'
                );
            }

            // PASO 3: crear solicitud
            $certificateRequest = CertificateRequest::create([
                'person_id'     => $person->id,
                'contract_year' => $request->contract_year,
                'status'        => 'solicitado',
                'requested_at'  => now(),
                'notes'         => 'Solicitud web - Email: ' . $request->personal_email . ' - Tel: ' . $request->telephone1,
            ]);

            Log::info('✅ SOLICITUD CREADA', [
                'request_id'        => $certificateRequest->id,
                'person_id'         => $person->id,
                'person_full_name'  => $person->full_name,
                'year'              => $request->contract_year,
                'email'             => $request->personal_email,
                'phone'             => $request->telephone1
            ]);

            // PASO 4: asociar contrato existente
            $contractor = Contractor::where('person_id', $person->id)
                ->where('contract_year', $request->contract_year)
                ->whereNull('deleted_at')
                ->first();

            if ($contractor) {
                $certificateRequest->update(['contractor_id' => $contractor->id]);

                Log::info('✅ CONTRATO ENCONTRADO Y ASOCIADO', [
                    'contractor_id'   => $contractor->id,
                    'contract_number' => $contractor->contract_number ?? 'N/A'
                ]);
            } else {
                Log::warning('⚠️ NO SE ENCONTRÓ CONTRATO', [
                    'person_id' => $person->id,
                    'year'      => $request->contract_year,
                    'message'   => 'El administrador deberá revisar manualmente'
                ]);
            }

            DB::commit();

            Log::info('========== ✅ SOLICITUD COMPLETADA EXITOSAMENTE ==========', [
                'request_id'      => $certificateRequest->id,
                'person_id'       => $person->id,
                'person_full_name'=> $person->full_name,
                'has_contract'    => $contractor ? 'SÍ' : 'NO'
            ]);

            $successMessage = '¡Solicitud enviada exitosamente! ';
            if ($person->wasRecentlyCreated) {
                $successMessage .= 'Se ha registrado a ' . $person->full_name . ' en el sistema. ';
            }

            $successMessage .= 'Su solicitud de certificado contractual para el año ' . $request->contract_year .
                ' ha sido recibida. Recibirás una notificación al correo ' . $request->personal_email .
                ' en un plazo máximo de 48 horas hábiles.';

            if (!$contractor) {
                $successMessage .= ' (Nota: Requiere revisión manual del contrato)';
            }

            return redirect()->back()->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('========== ❌ ERROR AL PROCESAR SOLICITUD ==========', [
                'error_class'   => get_class($e),
                'error_message' => $e->getMessage(),
                'error_file'    => $e->getFile(),
                'error_line'    => $e->getLine(),
                'stack_trace'   => $e->getTraceAsString(),
                'request_data'  => $request->except(['_token'])
            ]);

            return back()
                ->withInput()
                ->with('error',
                    '❌ Ocurrió un error al procesar su solicitud. ' .
                    'Error técnico: ' . $e->getMessage() . '. ' .
                    'Por favor, intente nuevamente o contacte al área de Gestión Humana.'
                );
        }
    }

    /**
     * Vista del formulario de certificado
     */
    public function viewcontractualcertificate(Request $request)
    {
        $contractors = collect([]);
        $message = null;

        if ($request->has('document') && $request->isMethod('post')) {

            $person = Person::where('document_number', $request->document)->first();

            if (!$person) {
                $message = 'No se encontró ninguna persona con ese número de documento.';
            } else {
                $contractors = Contractor::with([
                        'person',
                        'supervisor',
                        'contractor_type',
                        'employee_type'
                    ])
                    ->where('person_id', $person->id)
                    ->whereNull('deleted_at')
                    ->get();

                if ($contractors->isEmpty()) {
                    $message = 'No se encontraron contratos para esta persona.';
                }
            }
        }

        return view('gth::contractualcertificate.contractualcertificate', compact('contractors', 'message'));
    }

    /**
     * Búsqueda de contratos por documento
     */
   public function search(Request $request)
{
    $person = Person::where('document_number', $request->document)->first();

    if (!$person) {
        return redirect()
            ->route('cefa.contractualcertificate.view')
            ->with('error', 'No se encontró ninguna persona con ese número de documento.');
    }

    $contractors = Contractor::with([
            'person',
            'supervisor',
            'contractor_type',
            'employee_type'
        ])
        ->where('person_id', $person->id)
        ->whereNull('deleted_at')
        ->get();

    if ($contractors->isEmpty()) {
        return redirect()
            ->route('cefa.contractualcertificate.view')
            ->with('error', 'No se encontraron contratos para esta persona.');
    }

    // ✅ Obtener solicitudes agrupadas por año del contrato
    $requestsByYear = CertificateRequest::where('person_id', $person->id)
        ->get()
        ->keyBy('contract_year');

    return view('gth::contractualcertificate.contractualcertificate', [
        'contractors' => $contractors,
        'message'     => null,
        'requestsByYear' => $requestsByYear  // ✅ Pasar solicitudes agrupadas
    ]);
}
    /**
     * Generar certificado con los datos del modal
     */
    public function generateCertificate(Request $request, $contractorId)
    {
        try {
            $contractor = Contractor::with([
                    'person',
                    'supervisor',
                    'contractor_type',
                    'employee_type'
                ])->findOrFail($contractorId);

            $configData = [
                'center_name'    => 'Centro de Formación Agroindustrial',
                'center_address' => 'Km 38 via al sur de Neiva, Campoalegre – Huila PBX 57 601 5461500',
                'version_code'   => 'GTH-F-131 V05',
                'title_line_1'   => 'LA SUSCRITA SUBDIRECTORA (E) DEL CENTRO DE FORMACIÓN AGROINDUSTRIAL DEL SERVICIO',
                'title_line_2'   => 'NACIONAL DE APRENDIZAJE SENA',
                'logo_color'     => '#39A900',
            ];

            $certificateData = array_merge($configData, [
                'contractor_id'        => $contractor->id,
                'contract_date'        => $contractor->contract_start_date,
                'execution_start_date' => $contractor->contract_start_date,
                'execution_end_date'   => $contractor->contract_end_date,
                'expedition_date'      => now(),
                'status'               => 'draft',

                'gender'        => trim($request->gender),
                'place_of_issue'=> ucfirst(trim($request->place_of_issue)),

                'projected_by'       => ucwords(strtolower(trim($request->projected_by))),
                'projected_by_role'  => ucfirst(trim($request->projected_by_role)),
                'reviewed_by'        => ucwords(strtolower(trim($request->reviewed_by))),
                'reviewed_by_role'   => ucfirst(trim($request->reviewed_by_role)),
                'director_name'      => strtoupper(trim($request->director_name)),
                'director_role'      => ucfirst(trim($request->director_role)),

                'payment_type'       => $request->payment_type,
            ]);

            if ($request->payment_type === 'mensual') {
                $certificateData['monthly_payment'] = $request->monthly_payment;
                $certificateData['unit_hour_value'] = null;
            } elseif ($request->payment_type === 'horas') {
                $certificateData['unit_hour_value'] = $request->unit_hour_value;
                $certificateData['monthly_payment'] = null;
            }

            $certificate = ContractualCertificate::create($certificateData);

            Log::info('Certificado creado con forma de pago:', [
                'certificate_id'   => $certificate->id,
                'payment_type'     => $certificate->payment_type,
                'monthly_payment'  => $certificate->monthly_payment,
                'unit_hour_value'  => $certificate->unit_hour_value,
            ]);

            $certificate->generateCertificateNumber();

            return redirect()
                ->route('gth.contractualcertificate.pdf', $certificate->id)
                ->with('success', 'Certificado generado correctamente');

        } catch (\Exception $e) {
            Log::error('Error al generar certificado:', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);
            return back()
                ->withInput()
                ->with('error', 'Error al generar el certificado: ' . $e->getMessage());
        }
    }

    /**
     * Generar PDF del certificado
     */
    public function pdfFromCertificate($certificateId)
    {
        $certificate = ContractualCertificate::with([
                'contractor.person',
                'contractor.contractor_type',
                'contractor.employee_type'
            ])->findOrFail($certificateId);

        $contract = $certificate->contractor;

        $startDate = Carbon::parse($certificate->execution_start_date);
        $endDate   = Carbon::parse($certificate->execution_end_date);
        $duration  = $endDate->diff($startDate);

        $totalInCOP   = new Money($contract->total_contract_value * 100, new Currency('COP'));
        $totalInWords = (new NumberToWords())
            ->getCurrencyTransformer('es')
            ->toWords($totalInCOP->getAmount(), 'COP');

        $certificateNumber = ltrim(Str::afterLast($certificate->certificate_number, '-'), '');

        $pdf = Pdf::loadView('gth::contractualcertificate.contractpdf', compact(
            'certificate',
            'contract',
            'duration',
            'totalInWords',
            'certificateNumber'
        ));

        $certificate->markAsIssued(auth()->id());

        return $pdf->stream('Certificacion_' . $certificateNumber . '.pdf');
    }
}
