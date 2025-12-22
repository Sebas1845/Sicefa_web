<?php
use Illuminate\Support\Facades\Route;
use NumberToWords\Legacy\Numbers\Words\Locale\Ro;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::prefix('gdf')->group(function () {
    Route::get('index', [\Modules\GDF\Http\Controllers\GDFController::class, 'index'])
        ->name('cefa.gdf.index');

    Route::middleware('auth')->prefix('gdf')->group(function () {
        Route::get('/gateway', [\Modules\GDF\Http\Controllers\GDFController::class, 'gateway'])->name('gdf.gateway');
        Route::post('/gateway/select', [\Modules\GDF\Http\Controllers\GDFController::class, 'select'])->name('gdf.gateway.select');

        Route::get('/coordination', [\Modules\GDF\Http\Controllers\GDFController::class, 'academic_coordination'])->name('gdf.view.coordination');
        Route::get('/campesena', [\Modules\GDF\Http\Controllers\GDFController::class, 'campesena'])->name('gdf.view.campesena');
        Route::get('/subdireccion', [\Modules\GDF\Http\Controllers\GDFController::class, 'subdireccion'])->name('gdf.view.subdireccion');
        Route::get('/admin', [\Modules\GDF\Http\Controllers\GDFController::class, 'admin'])->name('gdf.view.admin');
    });
});
