<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
use NumberToWords\Legacy\Numbers\Words\Locale\Ro;





Route::middleware(['lang'])->group(function () { //Middleware que permite la internacionalizacion

    Route::prefix('sigac')->group(function () {  // agrega el prefijo en la url (sicefa.test/sigac/...)

        // RUTAS GENERALES
        Route::controller(SIGACController::class)->group(function () { // Agregar por única vez el controlodaar para posteriormente solo definir rutas con el formato (url, método_controlador)->name(nombre_de_ruta)
            Route::get('index', 'index')->name('cefa.sigac.index'); // Vista principal y pública de la aplicación.
            Route::get('information', 'info')->name('cefa.sigac.info'); // Vista mas info sobre SIGAC y pública de la aplicación (Pública)
            Route::get('developers', 'devs')->name('cefa.sigac.devs'); // Vista sobre desarrolladores y creditos sobre SIGAC y pública de la aplicación (Pública)
            Route::get('academic_coordination', 'academic_coordination_dashboard')->name('sigac.academic_coordination.dashboard'); // Panel de control de coordinación académica (Coordinación Académica)
            Route::get('instructor', 'instructor_dashboards')->name('sigac.instructor.dashboard'); // Panel de control del instructor (Instructor)
            Route::get('wellness', 'wellness_dashboard')->name('sigac.wellbeing.dashboard'); // Panel de control de bienestar (Bienestar)
            Route::get('apprentice', 'apprentice_dashboard')->name('sigac.apprentice.dashboard'); // Panel de control de aprendiz (Aprendiz)
            Route::get('support', 'support_dashboard')->name('sigac.support.dashboard'); // Panel de control de apoyo (Apoyo)
            Route::get('securitystaff', 'securitystaff_dashboard')->name('sigac.securitystaff.dashboard'); // Panel de control de apoyo (Apoyo)
            Route::get('securitypersonnel', 'securitypersonnel_dashboard')->name('sigac.securitypersonnel.dashboard'); // Panel de control del personal de seguridad (Apoyo)
            Route::get('tutor', 'tutor_dashboards')->name('sigac.tutor.dashboard'); // Panel de control del instructor (Instructor)
            Route::get('campesena', 'campesena_dashboard')->name('sigac.campesena.dashboard'); // Panel de control de campesena (Campesena)



        });

        // RUTAS PROGRAMACION DE INSTRUCTORES
        Route::controller(ProgrammeController::class)->group(function () {

            /*
    |--------------------------------------------------------------------------
    | 1) CALENDARIO / VISTAS BASE (Horarios y eventos)
    |--------------------------------------------------------------------------
    | - "programming": vista principal del calendario de programación
    | - "programming_get": JSON/eventos para pintar el calendario
    | - "event_programming": vista de programación de eventos
    */
            Route::get('programming/index', 'programming')->name('cefa.sigac.programming.index');

            Route::get('academic_coordination/programming/index', 'programming')->name('sigac.academic_coordination.programming.index');
            Route::get('instructor/programming/index', 'programming')->name('sigac.instructor.programming.index');
            Route::get('support/programming/index', 'programming')->name('sigac.support.programming.index');
            Route::get('apprentice/programming/index', 'programming')->name('sigac.apprentice.programming.index');
            Route::get('wellness/programming/index', 'programming')->name('sigac.wellness.programming.index');

            Route::get('academic_coordination/programming/get', 'programming_get')->name('sigac.academic_coordination.programming.get');
            Route::get('academic_coordination/events', 'event_programming')->name('sigac.academic_coordination.event_programming.index');


            /*
    |--------------------------------------------------------------------------
    | 2) PARÁMETROS (Catálogos base: profesiones, programas especiales, actividades externas)
    |--------------------------------------------------------------------------
    | Se usa para “parametrizar” lo que luego consume la programación.
    */
            Route::get('academic_coordination/programming/parameters/index', 'parameter')->name('sigac.academic_coordination.programming.parameters.index');
            Route::get('wellbeing/programming/parameters/index', 'parameter')->name('sigac.wellbeing.programming.parameters.index');

            // Profesiones (solo coordinación en tu bloque actual)
            Route::post('academic_coordination/profession/store', 'profession_store')->name('sigac.academic_coordination.programming.profession.store');
            Route::post('academic_coordination/profession/update/{id}', 'profession_update')->name('sigac.academic_coordination.programming.profession.update');
            Route::delete('academic_coordination/profession/destroy/{id}', 'profession_destroy')->name('sigac.academic_coordination.programming.profession.destroy');

            // Programas especiales (solo coordinación en tu bloque actual)
            Route::post('academic_coordination/programming/parameters/special_program/store', 'special_program_store')->name('sigac.academic_coordination.programming.parameters.special_program.store');
            Route::post('academic_coordination/programming/parameters/special_program/update', 'special_program_update')->name('sigac.academic_coordination.programming.parameters.special_program.update');
            Route::delete('academic_coordination/programming/parameters/special_program/destroy/{id}', 'special_program_destroy')->name('sigac.academic_coordination.programming.parameters.special_program.destroy');

            // Actividades externas (catálogo)
            Route::post('academic_coordination/programming/parameters/external_activities/store', 'external_activity_store')->name('sigac.academic_coordination.programming.parameters.external_activities.store');
            Route::post('wellbeing/programming/parameters/external_activities/store', 'external_activity_store')->name('sigac.wellbeing.programming.parameters.external_activities.store');

            Route::post('academic_coordination/programming/parameters/external_activities/update', 'external_activity_update')->name('sigac.academic_coordination.programming.parameters.external_activities.update');
            Route::post('wellbeing/programming/parameters/external_activities/update', 'external_activity_update')->name('sigac.wellbeing.programming.parameters.external_activities.update');

            Route::delete('academic_coordination/programming/parameters/external_activities/destroy/{id}', 'external_activity_destroy')->name('sigac.academic_coordination.programming.parameters.external_activities.destroy');
            Route::delete('wellbeing/programming/parameters/external_activities/destroy/{id}', 'external_activity_destroy')->name('sigac.wellbeing.programming.parameters.external_activities.destroy');


            /*
    |--------------------------------------------------------------------------
    | 3) PARAMETRIZACIÓN DE PROGRAMAS / COMPETENCIAS / RESULTADOS
    |--------------------------------------------------------------------------
    | Se usa para cargar/editar: Programas, Competencias y Resultados de aprendizaje.
    */
            // Competencias
            Route::get('academic_coordination/competences/index/{program_id}', 'parameter_competencies')->name('sigac.academic_coordination.programming.competence.index');
            Route::post('academic_coordination/competences/store', 'competence_store')->name('sigac.academic_coordination.programming.competence.store');
            Route::post('academic_coordination/competences/update/{id}', 'competence_update')->name('sigac.academic_coordination.programming.competence.update');
            Route::delete('academic_coordination/competences/destroy/{id}', 'competence_destroy')->name('sigac.academic_coordination.programming.competence.destroy');

            // Programas (import/export)
            Route::get('academic_coordination/programming/programs/export', 'program_export')->name('sigac.academic_coordination.programming.programs.export');
            Route::get('academic_coordination/programming/programs/load/create', 'program_load_create')->name('sigac.academic_coordination.programming.programs.load.create');
            Route::post('academic_coordination/programming/programs/load/store', 'program_load_store')->name('sigac.academic_coordination.programming.programs.load.store');
            Route::get('academic_coordination/programming/programs/search', 'program_search')->name('sigac.academic_coordination.programming.programs.search');

            // Resultados de aprendizaje
            Route::get('academic_coordination/learning_outcomes/index/{competencie_id}/{program_id}', 'parameter_learning_outcomes')->name('sigac.academic_coordination.programming.learning_outcome.index');
            Route::post('academic_coordination/learning_outcomes/store', 'learning_outcome_store')->name('sigac.academic_coordination.programming.learning_outcome.store');
            Route::post('academic_coordination/learning_outcomes/update/{id}', 'learning_outcome_update')->name('sigac.academic_coordination.programming.learning_outcome.update');
            Route::delete('academic_coordination/learning_outcomes/destroy/{id}', 'learning_outcome_destroy')->name('sigac.academic_coordination.programming.learning_outcome.destroy');
            Route::get('academic_coordination/learning_outcomes/load/create/{program_id}', 'learning_outcome_load_create')->name('sigac.academic_coordination.programming.learning_outcome.load.create');
            Route::post('academic_coordination/learning_outcomes/load/store', 'learning_outcome_load_store')->name('sigac.academic_coordination.programming.learning_outcome.load.store');


            /*
    |--------------------------------------------------------------------------
    | 4) GESTIÓN DE PROGRAMACIÓN (Crear/borrar programación, filtros, novedades)
    |--------------------------------------------------------------------------
    | Coordinación crea programación. Instructor puede reportar novedad y eliminar (según tu diseño actual).
    */
            Route::get('academic_coordination/programming/management/index', 'management_programming')->name('sigac.academic_coordination.programming.management.index');

            Route::get('academic_coordination/programming/management/search_quarter_number', 'management_search_quarter_number')->name('sigac.academic_coordination.programming.management.search_quarter_number');
            Route::get('academic_coordination/programming/management/filterquarterlie', 'management_programming_filterquarterlie')->name('sigac.academic_coordination.programming.management.filterquarterlie');
            Route::get('academic_coordination/programming/management/filterlearning', 'management_programming_filterlearning')->name('sigac.academic_coordination.programming.management.filterlearning');
            Route::get('academic_coordination/programming/management/filterinstructor', 'management_programming_filterinstructor')->name('sigac.academic_coordination.programming.management.filterinstructor');
            Route::get('academic_coordination/programming/management/filterenvironment', 'management_programming_filterenvironment')->name('sigac.academic_coordination.programming.management.filterenvironment');
            Route::get('academic_coordination/programming/management/filterstatelearning', 'management_programming_filterstatelearning')->name('sigac.academic_coordination.programming.management.filterstatelearning');

            Route::post('academic_coordination/programming/management/store', 'management_programming_store')->name('sigac.academic_coordination.programming.management.store');
            Route::get('academic_coordination/programming/management/search_course', 'management_programming_search_course')->name('sigac.academic_coordination.programming.management.search_course');
            Route::post('academic_coordination/programming/management/destroy', 'management_programming_destroy')->name('sigac.academic_coordination.programming.management.destroy');

            // Novedades (coord e instructor)
            Route::post('academic_coordination/programming/management/novelty/store', 'management_programming_novelty')->name('sigac.academic_coordination.programming.management.novelty.store');
            Route::post('instructor/programming/management/novelty/store', 'management_programming_novelty')->name('sigac.instructor.programming.management.novelty.store');

            // Eliminar programación (instructor) + buscar curso (instructor)
            Route::post('instructor/programming/management/destroy', 'management_programming_destroy')->name('sigac.instructor.programming.management.destroy');
            Route::get('instructor/programming/management/search_course', 'management_programming_search_course')->name('sigac.instructor.programming.management.search_course');

            // Filtros/consulta de horarios (por roles)
            Route::post('academic_coordination/programming/management/filter', 'management_filter')->name('sigac.academic_coordination.programming.management.filter');
            Route::post('academic_coordination/programming/management/search', 'management_search')->name('sigac.academic_coordination.programming.management.search');

            Route::post('instructor/programming/management/filter', 'management_filter')->name('sigac.instructor.programming.management.filter');
            Route::post('instructor/programming/management/search', 'management_search')->name('sigac.instructor.programming.management.search');

            Route::post('support/programming/management/filter', 'management_filter')->name('sigac.support.programming.management.filter');
            Route::post('support/programming/management/search', 'management_search')->name('sigac.support.programming.management.search');

            Route::post('apprentice/programming/management/filter', 'management_filter')->name('sigac.apprentice.programming.management.filter');
            Route::post('apprentice/programming/management/search', 'management_search')->name('sigac.apprentice.programming.management.search');

            Route::post('wellness/programming/management/filter', 'management_filter')->name('sigac.wellness.programming.management.filter');
            Route::post('wellness/programming/management/search', 'management_search')->name('sigac.wellness.programming.management.search');

            Route::post('programming/management/filter', 'management_filter')->name('cefa.sigac.programming.management.filter');
            Route::post('programming/management/search', 'management_search')->name('cefa.sigac.programming.management.search');


            /*
    |--------------------------------------------------------------------------
    | 5) FECHAS (si tu controlador realmente tiene dates_index y store_dates)
    |--------------------------------------------------------------------------
    */
            Route::get('academic_coordination/programming/dates', 'dates_index')->name('sigac.academic_coordination.programming.dates_index');
            Route::post('academic_coordination/programming/dates/store_dates', 'store_dates')->name('sigac.academic_coordination.programming.dates.store_dates');


            /*
    |--------------------------------------------------------------------------
    | 6) SOLICITUD DE PROGRAMA (ProgramRequest)
    |--------------------------------------------------------------------------
    | - index/table: formulario + listado solicitudes del usuario
    | - search*: endpoints AJAX para select2/autocomplete
    | - store: crear solicitud
    | - document_store: subir documentos faltantes
    | - download: descargar ZIP de documentos
    | - characterization/confirmation: flujo de coordinación/apoyo/campesena para “confirmar” y crear Course
    |
    | IMPORTANTE: Aquí era donde te faltaban rutas espejo para CAMPESENA.
    */
            //Aprovar / Devolver / Rechazar (Coordinación Académica) 
            Route::post('academic_coordination/programming/program_request/{id}/approve', 'program_request_approve')->name('sigac.academic_coordination.programming.program_request.approve');

            Route::post('academic_coordination/programming/program_request/{id}/devolve', 'program_request_devolve')->name('sigac.academic_coordination.programming.program_request.devolve');

            Route::post('academic_coordination/programming/program_request/{id}/reject', 'program_request_reject')->name('sigac.academic_coordination.programming.program_request.reject');

            // Listado/table (Instructor, Coordinación, Campesena, Support)
            Route::get('instructor/programming/program_request/table', 'program_request_table')->name('sigac.instructor.programming.program_request.table');
            Route::get('academic_coordination/programming/program_request/table', 'program_request_table')->name('sigac.academic_coordination.programming.program_request.table');
            Route::get('campesena/programming/program_request/table', 'program_request_table')->name('sigac.campesena.programming.program_request.table'); // <-- FALTABA
            Route::get('support/programming/program_request/table', 'program_request_table')->name('sigac.support.programming.program_request.table');   // <-- RECOMENDADA

            // Formulario/index
            Route::get('instructor/programming/program_request/index', 'program_request_index')->name('sigac.instructor.programming.program_request.index');
            Route::get('academic_coordination/programming/program_request/index', 'program_request_index')->name('sigac.academic_coordination.programming.program_request.index');
            Route::get('campesena/programming/program_request/index', 'program_request_index')->name('sigac.campesena.programming.program_request.index'); // <-- FALTABA

            // AJAX búsqueda (personas/profesión/empresa/veredas/solicitante)
            Route::get('instructor/programming/program_request/searchperson', 'program_request_searchperson')->name('sigac.instructor.programming.program_request.searchperson');
            Route::get('academic_coordination/programming/program_request/searchperson', 'program_request_searchperson')->name('sigac.academic_coordination.programming.program_request.searchperson');
            Route::get('campesena/programming/program_request/searchperson', 'program_request_searchperson')->name('sigac.campesena.programming.program_request.searchperson'); // <-- FALTABA

            Route::get('instructor/programming/program_request/searchprofession', 'program_request_searchprofession')->name('sigac.instructor.programming.program_request.searchprofession');
            Route::get('academic_coordination/programming/program_request/searchprofession', 'program_request_searchprofession')->name('sigac.academic_coordination.programming.program_request.searchprofession');
            Route::get('campesena/programming/program_request/searchprofession', 'program_request_searchprofession')->name('sigac.campesena.programming.program_request.searchprofession'); // <-- FALTABA

            Route::get('instructor/programming/program_request/searchempresa', 'program_request_searchempresa')->name('sigac.instructor.programming.program_request.searchempresa');
            Route::get('academic_coordination/programming/program_request/searchempresa', 'program_request_searchempresa')->name('sigac.academic_coordination.programming.program_request.searchempresa');
            Route::get('campesena/programming/program_request/searchempresa', 'program_request_searchempresa')->name('sigac.campesena.programming.program_request.searchempresa'); // <-- FALTABA

            Route::get('instructor/programming/program_request/searchvillages', 'program_request_searchvillages')->name('sigac.instructor.programming.program_request.searchvillages');
            Route::get('academic_coordination/programming/program_request/searchvillages', 'program_request_searchvillages')->name('sigac.academic_coordination.programming.program_request.searchvillages');
            Route::get('campesena/programming/program_request/searchvillages', 'program_request_searchvillages')->name('sigac.campesena.programming.program_request.searchvillages'); // <-- FALTABA
            Route::get('program_request/searchmunicipalities', 'program_request_searchmunicipalities')->name('programming.program_request.searchmunicipalities');

            Route::post('instructor/programming/program_request/storevillages', 'program_request_storevillages')->name('sigac.instructor.programming.program_request.storevillages');
            Route::post('academic_coordination/programming/program_request/storevillages', 'program_request_storevillages')->name('sigac.academic_coordination.programming.program_request.storevillages');
            Route::post('campesena/programming/program_request/storevillages', 'program_request_storevillages')->name('sigac.campesena.programming.program_request.storevillages'); // <-- FALTABA

            Route::get('instructor/programming/program_request/searchapplicant', 'program_request_searchapplicant')->name('sigac.instructor.programming.program_request.searchapplicant');
            Route::get('academic_coordination/programming/program_request/searchapplicant', 'program_request_searchapplicant')->name('sigac.academic_coordination.programming.program_request.searchapplicant');
            Route::get('campesena/programming/program_request/searchapplicant', 'program_request_searchapplicant')->name('sigac.campesena.programming.program_request.searchapplicant'); // <-- FALTABA
            Route::get('program_request/searchmunicipality', 'program_request_searchmunicipality')->name('sigac.programming.program_request.searchmunicipality');
            Route::get('program_request/municipalities', 'program_request_searchmunicipalities')->name('sigac.programming.program_request.municipalities');


            // Conflicto de horario (global)
            Route::post('programming/program_request/searchschedule', 'program_request_searchschedule')->name('sigac.programming.program_request.searchschedule');

            Route::get('program_request/{id}/dates', 'program_request_dates_json')->name('sigac.programming.program_request.dates_json');


            // Crear solicitud (store)
            Route::post('instructor/programming/program_request/store', 'program_request_store')->name('sigac.instructor.programming.program_request.store');
            Route::post('academic_coordination/programming/program_request/store', 'program_request_store')->name('sigac.academic_coordination.programming.program_request.store');
            Route::post('campesena/programming/program_request/store', 'program_request_store')->name('sigac.campesena.programming.program_request.store'); // <-- FALTABA

            // Documentos faltantes
            Route::post('academic_coordination/programming/program_request/document_store/{id}', 'program_request_document_store')->name('sigac.academic_coordination.programming.program_request.document_store');
            Route::post('instructor/programming/program_request/document_store/{id}', 'program_request_document_store')->name('sigac.instructor.programming.program_request.document_store');
            Route::post('campesena/programming/program_request/document_store/{id}', 'program_request_document_store')->name('sigac.campesena.programming.program_request.document_store'); // <-- FALTABA
            Route::post('support/programming/program_request/document_store/{id}', 'program_request_document_store')->name('sigac.support.programming.program_request.document_store'); // <-- RECOMENDADA
            Route::get('program_request/excel-template', 'program_request_excel_template')->name('programming.program_request.excel_template');

            // Descargar ZIP documentos (ya tenías, lo dejo coherente)
            Route::get('academic_coordination/programming/program_request/download/{id}', 'program_request_download')->name('sigac.academic_coordination.programming.program_request.download');
            Route::get('instructor/programming/program_request/download/{id}', 'program_request_download')->name('sigac.instructor.programming.program_request.download');
            Route::get('campesena/programming/program_request/download/{id}', 'program_request_download')->name('sigac.campesena.programming.program_request.download');
            Route::get('support/programming/program_request/download/{id}', 'program_request_download')->name('sigac.support.programming.program_request.download');
            Route::get('program_request/document/download/{document_id}', 'program_request_document_download')->name('sigac.programming.program_request.document.download');
            Route::get('support/programming/program-request/document/{id}/download','program_request_document_download')->name('sigac.support.programming.program_request.document.download');

            Route::post('academic_coordination/programming/program-request/dismiss/{id}','program_request_dismiss')->name('sigac.academic_coordination.programming.program_request.dismiss');
            Route::post('campesena/programming/program-request/dismiss/{id}','program_request_dismiss')->name('sigac.campesena.programming.program_request.dismiss');


            // Bandeja de caracterización (apoyo/coord/campesena)
            Route::get('support/programming/program_request/characterization/index', 'program_request_characterization')->name('sigac.support.programming.program_request.characterization.index');
            Route::get('academic_coordination/programming/program_request/characterization/index', 'program_request_characterization')->name('sigac.academic_coordination.programming.program_request.characterization.index');
            Route::get('campesena/programming/program_request/characterization/index', 'program_request_characterization')->name('sigac.campesena.programming.program_request.characterization.index');

            // Preconfirmar (coord/campesena)
            Route::get('academic_coordination/programming/program_request/confirmation/{id}', 'program_request_confirmation')->name('sigac.academic_coordination.programming.program_request.confirmation');
            Route::get('campesena/programming/program_request/confirmation/{id}', 'program_request_confirmation')->name('sigac.campesena.programming.program_request.confirmation');

            // Caracterizar / Cancelar (apoyo)
            Route::post('support/programming/program_request/characterization/store/{id}', 'program_request_characterization_store')->name('sigac.support.programming.program_request.characterization.store');
            Route::post('support/programming/program_request/characterization/devolution/{id}', 'program_request_characterization_devolution')->name('sigac.support.programming.program_request.characterization.devolution');
            Route::get('programming/program-request/template/apprentices', 'downloadApprenticesTemplate')->name('sigac.program_request.template.apprentices');



            /*
    |--------------------------------------------------------------------------
    | 7) ACTIVIDADES EXTERNAS (flujo operativo)
    |--------------------------------------------------------------------------
    | - index/create/store: creación y listado
    | - search_course/searchperson: ayudas AJAX
    | - approved/cancel: aprobación masiva o por ID
    |
    | FALTABA: approved/cancel para WELLNESS en tu bloque.
    */
            // Academic coordination
            Route::get('academic_coordination/programming/external_activities/index', 'external_activities_index')->name('sigac.academic_coordination.programming.external_activities.index');
            Route::get('academic_coordination/programming/external_activities/create', 'external_activities_create')->name('sigac.academic_coordination.programming.external_activities.create');
            Route::get('academic_coordination/programming/external_activities/search_course', 'external_activities_search_course')->name('sigac.academic_coordination.programming.external_activities.search_course');
            Route::get('academic_coordination/programming/external_activities/searchperson', 'external_activities_search_person')->name('sigac.academic_coordination.programming.external_activities.search_person');
            Route::post('academic_coordination/programming/external_activities/store', 'external_activities_store')->name('sigac.academic_coordination.programming.external_activities.store');
            Route::post('academic_coordination/programming/external_activities/approved', 'approved_external_activities')->name('sigac.academic_coordination.programming.external_activities.approved');
            Route::post('academic_coordination/programming/external_activities/cancel', 'cancel_external_activities')->name('sigac.academic_coordination.programming.external_activities.cancel');

            // Wellness (incluye approved/cancel -> <-- FALTABAN)
            Route::get('wellness/programming/external_activities/index', 'external_activities_index')->name('sigac.wellness.programming.external_activities.index');
            Route::get('wellness/programming/external_activities/create', 'external_activities_create')->name('sigac.wellness.programming.external_activities.create');
            Route::get('wellness/programming/external_activities/search_course', 'external_activities_search_course')->name('sigac.wellness.programming.external_activities.search_course');
            Route::get('wellness/programming/external_activities/searchperson', 'external_activities_search_person')->name('sigac.wellness.programming.external_activities.search_person');
            Route::post('wellness/programming/external_activities/store', 'external_activities_store')->name('sigac.wellness.programming.external_activities.store');
            Route::post('wellness/programming/external_activities/approved', 'approved_external_activities')->name('sigac.wellness.programming.external_activities.approved'); // <-- FALTABA
            Route::post('wellness/programming/external_activities/cancel', 'cancel_external_activities')->name('sigac.wellness.programming.external_activities.cancel');     // <-- FALTABA
        });

        // RUTAS PLANEACION CURRICULAR
        Route::controller(CurriculumPlanningController::class)->group(function () {

            // ---------------- Proyecto Formatrivo ---------------------------
            Route::get('academic_coordination/curriculum_planning/training_project/index', 'training_project_index')->name('sigac.academic_coordination.curriculum_planning.training_project.index'); // Vista proyectos formativos y cursos (Coordinación Académica)
            Route::get('academic_coordination/curriculum_planning/training_project/quarterlie/index/{training_project_id}/{course_id}', 'training_project_quarterlie_index')->name('sigac.academic_coordination.curriculum_planning.training_project.quarterlie.index'); // Vista trimestralización del curso (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/training_project/store', 'training_project_store')->name('sigac.academic_coordination.curriculum_planning.training_project.store'); // Registrar proyecto formativo (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/training_project/update', 'training_project_update')->name('sigac.academic_coordination.curriculum_planning.training_project.update'); // Actualizar proyecto formativo (Coordinación Académica)
            Route::delete('academic_coordination/curriculum_planning/training_project/destroy/{id}', 'training_project_destroy')->name('sigac.academic_coordination.curriculum_planning.training_project.destroy'); // Eliminar proyecto formativo (Coordinación Académica)

            // ---------------- Trimestralización ---------------------------
            Route::get('academic_coordination/curriculum_planning/quarterlie/filter/learning', 'quarterlie_filterlearning')->name('sigac.academic_coordination.curriculum_planning.quarterlie.filterlearning'); // Consultar resultados de aprendizaje por competencia (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/quarterlie/store', 'quarterlie_store')->name('sigac.academic_coordination.curriculum_planning.quarterlie.store'); // Registrar trimestralizaciòn (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/quarterlie/destroy/{id}', 'quarterlie_destroy')->name('sigac.academic_coordination.curriculum_planning.quarterlie.destroy'); // Eliminar trimestralizaciòn (Coordinación Académica)

            /* Route::post('academic_coordination/curriculum_planning/quarterlie/update/{id}', 'quarterlie_update')->name('sigac.academic_coordination.curriculum_planning.quarterlie.update'); // Registrar Trimestralización (Coordinación Académica) */
            /* Route::get('academic_coordination/curriculum_planning/quarterlie/index', 'quarterlie_index')->name('sigac.academic_coordination.curriculum_planning.quarterlie.index'); // Trimestralización (Coordinación Académica) */
            /* Route::get('academic_coordination/curriculum_planning/quarterlie/create/{quarter_number}/{training_project_id}/{programId}', 'quarterlie_create')->name('sigac.academic_coordination.curriculum_planning.quarterlie.create'); // Fromulario de registro (Coordinación Académica) */
            /* Route::get('academic_coordination/curriculum_planning/quarterlie/edit/{id}', 'quarterlie_edit')->name('sigac.academic_coordination.curriculum_planning.quarterlie.edit'); // Fromulario de registro (Coordinación Académica) */
            /* Route::get('academic_coordination/curriculum_planning/quarterlie/filterlearnin_outcome', 'quarterlie_filterlearnin_outcome')->name('sigac.academic_coordination.curriculum_planning.quarterlie.filterlearnin_outcome'); // Fromulario de registro (Coordinación Académica) */


            Route::get('academic_coordination/curriculum_planning/quarterlie/load/create/{course_id}/{training_project_id}', 'quarterlie_load_create')->name('sigac.academic_coordination.curriculum_planning.quarterlie.load.create'); // Vista carge de trimestralizaciòn (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/quarterlie/load/store', 'quarterlie_load_store')->name('sigac.academic_coordination.curriculum_planning.quarterlie.load.store'); // Registrar trimestralizaciones cargadas (Coordinación Académica)            

            //Profession x Competencia 
            Route::get('academic_coordination/curriculum_planning/assign_learning_outcomes/competencie_profession_index', 'competencie_profession_index')->name('sigac.academic_coordination.curriculum_planning.assign_learning_outcomes.competencie_profession_index'); // Vista asignacion de profesion por competencia (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/assign_learning_outcomes/competencie_profession_table', 'competencie_profession_table')->name('sigac.academic_coordination.curriculum_planning.assign_learning_outcomes.competencie_profession.table'); // Consultar profesiones asignadas por programa (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/assign_learning_outcomes/competencie_profession_store', 'competencie_profession_store')->name('sigac.academic_coordination.curriculum_planning.assign_learning_outcomes.competencie_profession_store'); // Asignar profesion a la competencia (Coordinación Académica)
            Route::get('academic_coordination/curriculum_planning/assign_learning_outcomes/competencie_profession_search/{id}', 'competencie_profession_search')->name('sigac.academic_coordination.curriculum_planning.assign_learning_outcomes.competencie_profession_search'); // Actualizar la consulta profesiones asignadas (Coordinación Académica)
            Route::delete('academic_coordination/curriculum_planning/assign_learning_outcomes/competencie_profession_destroy/{competencie_id}/{profession_id}', 'competencie_profession_destroy')->name('sigac.academic_coordination.curriculum_planning.assign_learning_outcomes.competencie_profession_destroy'); // Eliminar asociación de la profesion asignada con la competencia (Coordinación Académica)

            // Curso x Proyecto formativo
            Route::get('academic_coordination/curriculum_planning/course_trainig_project/course_training_project_index', 'course_training_project_index')->name('sigac.academic_coordination.curriculum_planning.course_trainig_project.index'); // Vista asociacion de curso por proyecto formativo (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/course_trainig_project/table', 'course_training_project_table')->name('sigac.academic_coordination.curriculum_planning.course_trainig_project.table'); // Consulta de los cursos por proyecto formativo (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/course_trainig_project/course_training_project_store', 'course_training_project_store')->name('sigac.academic_coordination.curriculum_planning.course_trainig_project.store'); // Asociar curso al proyecto formativo (Coordinación Académica)
            Route::delete('academic_coordination/curriculum_planning/course_trainig_project/course_training_project_destroy/{training_project_id}/{course_id}', 'course_training_project_destroy')->name('sigac.academic_coordination.curriculum_planning.course_trainig_project.destroy'); // Eliminar asociacion del curso con el proyecto formativo (Coordinación Académica)


            // Competencia por calse de ambiente
            Route::get('academic_coordination/curriculum_planning/learning_class/index', 'competencie_class_index')->name('sigac.academic_coordination.curriculum_planning.competencie_class.index'); // Vista asociacion de competencia por clase de ambiente (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/learning_class/learning_outcome/learning_class_store', 'competencie_class_store')->name('sigac.academic_coordination.curriculum_planning.competencie_class.store'); // Asociar la competencia a la clase de ambiente (Coordinación Académica)
            Route::delete('academic_coordination/curriculum_planning/learning_class/destroy/{class_environment_id}/{competencie_id}', 'competencie_class_destroy')->name('sigac.academic_coordination.curriculum_planning.competencie_class.destroy'); // Eliminar asociacion de la competencia con la clase de ambiente (Coordinación Académica)

            // ---------------- Juicio Evaluativo ---------------------------
            Route::get('academic_coordination/curriculum_planning/evaluative_judgment/index', 'evaluative_judgment_index')->name('sigac.academic_coordination.curriculum_planning.evaluative_judgment.index'); // Proyecto formativo (Coordinación Académica)
            Route::get('academic_coordination/curriculum_planning/evaluative_judgment/load/create', 'evaluative_judgment_create')->name('sigac.academic_coordination.curriculum_planning.evaluative_judgment.load.create'); // Proyecto formativo (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/evaluative_judgment/load/store', 'evaluative_judgment_store')->name('sigac.academic_coordination.curriculum_planning.evaluative_judgment.load.store'); // Proyecto formativo (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/evaluative_judgment/search', 'evaluative_judgment_search')->name('sigac.academic_coordination.curriculum_planning.evaluative_judgment.search'); // Proyecto formativo (Coordinación Académica)
            Route::post('academic_coordination/curriculum_planning/evaluative_judgment/filter', 'evaluative_judgment_filter')->name('sigac.academic_coordination.curriculum_planning.evaluative_judgment.filter'); // Proyecto formativo (Coordinación Académica)
        });

        // RUTAS GESTION DE ASISTENCIAS
        Route::controller(AttendanceController::class)->group(function () {
            // ---------------- Asistencia ---------------------------
            Route::get('instructor/attendances/attendance/index', 'attendance_index')->name('sigac.instructor.attendances.attendance.index'); // Vista registro de asistencia (Instructor)
            Route::get('instructor/attendances/attendance/search', 'attendance_search')->name('sigac.instructor.attendances.attendance.search'); // Consultar asistencia (Instructor)
            Route::get('instructor/attendances/attendance/store', 'attendance_store')->name('sigac.instructor.attendances.attendance.store'); // Registra asistencia del aprendiz (Instructor)
            Route::get('academic_coordination/reports/attendance', 'reports_attendance')->name('sigac.academic_coordination.reports.attendance.index'); // Vista principal de la sección de reportes de asistencia (Coordinación Académica)

            /* Route::get('instructor/consult/excuses', 'consult_excuses')->name('sigac.instructor.attendance.excuses'); // Consultar excusas de aprendiz (Instructor) */
            /* Route::get('instructor/consult/attendance', 'consult_attendance')->name('sigac.instructor.attendance.consult'); // Consultar asistencia por aprendiz o tituladas (Instructor) */
            /* Route::get('instructor/register', 'index')->name('sigac.instructor.attendance.register'); // Registrar asistencia de aprendiz por titulada (Instructor) */
            /* Route::get('wellness/consult/attendance', 'consult_attendance')->name('sigac.wellness.attendance.consult'); // Consultar asistencia por aprendiz o tituladas (Bienestar) */
            /* Route::get('instructor/reports/attendance', 'reports_attendance')->name('sigac.instructor.reports.attendance.index'); // Vista principal de la sección de reportes de asistencia (Instructor) */
            /* Route::get('wellness/reports/attendance', 'reports_attendance')->name('sigac.wellness.reports.attendance.index'); // Vista principal de la sección de reportes de asistencia (Bienestar) */
        });

        // RUTAS GESTION DE APRENDICES
        Route::controller(ApprenticeController::class)->group(function () {
            Route::get('apprentice/excuses', 'send_excuses')->name('sigac.apprentice.excuses.send'); // Enviar excusa para justificación de inasistencia (Aprendiz)
        });

        // RUTAS GESTION DE INSTRUCTORES
        Route::controller(InstructorManagementController::class)->group(function () {

            // Gestion de Instructores
            Route::get('academic_coordination/human_talent/management_instructor/profession_instructor_index', 'profession_instructor_index')->name('sigac.academic_coordination.human_talent.management_instructor.profession_instructor.index'); // Vista asociacion de instructores por profesion (Coordinación Académica)
            Route::post('academic_coordination/human_talent/management_instructor/profession_instructor_store', 'profession_instructor_store')->name('sigac.academic_coordination.human_talent.management_instructor.profession_instructor.store'); // Asociar profesion al instructor (Coordinación Académica)
            Route::delete('academic_coordination/human_talent/management_instructor/profession_instructor_destroy/{id}', 'profession_instructor_destroy')->name('sigac.academic_coordination.human_talent.management_instructor.profession_instructor.destroy'); // Eliminar asociación de la profesion y el instructor (Coordinación Académica)

            // Resultados de aprendizaje x instructor
            Route::get('academic_coordination/human_talent/assign_learning_outcomes/learning_out_people_index', 'learning_out_people_index')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.index'); // Vista asociacion de resultado de aprendizaje por instructor (Coordinación Académica)
            Route::post('academic_coordination/human_talent/assign_learning_outcomes/table', 'learning_out_people_table')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.table'); // Consultar asociacion de instructores por resultado de aprendizaje (Coordinación Académica)
            Route::post('academic_coordination/human_talent/assign_learning_outcomes/learning_out_people_store', 'learning_out_people_store')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.store'); // Asociar resultado de aprendizaje al instructor (Coordinación Académica)
            Route::get('academic_coordination/human_talent/assign_learning_outcomes/learning_out_people_search_instructor/{id}', 'learning_out_people_search_instructor')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.search_instructor'); // Consultar instructor apto para el resultado de aprendizaje (Coordinación Académica)
            Route::get('academic_coordination/human_talent/assign_learning_outcomes/learning_out_people_search_competencie/{id}', 'learning_out_people_search_competencie')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.search_competencie'); // Consultar competencias del programa (Coordinación Académica)
            Route::get('academic_coordination/human_talent/assign_learning_outcomes/learning_out_people_search_learning_outcome/{id}', 'learning_out_people_search_learning_outcome')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.search_learning_outcome'); // Consultar resultado de aprendizaje (Coordinación Académica)
            Route::delete('academic_coordination/human_talent/assign_learning_outcomes/learning_out_people_destroy/{learning_outcome_person_id}', 'learning_out_people_destroy')->name('sigac.academic_coordination.human_talent.assign_learning_outcomes.destroy'); // Eliminar asociación del resultado de aprendizaje al instructor (Coordinación Académica)

        });

        // RUTAS CONTROL DE AMBIENTES
        Route::controller(EnvironmentControlController::class)->group(function () {

            // Entrada inventario
            Route::get('instructor/environmentcontrol/environment_inventory_movement/entrance/index', 'entrance_index')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.entrance.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::post('instructor/environmentcontrol/environment_inventory_movement/entrance/store', 'entrance_store')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.entrance.store'); // Vista reporte trimestralización (Coordinación Académica)

            // Movimiento interno de inventario
            Route::get('instructor/environmentcontrol/environment_inventory_movement/exit/index', 'exit_index')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.exit.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::get('instructor/environmentcontrol/environment_inventory_movement/exit/searchelement', 'exit_searchelement')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.exit.searchelement'); // Vista reporte trimestralización (Coordinación Académica)
            Route::post('instructor/environmentcontrol/environment_inventory_movement/exit/store', 'exit_store')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.exit.store'); // Vista reporte trimestralización (Coordinación Académica)

            // Asignacion de bodegas a ambientes
            Route::get('instructor/environmentcontrol/assign_environment_warehouse/index', 'assign_environment_warehouse_index')->name('sigac.instructor.environmentcontrol.assign_environment_warehouse.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::post('instructor/environmentcontrol/assign_environment_warehouse/store', 'assign_environment_warehouse_store')->name('sigac.instructor.environmentcontrol.assign_environment_warehouse.store'); // Vista reporte trimestralización (Coordinación Académica)
            Route::delete('instructor/environmentcontrol/assign_environment_warehouse/{environment_id}/{warehouse_id}', 'assign_environment_warehouse_destroy')->name('sigac.instructor.environmentcontrol.assign_environment_warehouse.destroy'); // Eliminar asociación de la profesion a

            Route::get('instructor/environmentcontrol/environment_inventory_movement/check/index', 'check_index')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.check.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::get('instructor/environmentcontrol/environment_inventory_movement/check/searchelement', 'check_searchinventory')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.check.searchinventory'); // Vista reporte trimestralización (Coordinación Académica)
            Route::get('instructor/environmentcontrol/environment_inventory_movement/check/searchperson', 'check_searchperson')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.check.searchperson'); // Vista reporte trimestralización (Coordinación Académica)
            Route::post('instructor/environmentcontrol/environment_inventory_movement/check/store', 'check_store')->name('sigac.instructor.environmentcontrol.environment_inventory_movement.check.store'); // Vista reporte trimestralización (Coordinación Académica)

            Route::get('securitystaff/environmentcontrol/environment_inventory_movement/check_pending/index', 'check_pending_index')->name('sigac.securitystaff.environmentcontrol.environment_inventory_movement.check.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::get('securitystaff/environmentcontrol/environment_inventory_movement/check_pending/searchelement', 'check_searchinventory')->name('sigac.securitystaff.environmentcontrol.environment_inventory_movement.check.searchinventory'); // Vista reporte trimestralización (Coordinación Académica)
            Route::get('securitystaff/environmentcontrol/environment_inventory_movement/check_pending/searchperson', 'check_searchperson')->name('sigac.securitystaff.environmentcontrol.environment_inventory_movement.check.searchperson'); // Vista reporte trimestralización (Coordinación Académica)
            Route::post('securitystaff/environmentcontrol/environment_inventory_movement/check_pending/store', 'check_store')->name('sigac.securitystaff.environmentcontrol.environment_inventory_movement.check.store'); // Vista reporte trimestralización (Coordinación Académica)

        });

        // RUTAS GESTION DE REPORTES
        Route::controller(ReportController::class)->group(function () {

            // Reporte trimestralización
            Route::get('academic_coordination/reports/quarterlies/index', 'report_quarterlie_index')->name('sigac.academic_coordination.reports.quartelies.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::get('instructor/reports/quarterlies/index', 'report_quarterlie_index')->name('sigac.instructor.reports.quartelies.index'); // Vista reporte trimestralización (Coordinación Académica)
            Route::post('academic_coordination/reports/quarterlies/search', 'report_quarterlie_search')->name('sigac.academic_coordination.reports.quartelies.search'); // Consultar trimestralización del curso (Coordinación Académica)
            Route::post('instructor/reports/quarterlies/search', 'report_quarterlie_search')->name('sigac.instructor.reports.quartelies.search'); // Consultar trimestralización del curso (Coordinación Académica)

            // ---------------- Consultar datos de instructores --------------------
            Route::get('academic_coordination/reports/instructors/index', 'instructors_index')->name('sigac.academic_coordination.reports.instructors.index'); // Reporte de instructores (Coordinación Académica)
            Route::post('academic_coordination/reports/instructors/search', 'instructors_search')->name('sigac.academic_coordination.reports.instructors.search'); // Consultar datos de instructores (Coordinación Académica)

            // ---------------- Consultar disponibilidad de ambientes --------------------
            Route::get('academic_coordination/reports/environments/index', 'environments_index')->name('sigac.academic_coordination.reports.environments.index'); // Ambientes (Coordinación Académica)
            Route::post('academic_coordination/reports/environments/search', 'environments_search')->name('sigac.academic_coordination.reports.environments.search'); // Consultar ambientes (Coordinación Académica)
            Route::get('academic_coordination/reports/environments/search_person', 'search_person')->name('sigac.academic_coordination.reports.environments.search_person'); // Buscar personas (Coordinación Académica)
            Route::post('academic_coordination/reports/environments/institucional_request_store', 'institucional_request_store')->name('sigac.academic_coordination.reports.environments.institucional_request_store'); // Guardar reprogramacion (Coordinación Académica)

            Route::get('academic_coordination/reports/active_courses/index', 'active_courses_index')->name('sigac.academic_coordination.reports.active_courses.index'); // Ambientes (Coordinación Académica)
            Route::post('academic_coordination/reports/active_courses/search', 'active_courses_search')->name('sigac.academic_coordination.reports.active_courses.search'); // Consultar ambientes (Coordinación Académica)

        });
        //Solicitud visita
        Route::controller(VisitRequestController::class)->group(function () {
            Route::get('academic_coordination/visit-request/index', 'application_index')->name('sigac.academic_coordination.visitrequest.index');
            Route::get('academic_coordination/visit-request/create', 'application_create')->name('sigac.academic_coordination.visitrequest.create');
            Route::post('academic_coordination/visit-request/store', 'application_store')->name('sigac.academic_coordination.visitrequest.store');
            Route::post('academic_coordination/visit-request/update', 'application_update')->name('sigac.academic_coordination.visitrequest.update');
            // Route::get('academic_coordination/visit-request/visits/report', 'form') ->name('visits.report.form');
            //Route::post('academic_coordination/visit-request/visits/report/export',  'export')->name('sigac.academic_coordination.visits.report.export]');

        });

        //Agendar Visitas
        Route::controller(VisitScheduleController::class)->group(function () {
            Route::get('academic_coordination/visit-schedule/events', 'events')->name('sigac.academic_coordination.visitschedule.events');
            Route::get('academic_coordination/visit-schedule/create/{request}', 'create')->name('sigac.academic_coordination.visitschedule.create');
            Route::post('academic_coordination/visit-schedule/store', 'store')->name('sigac.academic_coordination.visitschedule.store');
            Route::post('academic_coordination/visit/environments/search', 'available_environments')->name('sigac.academic_coordination.visit.environments.search');
            Route::get('academic_coordination/visit/staff/search', 'searchStaff')->name('sigac.academic_coordination.visit.staff.search');
            Route::get('academic_coordination/visit-schedule/calendar/{request}', 'calendar')->name('sigac.academic_coordination.visitschedule.calendar');
            Route::get('academic_coordination/visit-schedule/events/{request}', 'eventsByRequest')->name('sigac.academic_coordination.visitschedule.events.byrequest');
            Route::get('academic_coordination/visit-schedule/calendar', 'calendarAll')->name('sigac.academic_coordination.visitschedule.calendar.general');
            Route::get('academic_coordination/visit-schedule/events', 'eventsAll')->name('sigac.academic_coordination.visitschedule.events.all');
            Route::post('academic_coordination/visit-request/{visit}/notify', 'notify')->name('sigac.academic_coordination.visitrequest.notify');
            Route::post('academic_coordination/sigac/academic_coordination/visit-schedule/{schedule}/update', 'update')->name('sigac.academic_coordination.visitschedule.update');
            Route::post('academic_coordination/visit-schedule/{schedule}/cancel', 'cancel')->name('sigac.academic_coordination.visitschedule.cancel');
            Route::get('academic_coordination/people/{person}/emails', 'personEmails')->name('sigac.academic_coordination.people.emails'); // GET /sigac/people/{person}/emails
            Route::get('visitas/ver/{schedule}', 'publicView')->name('cefa.sigac.visit.public')->middleware('signed');
            Route::get('academic_coordination/visitas/people-list/html/{visit}', 'previewPeopleListHtml')->name('sigac.academic_coordination.visits.peoplelist.preview')->middleware('signed');
            Route::get('securitystaff/visits/today', 'securityToday')->name('sigac.securitystaff.visits.today');
            Route::post('securitystaff/visits/{schedule}/check-in',  'securityCheckIn')->name('sigac.securitystaff.visits.checkin');
            Route::post('securitystaff/visits/{schedule}/check-out', 'securityCheckOut')->name('sigac.securitystaff.visits.checkout');
            Route::post('sigac/academic_coordination/visit-schedule/{schedule}/security-authorize', 'sendSecurityAuthorization')->name('sigac.academic_coordination.visitschedule.security_authorize');
            Route::get('academic_coordination/visitschedule/{schedule}/authorization-pdf', 'authorizationPdf')->name('sigac.academic_coordination.visitschedule.authorization_pdf');
            Route::post('visitschedule/{schedule}/security-authorize', 'securityAuthorize')->name('sigac.academic_coordination.visitschedule.security_authorize');
            Route::get('academic_coordination/visit-request/visits/report', 'form')->name('visits.report.form');
            Route::post('academic_coordination/visit-request/visits/report/export',  'export')->name('sigac.academic_coordination.visits.report.export]');
        });
        Route::controller(ReportFichaController::class)->group(function () {

            // Formulario: seleccionar ficha e instructor
            Route::get('academic_coordination/ficha', 'form')->name('sigac.academic_coordination.reports.ficha.form');
            Route::get('academic_coordination/ficha/{ficha}/instructors', 'instructorsByFicha')->name('sigac.academic_coordination.reports.ficha.instructors');
            Route::post('academic_coordination/reports/fichas/export', 'export')->name('sigac.academic_coordination.reports.fichas.export');
            Route::post('academic_coordination/reports/fichas/preview', 'preview')->name('sigac.academic_coordination.reports.fichas.preview');
            Route::get('/sigac/fichas/buscar', 'buscarFichaAjax')->name('sigac.fichas.buscar');
        });

        Route::controller(PermissionValidationController::class)->group(function () {
            // INSTRUCTOR
            Route::prefix('instructor/apprentice_permissions')->group(function () {
                Route::get('index', 'index')->name('sigac.instructor.PermissionValidation.index');
                Route::post('post', 'store')->name('sigac.instructor.PermissionValidation.store');
                Route::post('cancel', 'cancel')->name('sigac.instructor.PermissionValidation.cancel');
                // Mostrar historial
                Route::get('instructorValidationHistory', 'instructorValidationHistory')->name('sigac.instructor.PermissionValidation.instructorValidationHistory');

                // Actualizar validación (PUT con id)
                Route::put('instructorValidationHistory/{id}', 'instructorUpdateValidation')->name('sigac.instructor.PermissionValidation.instructorUpdateValidation');

                Route::get('instructor/apprentice_permissions/{permission}/evidence', 'showEvidence')->name('sigac.instructor.PermissionValidation.evidence');
            });

            // TUTOR
            Route::prefix('tutor/apprentice_permissions')->group(function () {
                Route::get('index', 'index')->name('sigac.tutor.PermissionValidation.index');
                Route::post('post', 'store')->name('sigac.tutor.PermissionValidation.store');
                Route::post('cancel', 'cancel')->name('sigac.tutor.PermissionValidation.cancel');
                Route::get('tutorValidationHistory', 'tutorValidationHistory')->name('sigac.tutor.PermissionValidation.tutorValidationHistory');
                Route::put('tutorValidationHistory/{id}', 'tutorUpdateValidation')->name('sigac.tutor.PermissionValidation.tutorUpdateValidation');
                Route::get('instructor/apprentice_permissions/{permission}/evidence', 'showEvidence')->name('sigac.tutor.PermissionValidation.evidence');
            });

            // BIENESTAR
            Route::prefix('bienestar/apprentice_permissions')->group(function () {
                Route::get('index', 'index')->name('sigac.bienestar.PermissionValidation.index');
                Route::post('post', 'store')->name('sigac.bienestar.PermissionValidation.store');
                Route::post('cancel', 'cancel')->name('sigac.bienestar.PermissionValidation.cancel');
                Route::get('wellnessValidationHistory', 'wellnessValidationHistory')->name('sigac.wellness.PermissionValidation.wellnessValidationHistory');
                Route::put('wellnessValidationHistory/{id}', 'wellnessUpdateValidation')->name('sigac.wellness.PermissionValidation.wellnessUpdateValidation');
                Route::get('/apprentice_permissions/{permission}/evidence', 'showEvidence')->name('sigac.wellness.PermissionValidation.evidence');
            });

            // COORDINADOR
            Route::prefix('coordinador/apprentice_permissions')->group(function () {
                Route::get('index', 'index')->name('sigac.coordinador.PermissionValidation.index');
                Route::post('post', 'store')->name('sigac.coordinador.PermissionValidation.store');
                Route::post('cancel', 'cancel')->name('sigac.coordinador.PermissionValidation.cancel');
                Route::get('academicCoordinationValidationHistory', 'academicCoordinationValidationHistory')->name('sigac.academic_coordination.PermissionValidation.academicCoordinationValidationHistory');
                Route::put('academicCoordinationUpdateValidation/{id}', 'academicCoordinationUpdateValidation')->name('sigac.academic_coordination.PermissionValidation.academicCoordinationUpdateValidation');
                Route::get('/apprentice_permissions/{permission}/evidence', 'showEvidence')->name('sigac.coordinador.PermissionValidation.evidence');
            });
            Route::prefix('securitypersoneel/apprentice_permissions')->group(function () {
                Route::get('index', 'index_security_personnel')->name('sigac.security.personnel.permission.index');
            });
        });
        Route::controller(ApprenticePermissionsController::class)->group(function () {
            Route::get('/aprendices/permission', 'index')->name('sigac.apprentice.permission.index');
            Route::get('/aprendices/get-instructor', 'getInstructor')->name('sigac.apprentice.permission.getInstructor');
            Route::post('/aprendices/post-instructor', 'store')->name('sigac.apprentice.permission.store');
            Route::get('/apprentice/permissions/status', 'statuses')->name('sigac.apprentice.permission.statuses');
            Route::put('/permissions/{id}/cancel', 'cancel')->name('sigac.apprentice.permission.cancel');
        });

        Route::controller(EnvironmentRoundController::class)->group(function () {
            Route::prefix('coordinador/environment_rounds')->group(function () {
                Route::get('index', 'index')->name('sigac.coordinador.environment_rounds.index');
                Route::post('create-or-show', 'createOrShow')->name('sigac.coordinador.environment_rounds.create');
                Route::get('show/{round}', 'show')->name('sigac.coordinador.environment_rounds.show');
                Route::post('lock/{round}', 'lock')->name('sigac.coordinador.environment_rounds.lock');
            });
        });

        Route::controller(EnvironmentRoundEntryController::class)->group(function () {
            Route::prefix('coordinador/environment_rounds/entries')->group(function () {
                Route::put('{entry}/update', 'update')->name('sigac.coordinador.environment_round_entries.update');
            });
        });
        -Route::controller(EnvironmentIncidentController::class)->group(function () {
            Route::prefix('coordinador/environment_incidents')->group(function () {
                Route::get('index', 'index')->name('sigac.coordinador.environment_incidents.index');
                Route::put('{incident}/update-status', 'updateStatus')->name('sigac.coordinador.environment_incidents.updateStatus');
            });

            Route::prefix('instructor/environment_incidents')->group(function () {
                Route::post('store', 'store')->name('sigac.instructor.environment_incidents.store');
            });
        });
        Route::controller(EnvironmentKeyLogController::class)->group(function () {
            Route::get('environment_keys', 'index')
                ->name('sigac.coordinador.environment_keys.index');

            Route::post('environment_keys/change-environment', 'changeEnvironment')
                ->name('sigac.coordinador.environment_keys.change_environment');

            Route::post('environment_keys/deliver', 'deliverKey')
                ->name('sigac.coordinador.environment_keys.deliver');

            Route::post('environment_keys/return', 'returnKey')
                ->name('sigac.coordinador.environment_keys.return');
        });
    });
});
