<?php

namespace Modules\GDF\Http\Controllers\Coordination;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class PasswordController extends Controller
{
    /**
     * GET /gdf/security/magic/{token}
     * Valida token y prepara sesión para el formulario.
     * ESTA RUTA DEBE SER PÚBLICA (NO auth).
     */
    public function magic(Request $request, string $token)
    {
        $row = $this->getValidLoginTokenRow($token);
        if (!$row) {
            return redirect()->route('login')
                ->with('error', 'El enlace es inválido o expiró. Solicita uno nuevo.');
        }

        // Guardar en sesión para validar en POST
        $request->session()->put('gdf_magic_token', $token);
        $request->session()->put('gdf_magic_user_id', (int) $row->user_id);

        return redirect()->route('gdf.coordination.password.create')
            ->with('success', 'Enlace verificado. Ahora define tu contraseña.');
    }

    /**
     * GET /gdf/security/create-password
     * Formulario de creación de contraseña
     */
    public function create(Request $request)
    {
        if (!$request->session()->has('gdf_magic_token')) {
            return redirect()->route('login')
                ->with('error', 'Debes ingresar desde el enlace enviado a tu correo.');
        }

        // Reutiliza tu blade existente
        return view('auth.passwords.create_password', [
            'action' => route('gdf.coordination.password.store'),
        ]);
    }

    /**
     * POST /gdf/security/create-password
     * Guarda contraseña, marca token usado.
     */
    public function store(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $plainToken = (string) $request->session()->get('gdf_magic_token');
        $userId     = (int) $request->session()->get('gdf_magic_user_id');

        if (!$plainToken || !$userId) {
            return redirect()->route('login')
                ->with('error', 'Sesión inválida. Abre nuevamente el enlace del correo.');
        }

        $row = $this->getValidLoginTokenRow($plainToken);
        if (!$row || (int)$row->user_id !== $userId) {
            $this->clearMagicSession($request);
            return redirect()->route('login')
                ->with('error', 'El enlace expiró o ya fue usado. Solicita uno nuevo.');
        }

        $user = User::find($userId);
        if (!$user) {
            $this->clearMagicSession($request);
            return redirect()->route('login')->with('error', 'Usuario no encontrado.');
        }

        DB::transaction(function () use ($user, $row, $request) {
            $user->password = Hash::make($request->password);

            if (Schema::hasColumn('users', 'force_password_change')) {
                $user->force_password_change = 0;
            }

            $user->save();

            DB::table('login_tokens')->where('id', $row->id)->update([
                'used_at'    => now(),
                'updated_at' => now(),
            ]);
        });

        $this->clearMagicSession($request);

        return redirect()->route('login')
            ->with('success', 'Contraseña guardada correctamente. Ya puedes iniciar sesión.');
    }

    /* ========================= Helpers ========================= */

    private function clearMagicSession(Request $request): void
    {
        $request->session()->forget(['gdf_magic_token', 'gdf_magic_user_id']);
    }

    /**
     * Soporta:
     * - login_tokens.token_hash (sha256) + expires_at opcional
     * - o login_tokens.token (en claro)
     */
    private function getValidLoginTokenRow(string $plainToken): ?object
    {
        $q = DB::table('login_tokens');

        if (Schema::hasColumn('login_tokens', 'token_hash')) {
            $q->where('token_hash', hash('sha256', $plainToken));
        } else {
            $q->where('token', $plainToken);
        }

        $row = $q->first();
        if (!$row) return null;

        if (!empty($row->used_at)) return null;

        if (Schema::hasColumn('login_tokens', 'expires_at') && !empty($row->expires_at)) {
            if (now()->gt($row->expires_at)) return null;
        }

        return $row;
    }
}
