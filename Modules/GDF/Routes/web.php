<?php

use Illuminate\Support\Facades\Route;

use Modules\GDF\Http\Controllers\GDFController;
use Modules\GDF\Http\Controllers\Admin\AdminController;
use Modules\GDF\Http\Controllers\Admin\StorageAdminController;

use Modules\GDF\Http\Controllers\Subdirection\SubdirectionController;
use Modules\GDF\Http\Controllers\Subdirection\AreaController;
use Modules\GDF\Http\Controllers\Subdirection\AreaUserController;
use Modules\GDF\Http\Controllers\Subdirection\MotorcycleQuotaController;
use Modules\GDF\Http\Controllers\Subdirection\AreaBudgetItemController;
use Modules\GDF\Http\Controllers\Subdirection\SubdirectionMotorcyclesController;
use Modules\GDF\Http\Controllers\Subdirection\AuthorizationSignersController;

use Modules\GDF\Http\Controllers\Support\SupportDashboardController;
use Modules\GDF\Http\Controllers\Support\SupportMotorcyclesController;
use Modules\GDF\Http\Controllers\Support\BudgetAdditionController;

use Modules\GDF\Http\Controllers\Treasury\TreasuryController;

use Modules\GDF\Http\Controllers\Coordination\CoordinationController;
use Modules\GDF\Http\Controllers\Coordination\CoordinationPeopleController;
use Modules\GDF\Http\Controllers\Coordination\CoordinationMotorcyclesController;
use Modules\GDF\Http\Controllers\Coordination\PasswordController;

use Modules\GDF\Http\Controllers\Instructor\OfficialController;
use Modules\GDF\Http\Controllers\Instructor\CatalogController;
use Modules\GDF\Http\Controllers\Instructor\MotorcycleController;
use Modules\GDF\Http\Controllers\Instructor\sitraInstructorController;


