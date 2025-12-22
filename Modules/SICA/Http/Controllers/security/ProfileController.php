<?php

namespace Modules\SICA\Http\Controllers\security;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class ProfileController extends Controller
{
    public function show()
{
    $user = Auth::user()->load(['person', 'roles.app']);

    // Agrupar roles por app (módulo)
    $apps = $user->roles
        ->groupBy(fn($role) => optional($role->app)->name ?? 'SICEFA')
        ->map(function ($roles, $appName) {
            return [
                'app' => $appName,
                'roles' => $roles->pluck('name')->unique()->values(),
            ];
        })->values();

    // EJEMPLOS: datos extra condicionales (solo si aplica)
    $sigac = null;
    if ($user->roles->contains(fn($r) => str_contains(strtolower($r->name), 'instructor'))) {
        // aquí llamas un servicio SIGAC para horario (ver sección 4)
        $sigac = app(\Modules\SIGAC\Services\InstructorScheduleService::class)->week($user->person_id);
    }

    $apprenticeWidgets = null;
    if ($user->roles->contains(fn($r) => str_contains(strtolower($r->name), 'apprentice'))) {
        // aquí llamas un servicio para estado de solicitudes del aprendiz
        $apprenticeWidgets = app(\Modules\SIGAC\Services\ApprenticeStatusService::class)->summary($user->person_id);
    }

    return view('auth.profile', compact('user','apps','sigac','apprenticeWidgets'));
}

    public function updateEmail(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => ['required','email','max:255','unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->with([
                'message' => 'Revisa el correo ingresado.',
                'typealert' => 'danger'
            ]);
        }

        $user->email = $request->email;
        $user->save();

        return back()->with([
            'message' => 'Correo actualizado correctamente.',
            'typealert' => 'success'
        ]);
    }
}
