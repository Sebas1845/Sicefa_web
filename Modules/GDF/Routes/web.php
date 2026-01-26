<?php

use Illuminate\Support\Facades\Route;
use Modules\GDF\Http\Controllers\GDFController;
use Modules\GDF\Http\Controllers\Admin\AdminController;
use Modules\GDF\Http\Controllers\Subdirection\SubdirectionController;
use Modules\GDF\Http\Controllers\Subdirection\AreaController;
use Modules\GDF\Http\Controllers\Subdirection\AreaUserController;
use Modules\GDF\Http\Controllers\Subdirection\MotorcycleQuotaController;
use Modules\GDF\Http\Controllers\Support\SupportDashboardController;
use Modules\GDF\Http\Controllers\Subdirection\AreaBudgetItemController;
use Modules\GDF\Http\Controllers\Subdirection\SubdirectionMotorcyclesController;
use Modules\GDF\Http\Controllers\Coordination\GdfReviewController;
use Modules\GDF\Http\Controllers\Treasury\TreasuryController;
use Modules\GDF\Http\Controllers\Coordination\CoordinationController;
use Modules\GDF\Http\Controllers\Coordination\CoordinationPeopleController;
use Modules\GDF\Http\Controllers\Coordination\PasswordController;
use Modules\GDF\Http\Controllers\Coordination\CoordinationMotorcyclesController;
use Modules\GDF\Http\Controllers\Coordination\MotorcycleAssignmentController;
use Modules\GDF\Http\Controllers\instructor\OfficialController;
use Modules\GDF\Http\Controllers\Support\SupportController;
use Modules\GDF\Http\Controllers\Support\SupportMotorcyclesController;
use Modules\GDF\Http\Controllers\instructor\CatalogController;
use Modules\GDF\Http\Controllers\instructor\MotorcycleController;


/*
|--------------------------------------------------------------------------
| Alias de compatibilidad (opcional)
|--------------------------------------------------------------------------
| Si en algún lado del proyecto existe route('cefa.gdf.index'), aquí lo defines
| sin que quede prefijado por "gdf.".
*/

Route::get('/gdf/index', [GDFController::class, 'index'])->name('cefa.gdf.index');

