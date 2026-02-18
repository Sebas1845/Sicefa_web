<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware. Now create something great!
|
*/

Route::middleware(['lang'])->group(function () {

    Route::prefix('gth')->group(function () {

        // ===================================
        // RUTAS PRINCIPALES
        // ===================================
        Route::get('/index', 'GTHController@index')->name('cefa.index.view');
        Route::get('/admin', 'GTHController@index')->name('gth.admin.index');
        Route::get('/attendanceregister', 'GTHController@viewregisterattendance')->name('gth.registerattendance.registerattendance.index');

        // ===================================
        // TYPE EMPLOYEE
        // ===================================
        Route::get('/employeetypes', 'EmployeeTypController@viewemployeetypes')->name('gth.admin.employeetypes.index');
        Route::get('/employeetypes/create', 'EmployeeTypController@getcreateemployeetypes')->name('cefa.gth.employeetypes.create');
        Route::post('/employeetypes/create', 'EmployeeTypController@postcreateemployeetypes')->name('cefa.gth.employeetypes.create');
        Route::get('gth/employeetypes/{id}', 'EmployeeTypController@showEmployeeType')->name('cefa.gth.employeetypes.show');
        Route::patch('/gth/employeetypes/update{id}', 'EmployeeTypController@updateeemployeetypes')->name('cefa.gth.employeetypes.update');
        Route::delete('/gth/employeetypes/{id}/delete', 'EmployeeTypController@deleteEmployeeType')->name('cefa.gth.employeetypes.delete');

        // ===================================
        // TYPE CONTRACTOR
        // ===================================
        Route::get('/contractortypes', 'ContractTypController@viewcontractortypes')->name('gth.admin.contractortypes.index');
        Route::post('/contractortypes/create', 'ContractTypController@postcreatecontractortypes')->name('cefa.gth.contractortypes.create');
        Route::get('gth/contractortypes/{id}', 'ContractTypController@showContractorTypes')->name('cefa.gth.contractortypes.show');
        Route::patch('/gth/contractortypes/update{id}', 'ContractTypController@updatecontractortypes')->name('cefa.gth.contractortypes.update');
        Route::delete('/gth/contractortypes/{id}/delete', 'ContractTypController@deleteContractorTypes')->name('cefa.gth.contractortypes.delete');

        // ===================================
        // INSURER ENTITIES (ASEGURADORAS)
        // ===================================
        Route::get('/insurerentities', 'InsurerEntityController@viewInsurers')->name('gth.admin.insurerentities.index');
        Route::post('/insurerentities/create', 'InsurerEntityController@store')->name('cefa.gth.insurerentities.create');
        Route::patch('/insurerentities/{id}', 'InsurerEntityController@update')->name('cefa.gth.insurerentities.update');
        Route::delete('/insurerentities/{id}', 'InsurerEntityController@destroy')->name('cefa.gth.insurerentities.delete');

        // ===================================
        // PENSION ENTITIES (PENSIONES)
        // ===================================
        Route::get('/pensionentities', 'PensionEntityController@viewPensions')->name('gth.admin.pensionentities.index');
        Route::post('/pensionentities/create', 'PensionEntityController@store')->name('cefa.gth.pensionentities.create');
        Route::patch('/pensionentities/{id}', 'PensionEntityController@update')->name('cefa.gth.pensionentities.update');
        Route::delete('/pensionentities/{id}', 'PensionEntityController@destroy')->name('cefa.gth.pensionentities.delete');

        // ===================================
        // INTERNS (PASANTES)
        // ===================================
        Route::get('/interns', 'IntersController@viewInterns')->name('gth.admin.interns.index');
        Route::get('/interns/create', 'CreateInternController@create')->name('gth.admin.interns.create');
        Route::post('/interns/store', 'CreateInternController@store')->name('gth.admin.interns.store');
        Route::get('/interns/{id}', 'IntersController@showIntern')->name('gth.admin.interns.show');
        Route::patch('/interns/update/{id}', 'IntersController@updateIntern')->name('gth.admin.interns.update');
        Route::delete('/interns/{id}', 'IntersController@deleteIntern')->name('gth.admin.interns.destroy');
        
        // Warehouse (Área)
        Route::post('/interns/assign-warehouse/{id}', 'IntersController@assignWarehouse')->name('gth.admin.interns.assign-warehouse');
        Route::delete('/interns/remove-warehouse/{id}', 'IntersController@removeWarehouse')->name('gth.admin.interns.remove-warehouse');
        
        // Supervisores
        Route::post('/interns/search-supervisor', 'IntersController@searchSupervisor')->name('gth.admin.interns.search-supervisor');
        Route::post('/interns/assign-supervisor/{id}', 'IntersController@assignSupervisor')->name('gth.admin.interns.assign-supervisor');
        Route::post('/interns/remove-supervisor/{id}', 'IntersController@removeSupervisor')->name('gth.admin.interns.remove-supervisor');

        // ===================================
        // CONTRACT REPORTS
        // ===================================
        Route::get('/contractreports', 'ContractReportController@viewcontractreports')->name('gth.admin.contractreports.index');
        Route::post('/contractreports', 'ContractReportController@create')->name('cefa.gth.contractreports.store');
        Route::get('/get-person-data', 'ContractReportController@getPersonData')->name('cefa.gth.getPersonData');

        // ===================================
        // CONTRACTORS
        // ===================================
        Route::get('/contractors', 'ContractorsController@viewcontractor')->name('gth.admin.contractors.index');
        Route::post('/contractors/create', 'ContractorsController@postcreatecontractor')->name('cefa.gth.contractor.create');
        Route::get('gth/contractors/{id}', 'ContractorsController@showContractor')->name('cefa.gth.contractor.show');
        Route::patch('/gth/contractors/update{id}', 'ContractorsController@updatecontractor')->name('cefa.gth.contractor.update');
        Route::delete('/gth/contractors/{id}/delete', 'ContractorsController@deleteContractor')->name('cefa.gth.contractor.delete');

        // ===================================
        // POSITIONS (CARGOS)
        // ===================================
        Route::get('/positions', 'PositionsController@viewpositions')->name('gth.admin.position.index');
        Route::post('/positions/create', 'PositionsController@postcreatepositions')->name('cefa.gth.positions.create');
        Route::get('gth/positions/{id}', 'PositionsController@showPositions')->name('cefa.gth.positions.show');
        Route::patch('/gth/positions/update/{id}', 'PositionsController@updatepositions')->name('cefa.gth.positions.update');
        Route::delete('/gth/positions/{id}/delete', 'PositionsController@deletepositions')->name('cefa.gth.positions.delete');

        // ===================================
        // OFFICIALS (FUNCIONARIOS)
        // ===================================
        Route::get('/official', 'OfficialController@viewofficials')->name('gth.admin.officials.index');
        Route::get('/obtener_datos', 'OfficialController@getPersonDatas')->name('cefa.gth.getPersonDatas');
        Route::post('/employees', 'OfficialController@store')->name('cefa.gth.store');
        Route::post('/official/edit/{id}', 'OfficialController@edit_official')->name('cefa.gth.officials.update');
        Route::delete('/gth/officials/{id}/delete', 'OfficialController@deleteofficials')->name('cefa.gth.officials.delete');

        // ===================================
        // BRIGADE (BRIGADISTAS)
        // ===================================
        Route::get('/brigade', 'BrigadeController@viewbrigader')->name('gth.admin.brigader.index');
        Route::get('/asistencia', 'BrigadeController@viewAsistencia')->name('cefa.gth.brigade.asistencia');
        Route::get('/reporte', 'BrigadeController@generateReport')->name('cefa.brigade.reporte');

        // ===================================
        // CONTRACTUAL CERTIFICATE (CERTIFICADOS)
        // ===================================
        
        // Vista principal y búsqueda
        Route::get('/contractualcertificate', 'ContractualCertificateController@viewcontractualcertificate')->name('cefa.contractualcertificate.view');
        Route::post('/contractualcertificate', 'ContractualCertificateController@viewcontractualcertificate')->name('cefa.contractualcertificate.search');
        Route::post('/contractualcertificate/search-ajax', 'ContractualCertificateController@search')->name('cefa.contractualcertificate.searchAjax');
        
        // Solicitudes de certificado
        Route::get('certificado/solicitar', 'ContractualCertificateController@requestCertificate')->name('cefa.contractualcertificate.request');
        Route::post('certificado/solicitar', 'ContractualCertificateController@requestCertificate')->name('cefa.contractualcertificate.request');
        
        // Generar certificado
        Route::post('/contractualcertificate/generate/{contractorId}', 'ContractualCertificateController@generateCertificate')->name('cefa.contractualcertificate.generate');
        Route::get('/gth/get-contract-years', 'ContractualCertificateController@getContractYears')->name('cefa.gth.getContractYears');
        
        // ADMIN: Gestión de solicitudes
        Route::get('certificado/pendientes', 'ContractualCertificateController@pendingRequests')->name('cefa.contractualcertificate.pending');
        Route::get('certificado/aprobar/{id}', 'ContractualCertificateController@approveRequest')->name('cefa.contractualcertificate.approve');
        Route::post('certificado/rechazar/{id}', 'ContractualCertificateController@rejectRequest')->name('cefa.contractualcertificate.reject');
        Route::get('certificado/emitido/{id}', 'ContractualCertificateController@markAsIssued')->name('cefa.contractualcertificate.issued');
        Route::post('certificado/no-emitido/{id}', 'ContractualCertificateController@markAsNotIssued')->name('cefa.contractualcertificate.notIssued');
        Route::delete('certificado/solicitud/{id}', 'ContractualCertificateController@destroyRequest')->name('cefa.contractualcertificate.destroy');
        
        // Certificados generados
        Route::get('/certificates', 'ContractualCertificateController@indexCertificates')->name('gth.certificates.index');
        Route::get('/certificates/{id}', 'ContractualCertificateController@showCertificate')->name('gth.certificates.show');
        Route::get('/certificates/{id}/edit', 'ContractualCertificateController@editCertificate')->name('gth.certificates.edit');
        Route::put('/certificates/{id}', 'ContractualCertificateController@updateCertificate')->name('gth.certificates.update');
        Route::post('/certificates/{id}/cancel', 'ContractualCertificateController@cancelCertificate')->name('gth.certificates.cancel');
        Route::get('/certificates/{certificateId}/pdf', 'ContractualCertificateController@pdfFromCertificate')->name('gth.contractualcertificate.pdf');

        // ===================================
        // PERSON (PERSONAS)
        // ===================================
        Route::patch('/person/{id}/update-gender', 'PersonController@updateGender')->name('cefa.gth.person.updateGender');
        Route::patch('/person/{id}/update-place', 'PersonController@updatePlace')->name('cefa.gth.person.updatePlace');

        // ===================================
        // ATTENDANCE (ASISTENCIA)
        // ===================================
        Route::get('/attendance', 'AttendanceController@viewattendance')->name('gth.registerattendance.attendancecourse.index');
        Route::post('/attendance/search', 'AttendanceController@search')->name('cefa.attendance.search');
        Route::get('/attendancereport', 'AttendanceReportController@viewattendancereport')->name('gth.brigadista.attendancereport.index');
        Route::get('/registerattendance', 'RegisterAttendanceController@registerattendance')->name('cefa.registerattendance.store');

        // ===================================
        // USER MANUAL
        // ===================================
        Route::get('/usermanual', 'UserManualController@viewusermanual')->name('cefa.usermanual.view');

        // ===================================
        // UTILIDADES / DEBUG
        // ===================================
        Route::get('/verificar-columnas', function() {
            $tieneGender = Schema::hasColumn('contractual_certificates', 'gender');
            $tienePlaceOfIssue = Schema::hasColumn('contractual_certificates', 'place_of_issue');
            
            return response()->json([
                'tabla' => 'contractual_certificates',
                'tiene_gender' => $tieneGender ? 'SÍ ✅' : 'NO ❌',
                'tiene_place_of_issue' => $tienePlaceOfIssue ? 'SÍ ✅' : 'NO ❌'
            ]);
        });
    });
});