<?php

namespace Modules\SICA\Http\Controllers\security;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Modules\SICA\Entities\Person;
use App\Models\User;
use App\Rules\AtLeastOneRoleSelected;
use Modules\SICA\Entities\App;
use Modules\SICA\Entities\Role;
use Modules\SICA\Entities\Apprentice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\AUTH\LoginOtpMail;
use Modules\SICA\Entities\LoginOtp;


class UserController extends Controller
{


    /* Listado  de usuarios disponibles */
    public function index()
    {
        $users = User::orderBy('updated_at', 'DESC')->get();
        $data = ['title' => trans('sica::menu.Users'), 'users' => $users];
        return view('sica::admin.security.users.index', $data);
    }

    /* Formulario de registro de usuario */
    public function create()
    {
        $data = ['title' => 'Usuarios - Registro'];
        return view('sica::admin.security.users.create', $data);
    }

    /* Consultar persona por número de identificación */
    public function search_person()
    {
        $data = json_decode($_POST['data']);
        $person = Person::where('document_number', $data->document_number)->first();
        if ($person):
            $user = User::where('person_id', $person->id)->first();
            $apps = App::orderBy('name', 'ASC')->get();
            $data = ['person' => $person, 'apps' => $apps, 'user' => $user];
            return view('sica::admin.security.users.search_person', $data);
        else:
            return '<div class="row d-flex justify-content-center"><span class="h5 text-danger">La persona consultada no se encuentra registrada.</span><div>';
        endif;
    }

    /* Registrar usuario */
    public function store(Request $request)
    {
        $rules = [
            'person_id' => 'required|unique:users',
            'nickname' => 'required|unique:users',
            'personal_email' => 'required|email|unique:users,email',
            'roles_id' => ['required', new AtLeastOneRoleSelected()]
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->with('message', 'Ocurrió un error con el formulario.')->with('typealert', 'danger')->withInput($request->except('password'))->with('scriptJS', 'ajaxSearchPersonUser()');
        } else {
            $user = new User;
            $user->nickname = e($request->input('nickname'));
            $user->person_id = e($request->input('person_id'));
            $user->email = e($request->input('personal_email'));
            try {
                DB::beginTransaction(); // Iniciar transacción
                $user = new User;
                $user->nickname = e($request->input('nickname'));
                $user->person_id = e($request->input('person_id'));
                $user->email = e($request->input('personal_email'));
                $user->save(); // Registrar usuario
                // Obtener los id de roles a partir de los valores de roles_id
                $roles_ids = array_values($request->input('roles_id'));
                $roles_id_int = array_values(array_map('intval', array_filter($roles_ids, function ($value) {
                    return $value !== null;
                })));
                $user->roles()->syncWithoutDetaching($roles_id_int); // Sincronizar los nuevos roles al usuario
                DB::commit(); // Confirmar la transacción
                $message = ['message' => 'Se registró exitosamente el usuario.', 'typealert' => 'success'];
            } catch (\Exception $e) {
                DB::rollBack(); // Revertir cambios realizados en la transacción
                $message = ['message' => 'No se pudo realizar el registro del usuario.', 'typealert' => 'danger'];
            }
            return redirect(route('sica.admin.security.users.index'))->with($message);
        }
    }

    /* Formulario de actualización de usuario */
    public function edit(User $user)
    {
        $apps = App::orderBy('name', 'ASC')->get();
        $data = ['title' => 'Usuarios - Actualizar', 'user' => $user, 'apps' => $apps];
        return view('sica::admin.security.users.edit', $data);
    }

    /* Actualizar usuario */
    public function update(Request $request, User $user)
    {
        // Vefirificar si hay algun cambio en los roles del usuario
        if ($user->roles->pluck('id')->toArray() != $request->input('roles_id')) {
            $rules = [
                'roles_id' => ['required', new AtLeastOneRoleSelected()]
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput()->with(['message' => 'Ocurrió un error con el formulario.', 'typealert' => 'danger']);
            }
            try {
                DB::beginTransaction(); // Iniciar transacción
                $user->roles()->detach(); // Eliminar roles vinculados al usuario
                // Obtener los id de roles a partir de los valores de roles_id
                $roles_ids = array_values($request->input('roles_id'));
                $roles_id_int = array_values(array_map('intval', array_filter($roles_ids, function ($value) {
                    return $value !== null;
                })));
                $user->roles()->syncWithoutDetaching($roles_id_int); // Sincronizar los nuevos roles al usuario
                $user->touch(); // Actualizar el dato updated_at del usuario
                DB::commit(); // Confirmar la transacción
                $message = ['message' => 'Se actualizó exitosamente el usuario.', 'typealert' => 'success'];
            } catch (\Exception $e) {
                DB::rollBack(); // Revertir cambios realizados en la transacción
                $message = ['message' => 'No se pudo realizar la actualización del usuario.', 'typealert' => 'danger'];
            }
        } else {
            $message = ['message' => 'No se realizó ninguna actualización por que no hay cambios en los datos del usuario.', 'typealert' => 'info'];
        }
        return redirect(route('sica.admin.security.users.index'))->with($message);
    }