Route::prefix('gdf')->name('gdf.')->group(function () {

    // Home real del módulo
    Route::get('index', [GDFController::class, 'index'])->name('index'); // gdf.index

    // Acceso por token único (Administrador)
    Route::get('access/{token}', [AdminController::class, 'accessByToken'])->name('access.by_token');
    Route::get('security/create-password', [AdminController::class, 'password_create_form'])->name('security.password.create');
    Route::post('security/create-password', [AdminController::class, 'password_create_store'])->name('security.password.store');

    Route::get('coordination/magic/{token}', [PasswordController::class, 'magic'])->name('security.magic');
    Route::get('coordination/create-password', [PasswordController::class, 'create'])->name('coordination.password.create');
    Route::post('coordination/create-password', [PasswordController::class, 'store'])->name('coordination.password.store');

    Route::middleware(['web', 'auth'])->group(function () {

        /*
        |--------------------------------------------------------------------------
        | Gateway (SOLO Instructor + Coord/Support)
        |--------------------------------------------------------------------------
        | Subdirección/Tesorería/Admin NO usan gateway.
        */
        // Gateway
        Route::get('gateway', [GDFController::class, 'gateway'])->middleware('gdf.role:role=gdf.instructor|gdf.academic_coordinator|gdf.academic_support|gdf.campesena_coordinator|gdf.campesena_support|gdf.treasury|gdf.subdirection|gdf.superadmin')->name('gateway');
        Route::post('gateway/select', [GDFController::class, 'select'])->middleware('gdf.role:role=gdf.instructor|gdf.academic_coordinator|gdf.academic_support|gdf.campesena_coordinator|gdf.campesena_support|gdf.treasury|gdf.subdirection|gdf.superadmin')->name('gateway.select');

        Route::post('gateway/clear', [GDFController::class, 'clearContext'])->middleware('auth')->name('gateway.clear');

        // Seguridad password
        Route::get('security/create-password', [AdminController::class, 'password_create_form'])->middleware('auth')->name('security.password.create');

        Route::post('security/create-password', [AdminController::class, 'password_create_store'])->middleware('auth')->name('security.password.store');






        // Admin
        Route::prefix('admin')->name('admin.')->middleware('gdf.role:role=gdf.admin')->group(function () {
            Route::get('dashboard', [AdminController::class, 'index'])->name('dashboard');
            Route::get('users', [AdminController::class, 'index_create_user'])->name('users.index');
            Route::post('users/assign-role', [AdminController::class, 'assignRole'])->name('users.assignRole');
            Route::post('users/revoke-role', [AdminController::class, 'revokeRole'])->name('users.revokeRole');
            Route::post('users/create-from-contractor', [AdminController::class, 'createFromContractor'])->name('users.createFromContractor');
            Route::get('users/import', [AdminController::class, 'import_users_index'])->name('users.import.index');
            Route::post('users/import/create', [AdminController::class, 'import_users_create'])->name('users.import.create');
            Route::post('users/create-by-document', [AdminController::class, 'createUserByDocument'])->name('users.createByDocument');
            Route::post('users/{user}/send-reset-link', [AdminController::class, 'sendResetLink'])->name('users.sendResetLink');
        });

        Route::prefix('support')->name('support.')->middleware('gdf.role:role=gdf.academic_support|gdf.campesena_support')->group(function () {
            Route::get('support/dashboard', [SupportDashboardController::class, 'dashboard'])->name('support.dashboard');
            Route::get('motorcycles/queue', [SupportMotorcyclesController::class, 'queue'])->name('motorcycles.queue');
            Route::post('motorcycles/assign/{assignment}', [SupportMotorcyclesController::class, 'assign'])->name('motorcycles.assign');
            Route::post('motorcycles/deliver/{assignment}', [SupportMotorcyclesController::class, 'deliver'])->name('motorcycles.deliver');
            Route::post('motorcycles/return/{assignment}', [SupportMotorcyclesController::class, 'return'])->name('motorcycles.return');
            Route::post('motorcycles/cancel/{assignment}', [SupportMotorcyclesController::class, 'cancel'])->name('motorcycles.cancel');
        });


        Route::prefix('treasury')->name('treasury.')
            ->middleware('gdf.role:role=gdf.treasury')
            ->group(function () {
                Route::get('review', [TreasuryController::class, 'index'])->name('dashboard');
                Route::get('requests',               [GdfTreasuryRequestsController::class, 'index'])->name('requests.index');
                Route::get('requests/{id}',          [GdfTreasuryRequestsController::class, 'show'])->name('requests.show');
                Route::post('requests/{id}/approve', [GdfTreasuryRequestsController::class, 'approve'])->name('requests.approve');
                Route::post('requests/{id}/return',  [GdfTreasuryRequestsController::class, 'return'])->name('requests.return');
                Route::post('requests/{id}/reject',  [GdfTreasuryRequestsController::class, 'reject'])->name('requests.reject');
            });


        // Coordinación Académica (requiere área academic)
        Route::prefix('academic')->name('academic.')
            ->middleware('gdf.role:role=gdf.academic_coordinator|gdf.academic_support,ctx=coord|support,area=academic')
            ->group(function () {

                Route::get('dashboard', [GDFController::class, 'academic_coordination'])->name('dashboard');

                Route::get('review', [CoordinationController::class, 'index'])->name('review');
                Route::post('review/{id}/approve', [CoordinationController::class, 'approve'])->name('review.approve');
                Route::post('review/{id}/return',  [CoordinationController::class, 'return'])->name('review.return');
                Route::post('review/{id}/reject',  [CoordinationController::class, 'reject'])->name('review.reject');

                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::get('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');
                Route::post('people',       [CoordinationPeopleController::class, 'store'])->name('people.store');

                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.by_rubro');
                Route::get('budget-items', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('budget_items.by_area');
                Route::get('/gdf/auth/magic/{token}', [CoordinationPeopleController::class, 'magicLink'])->name('gdf.auth.magic');
                Route::post('/gdf/auth/magic/{token}', [CoordinationPeopleController::class, 'setPassword'])->name('gdf.auth.magic.set');

                //MOTOCICLETAS
                Route::get('motorcycles', [CoordinationMotorcyclesController::class, 'index'])->name('motorcycles.index');

                // Asignación directa + cuota status (Coordinación/Apoyo)
                Route::get('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignCreate'])->name('motorcycles.assign.create');
                Route::post('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignStore'])->name('motorcycles.assign.store');
                Route::get('motorcycles/quota-status', [CoordinationMotorcyclesController::class, 'quotaStatus'])->name('motorcycles.quota_status');

                // Búsqueda persona (AJAX)
                Route::get('motorcycles/search-person', [CoordinationMotorcyclesController::class, 'searchPerson'])->name('motorcycles.search_person');

                // Apoyo (operación)
                Route::get('motorcycles/queue', [SupportMotorcyclesController::class, 'queue'])->name('motorcycles.queue');
                Route::post('motorcycles/assign/{assignment}', [SupportMotorcyclesController::class, 'assign'])->name('motorcycles.assign');
                Route::post('motorcycles/deliver/{assignment}', [SupportMotorcyclesController::class, 'deliver'])->name('motorcycles.deliver');
                Route::post('motorcycles/return/{assignment}', [SupportMotorcyclesController::class, 'return'])->name('motorcycles.return');
                Route::post('motorcycles/cancel/{assignment}', [SupportMotorcyclesController::class, 'cancel'])->name('motorcycles.cancel');

                Route::get('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'createWithMotorcycle'])->name('motorcycles.assign_create');
                Route::post('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'storeWithMotorcycle'])->name('motorcycles.assign_create.store');
            });


        Route::prefix('campesena')->name('campesena.')
            ->middleware('gdf.role:role=gdf.campesena_coordinator|gdf.campesena_support,ctx=coord|support,area=campesena')
            ->group(function () {

                Route::get('dashboard', [GDFController::class, 'campesena'])->name('dashboard');

                Route::get('review', [CoordinationController::class, 'index'])->name('review');
                Route::post('review/{id}/approve', [CoordinationController::class, 'approve'])->name('review.approve');
                Route::post('review/{id}/return',  [CoordinationController::class, 'return'])->name('review.return');
                Route::post('review/{id}/reject',  [CoordinationController::class, 'reject'])->name('review.reject');

                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::get('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');
                Route::post('people',       [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.by_rubro');
                Route::get('budget-items', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('budget_items.by_area');
                Route::get('/gdf/auth/magic/{token}', [CoordinationPeopleController::class, 'magicLink'])->name('gdf.auth.magic');
                Route::post('/gdf/auth/magic/{token}', [CoordinationPeopleController::class, 'setPassword'])->name('gdf.auth.magic.set');
                //MOTOCICLETAS
                Route::get('motorcycles', [CoordinationMotorcyclesController::class, 'index'])->name('motorcycles.index');

                // Asignación directa + cuota status (Coordinación/Apoyo)
                Route::get('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignCreate'])->name('motorcycles.assign.create');
                Route::post('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignStore'])->name('motorcycles.assign.store');
                Route::get('motorcycles/quota-status', [CoordinationMotorcyclesController::class, 'quotaStatus'])->name('motorcycles.quota_status');
                Route::get('motorcycles/search-person', [CoordinationMotorcyclesController::class, 'searchPerson'])->name('motorcycles.search_person');
                Route::get('motorcycles/people-in-area', [CoordinationMotorcyclesController::class, 'peopleInArea'])->name('motorcycles.people_in_area');

                // Apoyo (operación)
                Route::get('motorcycles/queue', [SupportMotorcyclesController::class, 'queue'])->name('motorcycles.queue');
                Route::post('motorcycles/assign/{assignment}', [SupportMotorcyclesController::class, 'assign'])->name('motorcycles.assign');
                Route::post('motorcycles/deliver/{assignment}', [SupportMotorcyclesController::class, 'deliver'])->name('motorcycles.deliver');
                Route::post('motorcycles/return/{assignment}', [SupportMotorcyclesController::class, 'return'])->name('motorcycles.return');
                Route::post('motorcycles/cancel/{assignment}', [SupportMotorcyclesController::class, 'cancel'])->name('motorcycles.cancel');

                Route::get('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'createWithMotorcycle'])->name('motorcycles.assign_create');
                Route::post('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'storeWithMotorcycle'])->name('motorcycles.assign_create.store');
            });


        Route::prefix('instructor')
            ->middleware(['auth', 'gdf.role:role=gdf.instructor,ctx=official'])
            ->name('instructor.')
            ->group(function () {
                Route::get('/', [OfficialController::class, 'dashboard'])->name('dashboard');

                Route::get('requests', [OfficialController::class, 'index'])->name('requests.index');
                Route::get('requests/create', [OfficialController::class, 'create'])->name('requests.create');
                Route::post('requests', [OfficialController::class, 'store'])->name('requests.store');
                Route::get('requests/{gdfRequest}', [OfficialController::class, 'show'])->name('requests.show');
                Route::post('requests/{gdfRequest}/submit', [OfficialController::class, 'submit'])->name('requests.submit');
                Route::post('requests/{gdfRequest}/cancel', [OfficialController::class, 'cancel'])->name('requests.cancel');

                // Catalog
                Route::get('catalog/budget-items', [CatalogController::class, 'budgetItems'])->name('catalog.budgetItems');
                Route::get('catalog/departments', [CatalogController::class, 'departments'])->name('catalog.departments');
                Route::get('catalog/municipalities', [CatalogController::class, 'municipalities'])->name('catalog.municipalities');
                Route::get('catalog/villages', [CatalogController::class, 'villages'])->name('catalog.villages');
                Route::get('transport-rate', [CatalogController::class, 'transportRate'])->name('catalog.transportRate');


                // Motos
                Route::get('motorcycle', [MotorcycleController::class, 'index'])->name('motorcycle.index');
                Route::post('motorcycle', [MotorcycleController::class, 'storeRequest'])->name('motorcycle.store');
                Route::post('motorcycle/{assignment}/cancel', [MotorcycleController::class, 'cancel'])->name('motorcycle.cancel');
                Route::get('motorcycle/active', [MotorcycleController::class, 'active'])->name('motorcycle.active'); // opcional
            });

        // Subdirección (NO requiere gateway)
        Route::prefix('subdirection')->name('subdirection.')->middleware('gdf.role:role=gdf.subdirection')->group(function () {

            Route::get('dashboard', [SubdirectionController::class, 'index'])->name('dashboard');
            Route::get('/reports', [SubdirectionController::class, 'reports'])->name('reports');
            Route::get('/reports/summary', [SubdirectionController::class, 'reportsSummary'])->name('reports.summary');
            Route::get('/reports/trend', [SubdirectionController::class, 'reportsTrend'])->name('reports.trend');
            Route::get('/reports/top-destinations', [SubdirectionController::class, 'reportsTopDestinations'])->name('reports.top');
            Route::get('/reports/map-points-gdf', [SubdirectionController::class, 'reportsMapPointsGdf'])->name('reports.map.gdf');
            Route::get('/reports/map-points-sitrav', [SubdirectionController::class, 'reportsMapPointsSitrav'])->name('reports.map.sitrav');
            Route::get('/reports/export/pdf', [SubdirectionController::class, 'exportReportsPdf'])->name('reports.export.pdf');
            Route::get('/reports/export/zip', [SubdirectionController::class, 'exportReportsZip'])->name('reports.export.zip');

            // Solicitudes
            Route::get('requests', [SubdirectionController::class, 'requestsIndex'])->name('requests');
            Route::get('requests/{id}', [SubdirectionController::class, 'requestsShow'])->name('requests.show');
            Route::post('requests/{id}/approve', [SubdirectionController::class, 'requestsApprove'])->name('requests.approve');
            Route::post('requests/{id}/reject', [SubdirectionController::class, 'requestsReject'])->name('requests.reject');
            Route::post('requests/{id}/return', [SubdirectionController::class, 'requestsReturn'])->name('requests.return');

            // Rubros
            Route::get('rubros', [SubdirectionController::class, 'rubrosIndex'])->name('rubros');
            Route::get('rubros/create', [SubdirectionController::class, 'rubrosCreate'])->name('rubros.create');
            Route::post('rubros', [SubdirectionController::class, 'rubrosStore'])->name('rubros.store');
            Route::get('rubros/{id}/edit', [SubdirectionController::class, 'rubrosEdit'])->name('rubros.edit');
            Route::put('rubros/{id}', [SubdirectionController::class, 'rubrosUpdate'])->name('rubros.update');

            // Presupuestos
            Route::get('budgets', [SubdirectionController::class, 'budgetsIndex'])->name('budgets.index');
            Route::get('budgets/create', [SubdirectionController::class, 'budgetsCreate'])->name('budgets.create');
            Route::post('budgets', [SubdirectionController::class, 'budgetsStore'])->name('budgets.store');
            Route::get('budgets/{id}/edit', [SubdirectionController::class, 'budgetsEdit'])->name('budgets.edit');
            Route::put('budgets/{id}', [SubdirectionController::class, 'budgetsUpdate'])->name('budgets.update');

            Route::get('budgets/{id}/percentages', [SubdirectionController::class, 'budgetsPercentagesEdit'])->name('budgets.percentages.edit');
            Route::post('budgets/{id}/percentages', [SubdirectionController::class, 'budgetsPercentagesStore'])->name('budgets.percentages.store');

            Route::patch('areas/{id}/activate', [SubdirectionController::class, 'areasActivate'])->name('areas.activate');
            Route::patch('areas/{id}/deactivate', [SubdirectionController::class, 'areasDeactivate'])->name('areas.deactivate');

            // Instructores / Reportes / Auditoría
            Route::get('instructors', [SubdirectionController::class, 'instructorsIndex'])->name('instructors');
            Route::get('reports', [SubdirectionController::class, 'reportsIndex'])->name('reports');
            Route::get('audit', [SubdirectionController::class, 'auditIndex'])->name('audit');
            Route::get('movements', [SubdirectionController::class, 'movementsIndex'])->name('movements.index');
            Route::get('movements/{id}', [SubdirectionController::class, 'movementsShow'])->name('movements.show');

            // Asignación de Áreas a Presupuestos
            Route::get('budgets/{id}/areas', [SubdirectionController::class, 'budgetsAreasEdit'])->name('budgets.areas.edit');
            Route::post('budgets/{id}/areas', [SubdirectionController::class, 'budgetsAreasStore'])->name('budgets.areas.store');

            // Users
            Route::get('users', [SubdirectionController::class, 'indexuser'])->name('users.index');
            Route::post('users/assign-role', [SubdirectionController::class, 'assignRole'])->name('users.assignRole');
            Route::post('users/revoke-role', [SubdirectionController::class, 'revokeRole'])->name('users.revokeRole');
            Route::post('users/{user}/send-reset-link', [SubdirectionController::class, 'sendResetLink'])->name('users.sendResetLink');
            Route::post('users/create-by-document', [SubdirectionController::class, 'createUserByDocument'])->name('users.createByDocument');
            Route::post('users/import-create', [SubdirectionController::class, 'importUsersCreate'])->name('users.import.create');

            // Gestión de Áreas y Usuarios
            Route::get('area-users', [AreaUserController::class, 'index'])->name('area_users.index');
            Route::post('area-users/assign', [AreaUserController::class, 'assign'])->name('area_users.assign');
            Route::post('area-users/revoke', [AreaUserController::class, 'revoke'])->name('area_users.revoke');

            // Gestión de Áreas (catálogo)
            Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
            Route::get('areas/create', [AreaController::class, 'create'])->name('areas.create');
            Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
            Route::get('areas/{id}/edit', [AreaController::class, 'edit'])->name('areas.edit');
            Route::put('areas/{id}', [AreaController::class, 'update'])->name('areas.update');

            Route::get('areas/{area}/rubros', [AreaBudgetItemController::class, 'edit'])->name('area_budget_items.edit');

            Route::post('areas/{area}/rubros', [AreaBudgetItemController::class, 'update'])->name('area_rubros.update');

            Route::get('motorcycles', [SubdirectionMotorcyclesController::class, 'index'])->name('motorcycles.index');
            Route::get('motorcycles/create', [SubdirectionMotorcyclesController::class, 'create'])->name('motorcycles.create');
            Route::post('motorcycles', [SubdirectionMotorcyclesController::class, 'store'])->name('motorcycles.store');
            Route::post('motorcycles/{motorcycle}/transfer', [SubdirectionMotorcyclesController::class, 'transfer'])->name('motorcycles.transfer');


            Route::get('motorcycles/quotas', [MotorcycleQuotaController::class, 'index'])->name('motorcycles.quotas.index');
            Route::post('motorcycles/quotas', [MotorcycleQuotaController::class, 'store'])->name('motorcycles.quotas.store');
        });
    });
});
