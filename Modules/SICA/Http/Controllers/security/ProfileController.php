<?php

namespace Modules\SICA\Http\Controllers\security;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;


class ProfileController extends Controller
{
    public function __construct()
    {
        // Solo exige estar logueado. No permisos, no roles.
        $this->middleware('auth');
    }

    public function show()
    {
        $user = Auth::user()->load('person'); // SOLO person

        // Puedes enviar solo $user y en la vista mostrar person/email/etc.
        return view('auth.profile', compact('user'));
    }

    public function updateEmail(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with([
                    'message' => 'Revisa el correo ingresado.',
                    'typealert' => 'danger'
                ]);
        }

        $user->email = $request->input('email');
        $user->save();

        return back()->with([
            'message' => 'Correo actualizado correctamente.',
            'typealert' => 'success'
        ]);
    }
}