    /* Eliminar usuario */
    public function destroy(User $user)
    {
        if ($user->delete()) {
            $message = ['message' => 'Se eliminó exitosamente el usuario.', 'typealert' => 'success'];
        } else {
            $message = ['message' => 'No se pudo eliminar el usuario.', 'typealert' => 'danger'];
        }
        return redirect(route('sica.admin.security.users.index'))->with($message);
    }

    public function change(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $user_id = $user->person->id;

            // Verificar si hay una contraseña guardada en la sesión para este usuario
            if (Session::has('passwords.' . $user_id)) {
                $session_password = Session::get('passwords.' . $user_id);

                $first_change = 1;
                return view('auth.passwords.change')->with(['firstchange' => $first_change]);
            }
        }

        return view('auth.passwords.change');
    }

    public function changesave(Request $request)
    {
        $rules = [
            'current_password' => 'required|string',
            'new_password' => 'required|confirmed|min:8|string',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }
        $auth = Auth::user();


        if (!Hash::check($request->get('current_password'), $auth->password)) {
            return back()->with('current_password', "La contraseña actual es invalida");
        }


        if (strcmp($request->get('current_password'), $request->new_password) == 0) {
            return redirect()->back()->with("new_password", "La nueva contraseña no puede ser la misma que su contraseña actual.");
        }

        $user =  User::find($auth->id);
        $user->password =  Hash::make($request->new_password);
        if ($nickname = $request->input('nickname')) {
            $user->nickname =  $nickname;
        }

        $user->save();

        // Eliminar la contraseña de la sesión
        Session::forget('passwords.' . $user->person->id);

        return back()->with('success', "Contraseña cambiada exitosamente");
    }

    /** Vista del formulario */
    public function user_register()
    {
        return view('auth.passwords.request_user');
    }

    /**
     * AJAX: Busca persona y devuelve rol + emails disponibles
     * RUTA RECOMENDADA: cefa.user.register.searchperson
     */
    public function user_search_person(Request $request)
    {
        try {
            $document_number = preg_replace('/\D+/', '', (string) $request->input('document_number'));

            if (!$document_number || strlen($document_number) < 6) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Documento inválido.',
                    'hint' => 'Debe tener al menos 6 dígitos.'
                ], 422);
            }

            $person = Person::where('document_number', $document_number)->first();
            if (!$person) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Persona no encontrada.',
                    'hint' => 'Verifica el documento.'
                ], 404);
            }

            if (User::where('person_id', $person->id)->exists()) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Esta persona ya cuenta con un usuario.',
                    'hint' => 'Intenta iniciar sesión.'
                ], 409);
            }

            // SOLO APRENDIZ
            $isApprentice = Apprentice::where('person_id', $person->id)->exists();
            if (!$isApprentice) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Este registro es solo para aprendices.',
                    'hint' => 'Si eres instructor, solicita creación por administración.'
                ], 403);
            }

            // SOLO personal_email
            $personalEmail = strtolower(trim((string) ($person->personal_email ?? '')));
            if ($personalEmail === '' || !str_contains($personalEmail, '@')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'No tienes correo personal registrado.',
                    'hint' => 'Contacta a Coordinación Académica para actualizarlo.'
                ], 422);
            }

            return response()->json([
                'ok' => true,
                'rol' => 'Aprendiz',
                'person' => [
                    'id' => $person->id,
                    'document_number' => $person->document_number,
                    'first_name' => $person->first_name,
                    'first_last_name' => $person->first_last_name,
                    'second_last_name' => $person->second_last_name,
                ],
                // compatibilidad con tu vista vieja:
                'emails' => [
                    ['value' => $personalEmail, 'type' => 'personal_email'],
                ],
                // y también lo dejas directo por si luego migras:
                'personal_email' => $personalEmail,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Error interno consultando el documento.',
                'hint' => 'Revisa storage/logs/laravel.log',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }



    /**
     * POST: Crea usuario con email seleccionado (o email manual si no había)
     * RUTA RECOMENDADA: cefa.user.register.store
     */
    public function user_register_store(Request $request)
    {   
        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'string', 'max:30'],
        ], [
            'document_number.required' => 'El documento es requerido.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $document_number = preg_replace('/\D+/', '', (string) $request->input('document_number'));
        if (!$document_number || strlen($document_number) < 6) {
            return back()->withInput()->with('error', 'Documento inválido.');
        }

        $person = Person::where('document_number', $document_number)->first();
        if (!$person) {
            return back()->withInput()->with('error', 'Persona no encontrada.');
        }

        // SOLO aprendices
        $isApprentice = Apprentice::where('person_id', $person->id)->exists();
        if (!$isApprentice) {
            return back()->withInput()->with('error', 'Este registro es exclusivo para aprendices.');
        }

        // SOLO personal_email
        $email = strtolower(trim((string) ($person->personal_email ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->withInput()->with(
                'error',
                'No tienes correo personal registrado. Contacta a Coordinación.'
            );
        }

        // Ya existe usuario
        if (User::where('person_id', $person->id)->exists()) {
            return back()->withInput()->with('error', 'Esta persona ya cuenta con un usuario. Intenta iniciar sesión.');
        }

        // OTP config
        $ttlMinutes = 10;
        $otp = (string) random_int(100000, 999999);
        $otpHash = hash('sha256', $otp);

        DB::beginTransaction();

        try {
            // 1) Crear User
            $baseNick = Str::ascii(trim(($person->first_name ?? '') . ' ' . ($person->first_last_name ?? '')));
            $baseNick = preg_replace('/\s+/', '', $baseNick);
            $baseNick = strtoupper(substr($baseNick, 0, 10));
            if ($baseNick === '') $baseNick = 'APPR';

            // Evitar colisión de nickname
            $nickname = $baseNick;
            $i = 1;
            while (User::where('nickname', $nickname)->exists()) {
                $nickname = $baseNick . $i;
                $i++;
                if ($i > 999) break;
            }

            $user = new User();
            $user->person_id = $person->id;
            $user->nickname  = $nickname;
            $user->email     = $email;
            $user->password  = Hash::make(Str::random(32));
            $user->save();

            // 2) Asignar rol aprendiz
            $roleIds = Role::where('slug', 'LIKE', '%.apprentice%')->pluck('id')->toArray();

            // Fallback si tu slug no coincide
            if (empty($roleIds)) {
                $roleIds = Role::where('name', 'LIKE', '%Aprendiz%')->pluck('id')->toArray();
            }

            if (!empty($roleIds)) {
                $user->roles()->sync($roleIds);
            }

            // 3) Invalidar OTP previos
            LoginOtp::where('person_id', $person->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->update(['consumed_at' => now()]);

            // 4) Crear OTP
            LoginOtp::create([
                'person_id'   => $person->id,
                'email_used'  => $email,
                'otp_hash'    => $otpHash,
                'attempts'    => 0,
                'expires_at'  => now()->addMinutes($ttlMinutes),
                'consumed_at' => null,
                'created_ip'  => $request->ip(),
                'user_agent'  => substr((string) $request->userAgent(), 0, 255),
            ]);

            // 5) Enviar correo OTP (si falla, debe hacer rollback)
            try {
                Mail::to($email)->send(new LoginOtpMail($otp, $ttlMinutes));
            } catch (\Throwable $mailEx) {
                // Log específico de correo
                logger()->error('OTP Mail send failed', [
                    'person_id' => $person->id,
                    'email' => $email,
                    'error' => $mailEx->getMessage(),
                ]);

                throw $mailEx; // fuerza rollback
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            logger()->error('User register store failed', [
                'document_number' => $document_number,
                'person_id' => $person->id ?? null,
                'email' => $email ?? null,
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 2000),
            ]);

            // En dev puedes mostrar el error real (opcional)
            if (config('app.debug')) {
                return back()->withInput()->with('error', 'FALLÓ: ' . $e->getMessage());
            }

            return back()->withInput()->with('error', 'No fue posible completar la solicitud. Intenta más tarde.');
        }

        return redirect()
            ->route('otp.login.verify.form', ['document_number' => $document_number])
            ->with('success', 'Te enviamos un código de seguridad a tu correo personal.');
    }


    /* Registrar usuario google */
    public function storer_usergoogle(Request $request)
    {
        $rules = [
            'nickname' => 'required|unique:users',
            'document_number' => 'required',
            'personal_email' => 'required|email|unique:users,email',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return back()->withErrors($validator)->with('message', 'Ocurrió un error con el formulario.')->with('typealert', 'danger')->withInput($request->except('password'))->with('scriptJS', 'ajaxSearchPersonUser()');
        } else {
            $app = App::where('name', 'SENAEMPRESA')->pluck('id');
            $person_id = Person::where('document_number', $request->input('document_number'))->first();

            try {
                DB::beginTransaction(); // Iniciar transacción
                $user = new User;
                $user->nickname = e($request->input('nickname'));
                $user->person_id = $person_id->id;
                $user->email = e($request->input('personal_email'));
                $user->save(); // Registrar usuario
                $role = Role::where('name', 'Aprendiz Senaempresa')->where('app_id', $app)->first();
                $user->roles()->syncWithoutDetaching($role); // Sincronizar los nuevos roles al usuario
                Auth::login($user);
                DB::commit(); // Confirmar la transacción
                $message = ['message' => 'Se registró exitosamente el usuario.', 'typealert' => 'success'];
            } catch (\Exception $e) {
                DB::rollBack(); // Revertir cambios realizados en la transacción
                dd($e);
                $message = ['message' => 'No se pudo realizar el registro del usuario.', 'typealert' => 'danger'];
            }
            return redirect(route('cefa.home'))->with($message);
        }
    }
}
