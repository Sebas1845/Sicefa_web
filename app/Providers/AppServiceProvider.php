<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
            // Esto se ejecuta para todas las vistas (*)
    View::composer('*', function ($view) {
        // Obtiene el controlador y método actual
        $action = Route::currentRouteAction();

        // Lo pasa a todas las vistas automáticamente
        $view->with('currentController', $action);
    });
    }
    //esto en si se usa para saber que controlador en si esta usando cada vista es para agilizar el procedimiento  para eso esta la clase boot
    //<p>Controlador actual: {{ $currentController }}</p>
}
