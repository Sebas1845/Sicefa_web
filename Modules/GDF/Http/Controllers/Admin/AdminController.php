<?php

namespace Modules\GDF\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\LoginToken;

use Modules\SICA\Entities\Role;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Employee;
use Modules\SICA\Entities\Contractor;

use Modules\GDF\Entities\TravelRequest;
use Modules\GDF\Entities\TravelLog;

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function __construct()
    {
        // Todo el admin requiere auth, EXCEPTO el magic link
        $this->middleware(['auth'])->except(['accessByToken']);
    }

    /* ---------------------------------------------------------------------
     | Dashboard
     * --------------------------------------------------------------------- */
    public function index()
    {
        $kpis = [
            'total'     => TravelRequest::count(),
            'draft'     => TravelRequest::where('status', 'draft')->count(),
            'submitted' => TravelRequest::where('status', 'submitted')->count(),
            'approved'  => TravelRequest::where('status', 'approved')->count(),
            'returned'  => TravelRequest::where('status', 'returned')->count(),
            'rejected'  => TravelRequest::where('status', 'rejected')->count(),
        ];

        $recentLogs = TravelLog::query()
            ->with(['user', 'travelRequest'])
            ->orderByDesc('created_at')
            ->take(12)
            ->get();

        $latestRequests = TravelRequest::query()
            ->with(['createdBy', 'area'])
            ->orderByDesc('id')
            ->take(10)
            ->get();

        return view('gdf::admin.dashboard', compact('kpis', 'recentLogs', 'latestRequests'));
    }

    /* ---------------------------------------------------------------------
     | Usuarios: listado + filtros + import modal (sin usuario)
     * --------------------------------------------------------------------- */
    public function index_create_user(Request $request)
    {
        $q        = $request->get('q');
        $roleSlug = $request->get('role_slug');
        $src      = $request->get('src'); // contractor|employee|null

        $gdfRoles = Role::where('slug', 'like', 'gdf.%')->orderBy('name')->get();

        $usersQuery = User::query()
            ->with(['roles', 'person'])
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('nickname', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhereHas('person', function ($p) use ($q) {
                            $p->where('first_name', 'like', "%{$q}%")
                                ->orWhere('first_last_name', 'like', "%{$q}%")
                                ->orWhere('second_last_name', 'like', "%{$q}%")
                                ->orWhere('document_number', 'like', "%{$q}%");
                        });
                });
            })
            ->when($roleSlug, function ($query) use ($roleSlug) {
                $query->whereHas('roles', fn($r) => $r->where('slug', $roleSlug));
            })
            ->when($src === 'contractor', function ($query) {
                $query->whereHas('person.contractors');
            })
            ->when($src === 'employee', function ($query) {
                $query->whereHas('person.employees');
            })
            ->orderByDesc('id');

        $users = $usersQuery->paginate(10)->appends($request->query());

        // Import (personas SIN usuario)
        $importType = $request->get('import_type', 'none'); // none|contractors|employees|both
        $importQ    = $request->get('import_q');

        $contractors = collect();
        $employees   = collect();

        if (in_array($importType, ['contractors', 'both'], true)) {
            $contractors = Contractor::query()
                ->with('person')
                ->whereHas('person', fn($p) => $p->whereDoesntHave('user'))
                ->when($importQ, function ($qq) use ($importQ) {
                    $qq->whereHas('person', function ($p) use ($importQ) {
                        $p->where('document_number', 'like', "%{$importQ}%")
                            ->orWhere('first_name', 'like', "%{$importQ}%")
                            ->orWhere('first_last_name', 'like', "%{$importQ}%")
                            ->orWhere('second_last_name', 'like', "%{$importQ}%");
                    });
                })
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        if (in_array($importType, ['employees', 'both'], true)) {
            $employees = Employee::query()
                ->with('person')
                ->whereHas('person', fn($p) => $p->whereDoesntHave('user'))
                ->when($importQ, function ($qq) use ($importQ) {
                    $qq->whereHas('person', function ($p) use ($importQ) {
                        $p->where('document_number', 'like', "%{$importQ}%")
                            ->orWhere('first_name', 'like', "%{$importQ}%")
                            ->orWhere('first_last_name', 'like', "%{$importQ}%")
                            ->orWhere('second_last_name', 'like', "%{$importQ}%");
                    });
                })
                ->orderByDesc('id')
                ->limit(200)
                ->get();
        }

        return view('gdf::admin.create_users', compact(
            'users',
            'gdfRoles',
            'q',
            'roleSlug',
            'contractors',
            'employees',
            'importType',
            'importQ',
            'src'
        ));
    }

    /* ---------------------------------------------------------------------
     | Roles GDF: asignar / revocar
     * --------------------------------------------------------------------- */
    public function assignRole(Request $request)
    {
        $data = $request->validate([
            'user_id'   => ['required', 'exists:users,id'],
            'role_slug' => ['required', 'string'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $role = Role::where('slug', $data['role_slug'])->firstOrFail();

        if (!is_string($role->slug) || !str_starts_with($role->slug, 'gdf.')) {
            return back()->with('error', 'Solo roles del módulo GDF.');
        }

        if ($user->roles()->where('roles.id', $role->id)->exists()) {
            return back()->with('info', "El usuario ya tiene el rol {$role->name}.");
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        return back()->with('success', "Rol {$role->name} asignado correctamente.");
    }

    public function revokeRole(Request $request)
    {
        $data = $request->validate([
            'user_id'   => ['required', 'exists:users,id'],
            'role_slug' => ['required', 'string'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $role = Role::where('slug', $data['role_slug'])->firstOrFail();

        if (!is_string($role->slug) || !str_starts_with($role->slug, 'gdf.')) {
            return back()->with('error', 'Solo roles del módulo GDF.');
        }

        if (!$user->roles()->where('roles.id', $role->id)->exists()) {
            return back()->with('error', 'El usuario no tiene ese rol.');
        }

        $user->roles()->detach($role->id);

        return back()->with('success', "Rol {$role->name} revocado correctamente.");
    }

    /* ---------------------------------------------------------------------
     | Reset estándar Laravel (opcional)
     * --------------------------------------------------------------------- */
    public function sendResetLink(User $user)
    {
        if (empty($user->email)) {
            return back()->with('error', 'El usuario no tiene correo registrado. No se puede enviar el enlace.');
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('success', "Se envió el enlace de restablecimiento al correo: {$user->email}");
        }

        return back()->with('error', 'No fue posible enviar el enlace. Verifica configuración de correo y que el usuario exista.');
    }

    /* ---------------------------------------------------------------------
     | Magic link: genera + consume
     * --------------------------------------------------------------------- */
    private function sendMagicLink(User $user): bool
    {
        if (!$user->email) return false;

        $token = hash('sha256', Str::random(64));

        LoginToken::create([
            'user_id' => $user->id,
            'token'   => $token,
        ]);

        $url = route('gdf.access.by_token', $token);

        Mail::raw(
            "Hola.\n\nIngresa a SICEFA con este enlace:\n{$url}\n\nAl ingresar, el sistema te pedirá crear tu contraseña.\n\nSi no solicitaste este acceso, ignora este correo.",
            function ($m) use ($user) {
                $m->to($user->email)->subject('Acceso a SICEFA - Crear contraseña');
            }
        );

        return true;
    }

    public function accessByToken(string $token)
    {
        $row = LoginToken::where('token', $token)->first();

        if (!$row || $row->used_at) {
            abort(403, 'Enlace inválido o ya usado.');
        }

        $user = User::findOrFail($row->user_id);

        Auth::login($user);

        $row->used_at = now();
        $row->save();

        if ((int)($user->force_password_change ?? 0) === 1) {
            return redirect()->route('gdf.security.password.create');
        }

        return $this->redirectAfterLogin();
    }

    private function redirectAfterLogin()
    {
        // Para tu caso, lo más estable es volver al gateway
        if (Route::has('gdf.gateway')) {
            return redirect()->route('gdf.gateway');
        }
        if (Route::has('cefa.home')) {
            return redirect()->route('cefa.home');
        }
        if (Route::has('home')) {
            return redirect()->route('home');
        }
        return redirect('/');
    }

    /* ---------------------------------------------------------------------
     | Crear contraseña (obligatoria cuando force_password_change=1)
     * --------------------------------------------------------------------- */
    public function password_create_form()
    {
        $user = auth()->user()?->fresh();

        if (!$user) {
            return redirect()->route('login');
        }

        // Si ya no aplica, no hacer nada: vuelve al gateway
        if ((int)($user->force_password_change ?? 0) === 0) {
            return redirect()->route('gdf.gateway');
        }

        return view('auth.passwords.create_password');
    }

    public function password_create_store(Request $request)
    {
        $user = auth()->user()?->fresh();
        if (!$user) return redirect()->route('login');

        // si ya no está forzado, no hagas nada
        if ((int)($user->force_password_change ?? 0) === 0) {
            return redirect()->route('gdf.gateway');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->password = Hash::make($data['password']);
        $user->force_password_change = 0;
        $user->save();

        // evita sesión con user viejo
        $user->refresh();
        auth()->setUser($user);

        return redirect()->route('gdf.gateway')
            ->with('success', 'Contraseña creada correctamente.');
    }


    /* ---------------------------------------------------------------------
     | Import masivo (selección) + crear por documento
     * --------------------------------------------------------------------- */
    public function import_users_index(Request $request)
    {
        $q = $request->get('q');

        $contractors = Contractor::query()
            ->with('person')
            ->whereHas('person', fn($p) => $p->whereDoesntHave('user'))
            ->when($q, fn($qq) => $qq->whereHas(
                'person',
                fn($p) => $p
                    ->where('document_number', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('first_last_name', 'like', "%{$q}%")
                    ->orWhere('second_last_name', 'like', "%{$q}%")
            ))
            ->limit(120)
            ->get();

        $employees = Employee::query()
            ->with('person')
            ->whereHas('person', fn($p) => $p->whereDoesntHave('user'))
            ->when($q, fn($qq) => $qq->whereHas(
                'person',
                fn($p) => $p
                    ->where('document_number', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('first_last_name', 'like', "%{$q}%")
                    ->orWhere('second_last_name', 'like', "%{$q}%")
            ))
            ->limit(120)
            ->get();

        return view('gdf::admin.import_users', compact('contractors', 'employees', 'q'));
    }

    public function import_users_create(Request $request)
    {
        $data = $request->validate([
            'selected'   => ['required', 'array', 'min:1'],
            'selected.*' => ['string'],
        ]);

        $created = 0;
        $updated = 0;
        $noEmail = 0;
        $sent    = 0;

        foreach ($data['selected'] as $item) {
            [$type, $personId] = array_pad(explode(':', $item, 2), 2, null);
            $personId = (int)$personId;

            $person = Person::find($personId);
            if (!$person) continue;

            $user = User::where('person_id', $personId)->first();

            $email = $person->email ?? $person->personal_email ?? $person->misena_email ?? null;

            if (!$user) {
                if (!$email) {
                    $noEmail++;
                    continue;
                }

                $nickname = $this->uniqueNicknameForPerson($person);

                $user = User::create([
                    'person_id' => $personId,
                    'nickname'  => $nickname,
                    'email'     => $email,
                    'password'  => Hash::make(Str::random(32)),
                    'force_password_change' => 1,
                ]);

                $this->attachDefaultRoles($user, ['gdf.instructor', 'sigac.instructor']);

                $created++;
            } else {
                $user->force_password_change = 1;

                if (!$user->email && $email) {
                    $user->email = $email;
                }

                $user->save();
                $updated++;
            }

            if ($user->email) {
                if ($this->sendMagicLink($user)) $sent++;
            } else {
                $noEmail++;
            }
        }

        return back()->with('success', "Listo. Creados: {$created}. Actualizados: {$updated}. Enviados: {$sent}. Sin correo: {$noEmail}.");
    }

    public function createUserByDocument(Request $request)
    {
        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:30'],
            'source'          => ['nullable', 'in:auto,contractor,employee'],
        ]);

        $doc    = $data['document_number'];
        $source = $data['source'] ?? 'auto';

        $person = null;

        if ($source === 'auto' || $source === 'contractor') {
            $c = Contractor::whereHas('person', fn($q) => $q->where('document_number', $doc))
                ->with('person')
                ->first();
            if ($c && $c->person) $person = $c->person;
        }

        if (!$person && ($source === 'auto' || $source === 'employee')) {
            $e = Employee::whereHas('person', fn($q) => $q->where('document_number', $doc))
                ->with('person')
                ->first();
            if ($e && $e->person) $person = $e->person;
        }

        if (!$person) {
            return back()->with('error', 'No se encontró la cédula en contractors ni employees.');
        }

        $user = User::where('person_id', $person->id)->first();

        $email = $person->email ?? $person->personal_email ?? $person->misena_email ?? null;
        if (!$email && !$user) {
            return back()->with('error', 'La persona no tiene correo. No se puede crear acceso.');
        }

        if (!$user) {
            $nickname = $this->uniqueNicknameForPerson($person);

            $user = User::create([
                'person_id' => $person->id,
                'nickname'  => $nickname,
                'email'     => $email,
                'password'  => Hash::make(Str::random(32)),
                'force_password_change' => 1,
            ]);

            $this->attachDefaultRoles($user, ['gdf.instructor', 'sigac.instructor']);

            if ($user->email) $this->sendMagicLink($user);

            return back()->with('success', 'Usuario creado. Se envió el enlace si tenía correo.');
        }

        $user->force_password_change = 1;
        if (!$user->email && $email) {
            $user->email = $email;
        }
        $user->save();

        if ($user->email) {
            $this->sendMagicLink($user);
            return back()->with('success', 'Usuario ya existía. Se reenvió enlace y se forzó crear contraseña.');
        }

        return back()->with('warning', 'Usuario ya existe pero no tiene correo. No se pudo enviar enlace.');
    }

    /* ---------------------------------------------------------------------
     | Helpers
     * --------------------------------------------------------------------- */
    private function attachDefaultRoles(User $user, array $defaultRoleSlugs): void
    {
        $roleIds = Role::whereIn('slug', $defaultRoleSlugs)->pluck('id')->all();
        if (!empty($roleIds)) {
            $user->roles()->syncWithoutDetaching($roleIds);
        }
    }

    private function uniqueNicknameForPerson(Person $person): string
    {
        $nicknameBase = $this->makeNiceNickname($person);
        $nickname = $nicknameBase;

        $i = 1;
        while (User::where('nickname', $nickname)->exists()) {
            $nickname = $nicknameBase . $i;
            $i++;
        }

        return $nickname;
    }

    private function makeNiceNickname(Person $p): string
    {
        $fn  = strtoupper(preg_replace('/[^A-Z]/', '', Str::ascii($p->first_name ?? '')));
        $ln1 = strtoupper(preg_replace('/[^A-Z]/', '', Str::ascii($p->first_last_name ?? '')));

        $a = substr($fn, 0, 2) ?: 'XX';
        $b = substr($ln1, 0, 2) ?: 'YY';

        $last4 = substr((string)($p->document_number ?? ''), -4);
        if (!preg_match('/^\d{4}$/', $last4)) {
            $last4 = (string)random_int(1000, 9999);
        }

        return $a . $b . $last4;
    }
}