Route::prefix('gdf')->name('gdf.')->group(function () {

    // Públicas (sin auth)
    Route::get('index', [GDFController::class, 'index'])->name('index');
    Route::get('developers', [GDFController::class, 'developers'])->name('developers');
    Route::get('about', [GDFController::class, 'about'])->name('about');
    Route::get('tech', [GDFController::class, 'tech'])->name('tech');

    Route::get('access/{token}', [AdminController::class, 'accessByToken'])->name('access.by_token');

    Route::get('security/create-password', [AdminController::class, 'password_create_form'])->name('security.password.create');
    Route::post('security/create-password', [AdminController::class, 'password_create_store'])->name('security.password.store');

    Route::get('coordination/magic/{token}', [PasswordController::class, 'magic'])->name('security.magic');
    Route::get('coordination/create-password', [PasswordController::class, 'create'])->name('coordination.password.create');
    Route::post('coordination/create-password', [PasswordController::class, 'store'])->name('coordination.password.store');

    // Protegidas
    Route::middleware(['web', 'auth'])->group(function () {

        // Gateway (selector de contexto)
        Route::get('gateway', [GDFController::class, 'gateway'])
            ->middleware('gdf.role:role=gdf.instructor|gdf.academic_coordinator|gdf.academic_support|gdf.campesena_coordinator|gdf.campesena_support|gdf.treasury|gdf.subdirection|gdf.superadmin')
            ->name('gateway');

        Route::post('gateway/select', [GDFController::class, 'select'])
            ->middleware('gdf.role:role=gdf.instructor|gdf.academic_coordinator|gdf.academic_support|gdf.campesena_coordinator|gdf.campesena_support|gdf.treasury|gdf.subdirection|gdf.superadmin')
            ->name('gateway.select');

        Route::post('gateway/clear', [GDFController::class, 'clearContext'])
            ->name('gateway.clear');

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

            Route::get('storage', [StorageAdminController::class, 'index'])->name('storage.index');
            Route::post('storage/delete', [StorageAdminController::class, 'delete'])->name('storage.delete');
            Route::post('storage/delete-bulk', [StorageAdminController::class, 'deleteBulk'])->name('storage.deleteBulk');
        });

        // Support Académica
        Route::prefix('support/academica')
            ->name('support.academic.')
            ->middleware('gdf.role:role=gdf.academic_support')
            ->group(function () {

                Route::get('/', [SupportDashboardController::class, 'index'])->name('dashboard');

                Route::get('requests/index', [SupportDashboardController::class, 'requestsIndex'])->name('requests.index');
                Route::get('requests/{travelRequest}', [SupportDashboardController::class, 'show'])->name('requests.show');

                Route::post('requests/{travelRequest}/seen', [SupportDashboardController::class, 'markSeen'])->name('requests.seen');
                Route::post('requests/{travelRequest}/return', [SupportDashboardController::class, 'returnToInstructor'])->name('requests.return');
                Route::post('requests/{travelRequest}/send-treasury', [SupportDashboardController::class, 'sendToTreasury'])->name('requests.sendTreasury');

                Route::post('requests/{travelRequest}/perdiem/toggle', [SupportDashboardController::class, 'togglePerDiem'])->name('requests.perdiem.toggle');
                Route::post('requests/{travelRequest}/perdiem/liquidate', [SupportDashboardController::class, 'perDiemLiquidate'])->name('requests.perdiem.liquidate');

                Route::get('requests/{travelRequest}/segments/{segmentId}/suggest-rate', [SupportDashboardController::class, 'suggestSegmentRate'])->name('requests.segments.suggestRate');
                Route::post('requests/{travelRequest}/segments/{segmentId}', [SupportDashboardController::class, 'segmentsUpdate'])->name('requests.segments.update');

                Route::get('rates', [SupportDashboardController::class, 'ratesIndex'])->name('rates.index');
                Route::post('rates/upsert', [SupportDashboardController::class, 'ratesUpsert'])->name('rates.upsert');
                Route::get('rates/export-missing', [SupportDashboardController::class, 'exportMissing'])->name('rates.exportMissing');
                Route::post('rates/import', [SupportDashboardController::class, 'importRates'])->name('rates.import');

                Route::get('motorcycles/queue', [SupportMotorcyclesController::class, 'queue'])->name('motorcycles.queue');
                Route::get('motorcycles/return', [SupportMotorcyclesController::class, 'returnIndex'])->name('motorcycles.return');

                Route::post('motorcycles', [SupportMotorcyclesController::class, 'storeMoto'])->name('motorcycles.store');
                Route::patch('motorcycles/{motorcycle}/status', [SupportMotorcyclesController::class, 'updateMotoStatus'])->name('motorcycles.updateStatus');

                Route::post('motorcycles/assignments/direct', [SupportMotorcyclesController::class, 'storeDirect'])->name('motorcycles.storeDirect');
                Route::get('motorcycles/assignments/{assignment}/receipt', [SupportMotorcyclesController::class, 'assignmentReceipt'])->name('motorcycles.assignments.receipt');
                Route::post('motorcycles/assignments/{assignment}/return', [SupportMotorcyclesController::class, 'returnAssignment'])->name('motorcycles.assignments.return');

                Route::post('requests/{travelRequest}/moto/assign', [SupportMotorcyclesController::class, 'assignMotoForRequest'])->name('motorcycles.assign.for_request');
                Route::post('requests/{travelRequest}/moto/release', [SupportMotorcyclesController::class, 'releaseForRequest'])->name('motorcycles.release.for_request');

                Route::get('motorcycles/people', [SupportMotorcyclesController::class, 'peopleSearch'])->name('motorcycles.people');

                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::post('people', [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people/budget-items-by-area', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('people.budgetItemsByArea');
                Route::post('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');
                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.byRubro.index');

                Route::post('budgets/{budget}/additions', [BudgetAdditionController::class, 'store'])->name('budgets.additions.store');

                Route::post('requests/{travelRequest}/allowances/upsert', [SupportDashboardController::class, 'upsertAllowance'])->name('requests.allowances.upsert');
                Route::post('requests/{travelRequest}/allowances/reject', [SupportDashboardController::class, 'rejectAllowances'])->name('requests.allowances.reject');

                // Documentos
                Route::get('requests/{travelRequest}/documents', [SupportDashboardController::class, 'documentsIndex'])->name('requests.documents.index');

                Route::get('documents/{documentId}/download', [SupportDashboardController::class, 'documentsDownload'])->name('documents.download');
                Route::get('documents/sigac/{documentId}/download', [SupportDashboardController::class, 'documentsDownloadSigac'])->name('documents.sigac.download');

                Route::get('documents/{documentId}/preview', [SupportDashboardController::class, 'documentsPreview'])->name('documents.preview');
                Route::post('documents/{documentId}/review', [SupportDashboardController::class, 'documentsReview'])->name('documents.review');
                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::post('people', [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');

                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.by_rubro');
                Route::get('budget-items', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('budget_items.by_area');
            });

        // Support Campesena
        Route::prefix('support/campesena')
            ->name('support.campesena.')
            ->middleware('gdf.role:role=gdf.campesena_support')
            ->group(function () {

                Route::get('/', [SupportDashboardController::class, 'index'])->name('dashboard');

                Route::get('requests/index', [SupportDashboardController::class, 'requestsIndex'])->name('requests.index');
                Route::get('requests/{travelRequest}', [SupportDashboardController::class, 'show'])->name('requests.show');

                Route::post('requests/{travelRequest}/seen', [SupportDashboardController::class, 'markSeen'])->name('requests.seen');
                Route::post('requests/{travelRequest}/return', [SupportDashboardController::class, 'returnToInstructor'])->name('requests.return');
                Route::post('requests/{travelRequest}/send-treasury', [SupportDashboardController::class, 'sendToTreasury'])->name('requests.sendTreasury');

                Route::post('requests/{travelRequest}/perdiem/toggle', [SupportDashboardController::class, 'togglePerDiem'])->name('requests.perdiem.toggle');
                Route::post('requests/{travelRequest}/perdiem/liquidate', [SupportDashboardController::class, 'perDiemLiquidate'])->name('requests.perdiem.liquidate');

                Route::get('requests/{travelRequest}/segments/{segmentId}/suggest-rate', [SupportDashboardController::class, 'suggestSegmentRate'])->name('requests.segments.suggestRate');
                Route::post('requests/{travelRequest}/segments/{segmentId}', [SupportDashboardController::class, 'segmentsUpdate'])->name('requests.segments.update');

                Route::get('rates', [SupportDashboardController::class, 'ratesIndex'])->name('rates.index');
                Route::post('rates/upsert', [SupportDashboardController::class, 'ratesUpsert'])->name('rates.upsert');
                Route::get('rates/export-missing', [SupportDashboardController::class, 'exportMissing'])->name('rates.exportMissing');
                Route::post('rates/import', [SupportDashboardController::class, 'importRates'])->name('rates.import');

                Route::get('motorcycles/queue', [SupportMotorcyclesController::class, 'queue'])->name('motorcycles.queue');
                Route::get('motorcycles/return', [SupportMotorcyclesController::class, 'returnIndex'])->name('motorcycles.return');

                Route::post('motorcycles', [SupportMotorcyclesController::class, 'storeMoto'])->name('motorcycles.store');
                Route::patch('motorcycles/{motorcycle}/status', [SupportMotorcyclesController::class, 'updateMotoStatus'])->name('motorcycles.updateStatus');

                Route::post('motorcycles/assignments/direct', [SupportMotorcyclesController::class, 'storeDirect'])->name('motorcycles.storeDirect');
                Route::get('motorcycles/assignments/{assignment}/receipt', [SupportMotorcyclesController::class, 'assignmentReceipt'])->name('motorcycles.assignments.receipt');
                Route::post('motorcycles/assignments/{assignment}/return', [SupportMotorcyclesController::class, 'returnAssignment'])->name('motorcycles.assignments.return');

                Route::post('requests/{travelRequest}/moto/assign', [SupportMotorcyclesController::class, 'assignMotoForRequest'])->name('motorcycles.assign.for_request');
                Route::post('requests/{travelRequest}/moto/release', [SupportMotorcyclesController::class, 'releaseForRequest'])->name('motorcycles.release.for_request');

                Route::get('motorcycles/people', [SupportMotorcyclesController::class, 'peopleSearch'])->name('motorcycles.people');

                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::post('people', [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people/budget-items-by-area', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('people.budgetItemsByArea');
                Route::post('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');
                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.byRubro.index');

                Route::post('budgets/{budget}/additions', [BudgetAdditionController::class, 'store'])->name('budgets.additions.store');

                Route::post('requests/{travelRequest}/allowances/upsert', [SupportDashboardController::class, 'upsertAllowance'])->name('requests.allowances.upsert');
                Route::post('requests/{travelRequest}/allowances/reject', [SupportDashboardController::class, 'rejectAllowances'])->name('requests.allowances.reject');

                // Documentos
                Route::get('requests/{travelRequest}/documents', [SupportDashboardController::class, 'documentsIndex'])->name('requests.documents.index');

                Route::get('documents/{documentId}/download', [SupportDashboardController::class, 'documentsDownload'])->name('documents.download');
                Route::get('documents/sigac/{documentId}/download', [SupportDashboardController::class, 'documentsDownloadSigac'])->name('documents.sigac.download');

                Route::get('documents/{documentId}/preview', [SupportDashboardController::class, 'documentsPreview'])->name('documents.preview');
                Route::post('documents/{documentId}/review', [SupportDashboardController::class, 'documentsReview'])->name('documents.review');
                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::post('people', [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');

                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.by_rubro');
                Route::get('budget-items', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('budget_items.by_area');
            });

        // Tesorería
        Route::prefix('treasury')->name('treasury.')->middleware('gdf.role:role=gdf.treasury')->group(function () {
            Route::get('review', [TreasuryController::class, 'index'])->name('dashboard');

            Route::get('requests', [TreasuryController::class, 'index'])->name('requests.index');
            Route::get('requests/{id}', [TreasuryController::class, 'show'])->name('requests.show');

            Route::post('requests/{id}/approve', [TreasuryController::class, 'approve'])->name('requests.approve');
            Route::post('requests/{id}/return', [TreasuryController::class, 'return'])->name('requests.return');
            Route::post('requests/{id}/reject', [TreasuryController::class, 'reject'])->name('requests.reject');

            Route::post('requests/{id}/radicate', [TreasuryController::class, 'radicate'])->name('requests.radicate');
            Route::post('requests/{id}/adjust-transport', [TreasuryController::class, 'adjustTransport'])->name('requests.adjustTransport');
            Route::post('requests/{id}/allowances/{allowanceId}/adjust', [TreasuryController::class, 'adjustAllowance'])->name('requests.allowances.adjust');
        });

        // Coordinación Académica
        Route::prefix('academic')->name('academic.')
            ->middleware('gdf.role:role=gdf.academic_coordinator|gdf.academic_support,ctx=coord|support,area=academic')
            ->group(function () {

                Route::get('dashboard', [GDFController::class, 'academic_coordination'])->name('dashboard');

                Route::get('review', [CoordinationController::class, 'index'])->name('review');
                Route::get('review/{travelRequest}', [CoordinationController::class, 'show'])->name('review.show');

                Route::post('review/{id}/approve', [CoordinationController::class, 'approve'])->name('review.approve');
                Route::post('review/{id}/return', [CoordinationController::class, 'return'])->name('review.return');
                Route::post('review/{id}/reject', [CoordinationController::class, 'reject'])->name('review.reject');

                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::post('people', [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');

                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.by_rubro');
                Route::get('budget-items', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('budget_items.by_area');

                Route::get('motorcycles', [CoordinationMotorcyclesController::class, 'index'])->name('motorcycles.index');
                Route::get('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignCreate'])->name('motorcycles.assign.create');
                Route::post('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignStore'])->name('motorcycles.assign.store');

                Route::get('motorcycles/quota-status', [CoordinationMotorcyclesController::class, 'quotaStatus'])->name('motorcycles.quota_status');
                Route::get('motorcycles/search-person', [CoordinationMotorcyclesController::class, 'searchPerson'])->name('motorcycles.search_person');
                Route::get('motorcycles/people-in-area', [CoordinationMotorcyclesController::class, 'peopleInArea'])->name('motorcycles.people_in_area');

                Route::get('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'createWithMotorcycle'])->name('motorcycles.assign_create');
                Route::post('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'storeWithMotorcycle'])->name('motorcycles.assign_create.store');

                Route::get('requests/{id}/index', [sitraInstructorController::class, 'requestsIndex'])->name('requests.index');

                Route::post('{id}/allowances/{allowanceId}/approve', [CoordinationController::class, 'approveAllowance'])->name('review.allowances.approve');
                Route::post('{id}/allowances/{allowanceId}/reject', [CoordinationController::class, 'rejectAllowance'])->name('review.allowances.reject');
                Route::post('{id}/allowances/{allowanceId}/update', [CoordinationController::class, 'updateAllowance'])->name('review.allowances.update');
            });

        // Coordinación Campesena
        Route::prefix('campesena')->name('campesena.')
            ->middleware('gdf.role:role=gdf.campesena_coordinator|gdf.campesena_support,ctx=coord|support,area=campesena')
            ->group(function () {

                Route::get('dashboard', [GDFController::class, 'campesena'])->name('dashboard');

                Route::get('review', [CoordinationController::class, 'index'])->name('review');
                Route::get('review/{travelRequest}', [CoordinationController::class, 'show'])->name('review.show');

                Route::post('review/{id}/approve', [CoordinationController::class, 'approve'])->name('review.approve');
                Route::post('review/{id}/return', [CoordinationController::class, 'return'])->name('review.return');
                Route::post('review/{id}/reject', [CoordinationController::class, 'reject'])->name('review.reject');

                Route::get('people', [CoordinationPeopleController::class, 'index'])->name('people.index');
                Route::get('people/create', [CoordinationPeopleController::class, 'create'])->name('people.create');
                Route::post('people', [CoordinationPeopleController::class, 'store'])->name('people.store');
                Route::get('people/search', [CoordinationPeopleController::class, 'search'])->name('people.search');

                Route::get('people/by-rubro', [CoordinationPeopleController::class, 'peopleByRubroIndex'])->name('people.by_rubro');
                Route::get('budget-items', [CoordinationPeopleController::class, 'budgetItemsByArea'])->name('budget_items.by_area');

                Route::get('motorcycles', [CoordinationMotorcyclesController::class, 'index'])->name('motorcycles.index');
                Route::get('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignCreate'])->name('motorcycles.assign.create');
                Route::post('motorcycles/assign', [CoordinationMotorcyclesController::class, 'assignStore'])->name('motorcycles.assign.store');

                Route::get('motorcycles/quota-status', [CoordinationMotorcyclesController::class, 'quotaStatus'])->name('motorcycles.quota_status');
                Route::get('motorcycles/search-person', [CoordinationMotorcyclesController::class, 'searchPerson'])->name('motorcycles.search_person');
                Route::get('motorcycles/people-in-area', [CoordinationMotorcyclesController::class, 'peopleInArea'])->name('motorcycles.people_in_area');

                Route::get('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'createWithMotorcycle'])->name('motorcycles.assign_create');
                Route::post('motorcycles/assign-create', [CoordinationMotorcyclesController::class, 'storeWithMotorcycle'])->name('motorcycles.assign_create.store');

                Route::get('requests/{id}/index', [sitraInstructorController::class, 'requestsIndex'])->name('requests.index');

                Route::post('{id}/allowances/{allowanceId}/approve', [CoordinationController::class, 'approveAllowance'])->name('review.allowances.approve');
                Route::post('{id}/allowances/{allowanceId}/reject', [CoordinationController::class, 'rejectAllowance'])->name('review.allowances.reject');
                Route::post('{id}/allowances/{allowanceId}/update', [CoordinationController::class, 'updateAllowance'])->name('review.allowances.update');
            });

        // Instructor Oficial
        Route::prefix('instructor')
            ->name('instructor.')
            ->middleware('gdf.role:role=gdf.instructor,ctx=official')
            ->group(function () {

                Route::get('/', [OfficialController::class, 'dashboard'])->name('dashboard');

                Route::get('requests', [OfficialController::class, 'index'])->name('requests.index');
                Route::get('requests/create', [OfficialController::class, 'create'])->name('requests.create');
                Route::post('requests', [OfficialController::class, 'store'])->name('requests.store');
                Route::get('requests/{gdfRequest}', [OfficialController::class, 'show'])->name('requests.show');
                Route::get('requests/{gdfRequest}/edit', [OfficialController::class, 'edit'])->name('requests.edit');
                Route::post('requests/{gdfRequest}/autosave', [OfficialController::class, 'autosave'])->name('requests.autosave');
                Route::post('requests/{gdfRequest}/submit', [OfficialController::class, 'submit'])->name('requests.submit');
                Route::post('requests/{gdfRequest}/cancel', [OfficialController::class, 'cancel'])->name('requests.cancel');

                Route::post('requests/draft', [OfficialController::class, 'createDraft'])->name('requests.draft');
                Route::get('official/documents/{documentId}/preview', [OfficialController::class, 'documentPreview'])->name('documents.preview');


                Route::get('catalog/budget-items', [CatalogController::class, 'budgetItems'])->name('catalog.budgetItems');
                Route::get('catalog/departments', [CatalogController::class, 'departments'])->name('catalog.departments');
                Route::get('catalog/municipalities', [CatalogController::class, 'municipalities'])->name('catalog.municipalities');
                Route::get('catalog/villages', [CatalogController::class, 'villages'])->name('catalog.villages');
                Route::get('transport-rate', [CatalogController::class, 'transportRate'])->name('catalog.transportRate');

                Route::get('motorcycle', [MotorcycleController::class, 'index'])->name('motorcycle.index');
                Route::post('motorcycle', [MotorcycleController::class, 'storeRequest'])->name('motorcycle.store');
                Route::post('motorcycle/{assignment}/cancel', [MotorcycleController::class, 'cancel'])->name('motorcycle.cancel');
                Route::get('motorcycle/active', [MotorcycleController::class, 'active'])->name('motorcycle.active');

                // SITRAV desde SIGAC
                Route::get('programs', [sitraInstructorController::class, 'programs'])->name('sitrav.programs.index');
                Route::get('programs/{programRequestId}/create', [sitraInstructorController::class, 'createFromProgram'])->name('sitrav.programs.create');
                Route::post('programs/{programRequestId}', [sitraInstructorController::class, 'storeFromProgram'])->name('sitrav.programs.store');

                Route::post('sitrav/requests/{travelRequest}/fuel/delete', [sitraInstructorController::class, 'deleteFuel'])->name('sitrav.requests.fuel.delete');

                Route::post('requests/{travelRequestId}/submit', [sitraInstructorController::class, 'submit'])->name('sitrav.requests.submit');

                // Documentos instructor
                Route::post('requests/{travelRequestId}/documents', [OfficialController::class, 'uploadDocument'])->name('requests.documents.upload');
                Route::post('documents/{documentId}/delete', [OfficialController::class, 'deleteDocument'])->name('requests.documents.delete');

                Route::prefix('sitrav')->name('sitrav.')->group(function () {

                    Route::get('requests/{travelRequestId}/edit', [sitraInstructorController::class, 'edit'])->name('requests.edit');

                    Route::post('requests/{travelRequestId}/dates', [sitraInstructorController::class, 'storeDates'])->name('requests.storeDates');
                    Route::post('requests/{travelRequestId}/costs', [sitraInstructorController::class, 'storeCosts'])->name('requests.storeCosts');
                    Route::post('requests/{travelRequestId}/costs/autosave', [sitraInstructorController::class, 'autosaveCosts'])->name('requests.costs.autosave');

                    Route::post('requests/{travelRequestId}/allowances', [sitraInstructorController::class, 'storeAllowances'])->name('requests.allowances.store');
                    Route::post('requests/{travelRequestId}/allowances/delete', [sitraInstructorController::class, 'deleteAllowances'])->name('requests.allowances.delete');

                    Route::post('requests/{travelRequestId}/fuel/delete', [sitraInstructorController::class, 'deleteFuel'])->name('requests.fuel.delete');

                    Route::post('requests/{travelRequestId}/submit', [sitraInstructorController::class, 'submit'])->name('requests.submit');
                    Route::get('sitrav/request/{travelRequestId}', [sitraInstructorController::class, 'show'])->name('request.show');
                });
            });

        // Subdirección
        Route::prefix('subdirection')->name('subdirection.')->middleware('gdf.role:role=gdf.subdirection')->group(function () {

            Route::get('dashboard', [SubdirectionController::class, 'index'])->name('dashboard');

            Route::get('reports', [SubdirectionController::class, 'reports'])->name('reports');
            Route::get('reports/summary', [SubdirectionController::class, 'reportsSummary'])->name('reports.summary');
            Route::get('reports/trend', [SubdirectionController::class, 'reportsTrend'])->name('reports.trend');
            Route::get('reports/top-destinations', [SubdirectionController::class, 'reportsTopDestinations'])->name('reports.top');
            Route::get('reports/map-points-gdf', [SubdirectionController::class, 'reportsMapPointsGdf'])->name('reports.map.gdf');
            Route::get('reports/map-points-sitrav', [SubdirectionController::class, 'reportsMapPointsSitrav'])->name('reports.map.sitrav');
            Route::get('reports/export/pdf', [SubdirectionController::class, 'exportReportsPdf'])->name('reports.export.pdf');
            Route::get('reports/export/zip', [SubdirectionController::class, 'exportReportsZip'])->name('reports.export.zip');

            // Solicitudes
            Route::get('requests', [SubdirectionController::class, 'requestsIndex'])->name('requests.index');
            Route::get('requests/{id}', [SubdirectionController::class, 'requestsShow'])->name('requests.show');
            Route::post('requests/{id}/approve', [SubdirectionController::class, 'requestsApprove'])->name('requests.approve');
            Route::post('requests/{id}/reject', [SubdirectionController::class, 'requestsReject'])->name('requests.reject');
            Route::post('requests/{id}/return', [SubdirectionController::class, 'requestsReturn'])->name('requests.return');

            Route::get('requests/{id}/documents/{docId}/download', [SubdirectionController::class, 'downloadDocument'])->name('requests.documents.download');
            Route::get('requests/{id}/sigac-documents/{docId}/download', [SubdirectionController::class, 'downloadSigacDocument'])->name('requests.sigac_documents.download');

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

            // Auditoría / movimientos
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

            // Áreas
            Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
            Route::get('areas/create', [AreaController::class, 'create'])->name('areas.create');
            Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
            Route::get('areas/{id}/edit', [AreaController::class, 'edit'])->name('areas.edit');
            Route::put('areas/{id}', [AreaController::class, 'update'])->name('areas.update');

            // Rubros por área
            Route::get('areas/{area}/rubros', [AreaBudgetItemController::class, 'edit'])->name('area_budget_items.edit');
            Route::post('areas/{area}/rubros', [AreaBudgetItemController::class, 'update'])->name('area_rubros.update');

            // Motos
            Route::get('motorcycles', [SubdirectionMotorcyclesController::class, 'index'])->name('motorcycles.index');
            Route::get('motorcycles/create', [SubdirectionMotorcyclesController::class, 'create'])->name('motorcycles.create');
            Route::post('motorcycles', [SubdirectionMotorcyclesController::class, 'store'])->name('motorcycles.store');
            Route::post('motorcycles/{motorcycle}/transfer', [SubdirectionMotorcyclesController::class, 'transfer'])->name('motorcycles.transfer');

            Route::get('motorcycles/quotas', [MotorcycleQuotaController::class, 'index'])->name('motorcycles.quotas.index');
            Route::post('motorcycles/quotas', [MotorcycleQuotaController::class, 'store'])->name('motorcycles.quotas.store');

            // Firmantes de autorización
            Route::get('authorization-signers', [AuthorizationSignersController::class, 'index'])->name('authorization_signers.index');
            Route::get('authorization-signers/create', [AuthorizationSignersController::class, 'create'])->name('authorization_signers.create');
            Route::post('authorization-signers', [AuthorizationSignersController::class, 'store'])->name('authorization_signers.store');
            Route::get('authorization-signers/{id}/edit', [AuthorizationSignersController::class, 'edit'])->name('authorization_signers.edit');
            Route::put('authorization-signers/{id}', [AuthorizationSignersController::class, 'update'])->name('authorization_signers.update');
            Route::delete('authorization-signers/{id}', [AuthorizationSignersController::class, 'destroy'])->name('authorization_signers.destroy');
            Route::get('authorization-signers/people-by-area', [AuthorizationSignersController::class, 'peopleByArea'])->name('authorization_signers.people_by_area');
            Route::get('authorization-signers/people-search', [AuthorizationSignersController::class, 'peopleSearch'])->name('authorization_signers.people_search');
            Route::get('authorization-signers/person-details', [AuthorizationSignersController::class, 'personDetails'])
                ->name('authorization_signers.person_details');
        });
    });
});
