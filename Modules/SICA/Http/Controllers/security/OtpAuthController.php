<?php

namespace Modules\SICA\Http\Controllers\security;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Apprentice;
use Modules\SICA\Entities\LoginOtp;
use Modules\SICA\Entities\Role;
use Modules\SICA\Entities\AccountActivation;
use App\Models\User;

use App\Mail\AUTH\LoginOtpMail;

class OtpAuthController extends Controller
{
    /** Config OTP */
    private int $otpLength = 6;
    private int $otpTtlMinutes = 10;
    private int $maxAttempts = 5;

    /** GET /otp-login */
    public function showDocumentForm()
    {
        return view('sica::auth.otp.document');
    }

    /**
     * POST /otp-login
     * Unifica "registro" + "solicitar código":
     * - NO crea usuario todavía
     * - Solo genera OTP y lo envía
     */
    public function registerOrSendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'string', 'max:30'],
        ], [
            'document_number.required' => 'El documento es requerido.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $document = $this->normalizeDocument((string) $request->input('document_number'));
        if ($document === '' || strlen($document) < 6) {
            return back()->withInput()->with('error', 'Documento inválido.');
        }

        $person = Person::where('document_number', $document)->first();
        if (!$person) {
            return back()->withInput()->with('error', 'Persona no encontrada.');
        }

        // SOLO aprendices
        if (!$this->isEligible((int)$person->id)) {
            return back()->withInput()->with('error', 'Este acceso es exclusivo para aprendices.');
        }

        // SOLO personal_email
        $email = $this->pickEmail($person);
        if (!$email) {
            return back()->withInput()->with('error', 'No tienes correo personal registrado (personal_email). Contacta al administrador.');
        }

        // Generar OTP
        $otp = $this->generateOtp($this->otpLength);
        $otpHash = hash('sha256', $otp);

        // Invalidar OTPs anteriores activos
        LoginOtp::where('person_id', $person->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        // Guardar OTP
        LoginOtp::create([
            'person_id'   => $person->id,
            'email_used'  => $email,
            'otp_hash'    => $otpHash,
            'attempts'    => 0,
            'expires_at'  => now()->addMinutes($this->otpTtlMinutes),
            'consumed_at' => null,
            'created_ip'  => $request->ip(),
            'user_agent'  => substr((string) $request->userAgent(), 0, 255),
        ]);

        // Enviar correo
        try {
            Mail::to($email)->send(new LoginOtpMail($otp, $this->otpTtlMinutes));
        } catch (\Throwable $e) {
            logger()->error('OTP Mail send failed', [
                'person_id' => $person->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'No fue posible enviar el código al correo. Intenta más tarde.');
        }

        return redirect()
            ->route('otp.login.verify.form', ['document_number' => $document])
            ->with('success', 'Te enviamos un código de seguridad a tu correo personal.');
    }

    /** GET /otp-login/verify */
    public function showOtpForm(Request $request)
    {
        $document = $this->normalizeDocument((string) $request->query('document_number', ''));
        return view('auth.verify', ['document_number' => $document]);
    }

    /** POST /otp-login/verify */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'string', 'max:30'],
            'code'            => ['required', 'string', 'min:4', 'max:10'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $document = $this->normalizeDocument((string) $request->input('document_number'));
        $code = preg_replace('/\s+/', '', (string) $request->input('code'));

        $person = Person::where('document_number', $document)->first();
        if (!$person) {
            return back()->withInput()->with('error', 'Código inválido o expirado.');
        }

        if (!$this->isEligible((int) $person->id)) {
            return back()->withInput()->with('error', 'No autorizado para iniciar sesión.');
        }

        $otpRow = LoginOtp::where('person_id', $person->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$otpRow) {
            return back()->withInput()->with('error', 'Código inválido o expirado. Solicita un nuevo código.');
        }

        $isValid = hash('sha256', $code) === $otpRow->otp_hash;

        if (!$isValid) {
            $otpRow->increment('attempts');

            if ($otpRow->attempts >= $this->maxAttempts) {
                $otpRow->consumed_at = now();
                $otpRow->save();
                return back()->withInput()->with('error', 'Demasiados intentos. Solicita un nuevo código.');
            }

            return back()->withInput()->with('error', 'Código inválido. Intenta nuevamente.');
        }

        // Consumir OTP
        $otpRow->consumed_at = now();
        $otpRow->save();

        $emailUsed = strtolower(trim((string) $otpRow->email_used));
        if ($emailUsed === '' || !str_contains($emailUsed, '@')) {
            return back()->withInput()->with('error', 'No se encontró un correo válido para iniciar sesión. Solicita un nuevo código.');
        }

        // Bloqueo por email ya usado por otra persona (users.email unique)
        $otherUser = User::where('email', $emailUsed)
            ->where('person_id', '!=', $person->id)
            ->exists();

        if ($otherUser) {
            return back()->withInput()->with('error', 'Este correo ya está registrado con otra persona. Contacta al administrador.');
        }

        try {
            $user = DB::transaction(function () use ($person, $emailUsed) {

                // Crear usuario si no existe
                $user = User::where('person_id', $person->id)->first();

                if (!$user) {
                    $user = new User();
                    $user->person_id = $person->id;
                    $user->nickname  = $this->generateNickname($person);
                    $user->email     = $emailUsed;
                    $user->password  = Hash::make(Str::random(32));
                    $user->save();
                } else {
                    if (empty($user->email)) {
                        $user->email = $emailUsed;
                        $user->save();
                    }
                }

                // Rol aprendiz por slug exacto
                $roleId = Role::where('slug', 'sigac.apprentice')->value('id');

                if ($roleId) {
                    $user->roles()->syncWithoutDetaching([$roleId]);
                } else {
                    logger()->warning('Rol sigac.apprentice no encontrado', [
                        'person_id' => $person->id,
                        'user_id'   => $user->id,
                    ]);
                }

                // Activación + FORZAR cambio de contraseña
                $activation = AccountActivation::firstOrCreate(
                    ['person_id' => $person->id],
                    [
                        'activated_at'         => null,
                        'last_login_at'        => null,
                        'must_change_password' => true,
                    ]
                );

                if (is_null($activation->activated_at)) {
                    $activation->activated_at = now();
                }

                $activation->last_login_at = now();
                $activation->must_change_password = true;
                $activation->save();

                return $user;
            });
        } catch (\Throwable $e) {
            logger()->error('OTP verify login failed', [
                'person_id'  => $person->id ?? null,
                'email_used' => $emailUsed ?? null,
                'error'      => $e->getMessage(),
            ]);

            return back()->withInput()->with('error', 'No fue posible iniciar sesión. Intenta nuevamente.');
        }

        // Evita redirecciones heredadas al módulo SICA
        $request->session()->forget('url.intended');

        Auth::login($user);

        // CAMBIO: redirigir al formulario OTP password (names nuevos)
        return redirect()->route('otp.login.password.form');
    }

    /** GET /otp-login/password */
    public function showPasswordChangeForm(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $personId = (int) (Auth::user()->person_id ?? 0);
        if ($personId <= 0) {
            Auth::logout();
            return redirect()->route('login')->with('error', 'Sesión inválida.');
        }

        $activation = AccountActivation::where('person_id', $personId)->first();

        // Si por alguna razón ya no debe cambiar contraseña, manda a CEFA (NO a SICA)
        if ($activation && !$activation->must_change_password) {
            return redirect()->route('cefa.welcome'); // o cefa.index
        }

        return view('auth.passwords.otpchange_password', [
            'person' => Auth::user()->person,
            'title' => 'Cambiar contraseña'
        ]);
    }

    /** POST /otp-login/password */
    public function savePasswordChange(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'new_password.required'  => 'La nueva contraseña es requerida.',
            'new_password.min'       => 'La contraseña debe tener mínimo 8 caracteres.',
            'new_password.confirmed' => 'La confirmación no coincide.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $authUser = Auth::user();

        try {
            DB::transaction(function () use ($authUser, $request) {
                $userModel = User::findOrFail($authUser->id);
                $userModel->password = Hash::make((string) $request->input('new_password'));
                $userModel->save();

                $activation = AccountActivation::where('person_id', $userModel->person_id)->first();
                if ($activation) {
                    $activation->must_change_password = false;
                    $activation->save();
                }
            });
        } catch (\Throwable $e) {
            logger()->error('OTP password change failed', [
                'user_id' => $authUser->id ?? null,
                'error'   => $e->getMessage(),
            ]);

            return back()->with('error', 'No fue posible cambiar la contraseña. Intenta nuevamente.');
        }

        // Evita que se “pegue” un intended a /sica/index
        $request->session()->forget('url.intended');

        // CAMBIO: salida a CEFA
        return redirect()->route('cefa.welcome') // o cefa.index
            ->with('success', 'Contraseña actualizada correctamente.');
    }

    /* ========================= Helpers ========================= */

    private function isEligible(int $personId): bool
    {
        return Apprentice::where('person_id', $personId)->exists();
    }

    private function pickEmail(Person $person): ?string
    {
        $email = strtolower(trim((string) ($person->personal_email ?? '')));
        if ($email !== '' && str_contains($email, '@')) {
            return $email;
        }
        return null;
    }

    private function generateOtp(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }

    private function normalizeDocument(string $document): string
    {
        return preg_replace('/\D+/', '', trim($document));
    }

    private function generateNickname(Person $person): string
    {
        $base = Str::ascii(($person->first_name ?? '') . ($person->first_last_name ?? ''));
        $base = strtoupper(preg_replace('/\s+/', '', $base));
        $base = substr($base, 0, 6);

        if ($base === '') $base = 'USER';

        $suffix = substr((string) ($person->document_number ?? ''), -2);
        return $base . $suffix;
    }
}
