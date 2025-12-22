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
use Modules\SICA\Entities\Employee;
use Modules\SICA\Entities\Contractor;

use App\Models\User;
use App\Models\LoginOtp;
use App\Models\AccountActivation;

use App\Mail\LoginOtpMail;

class OtpAuthController extends Controller
{
    /** Config OTP */
    private int $otpLength = 6;
    private int $otpTtlMinutes = 10;
    private int $maxAttempts = 5;

    /**
     * Formulario: ingresar documento
     */
    public function showDocumentForm()
    {
        return view('sica::auth.otp.document');
    }

    /**
     * POST: recibe documento, valida persona/rol, genera OTP y envía al correo
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'string', 'max:30'],
        ], [
            'document_number.required' => 'El documento es requerido.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $document = $this->normalizeDocument($request->input('document_number'));

        $person = Person::where('document_number', $document)->first();
        if (!$person) {
            return back()->withInput()->with('error', 'No autorizado o no encontrado.');
        }

        if (!$this->isEligible($person->id)) {
            return back()->withInput()->with('error', 'No autorizado para iniciar sesión.');
        }

        $email = $this->pickEmail($person);
        if (!$email) {
            return back()->withInput()->with('error', 'No tienes un correo registrado para validación. Contacta al administrador.');
        }

        // Generar OTP
        $otp = $this->generateOtp($this->otpLength);
        $otpHash = hash('sha256', $otp);

        // Invalidate OTPs previos activos (opcional pero recomendado para evitar confusión)
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
            return back()->withInput()->with('error', 'No fue posible enviar el código al correo. Intenta más tarde.');
        }

        // Redirigir al formulario de verificación OTP (manteniendo documento)
        return redirect()->route('otp.login.verify.form', ['document_number' => $document])
            ->with('success', 'Te enviamos un código de seguridad a tu correo.');
    }

    /**
     * Formulario: ingresar OTP
     */
    public function showOtpForm(Request $request)
    {
        $document = $this->normalizeDocument((string) $request->query('document_number', ''));

        return view('sica::auth.otp.verify', [
            'document_number' => $document,
        ]);
    }

    /**
     * POST: valida OTP y autentica
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_number' => ['required', 'string', 'max:30'],
            'code'            => ['required', 'string', 'min:4', 'max:10'],
        ], [
            'document_number.required' => 'El documento es requerido.',
            'code.required'            => 'El código es requerido.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $document = $this->normalizeDocument($request->input('document_number'));
        $code = trim((string) $request->input('code'));

        $person = Person::where('document_number', $document)->first();
        if (!$person) {
            return back()->withInput()->with('error', 'Código inválido o expirado.');
        }

        if (!$this->isEligible($person->id)) {
            return back()->withInput()->with('error', 'No autorizado para iniciar sesión.');
        }

        // Tomar el último OTP válido
        $otpRow = LoginOtp::where('person_id', $person->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (!$otpRow) {
            return back()->withInput()->with('error', 'Código inválido o expirado. Solicita un nuevo código.');
        }

        // Verificar OTP
        $isValid = hash('sha256', $code) === $otpRow->otp_hash;

        if (!$isValid) {
            $otpRow->increment('attempts');

            if ($otpRow->attempts >= $this->maxAttempts) {
                // Invalida OTP (marcar consumido)
                $otpRow->consumed_at = now();
                $otpRow->save();

                return back()->withInput()->with('error', 'Demasiados intentos. Solicita un nuevo código.');
            }

            return back()->withInput()->with('error', 'Código inválido. Intenta nuevamente.');
        }

        // Consumir OTP
        $otpRow->consumed_at = now();
        $otpRow->save();

        // Determinar correo (puede usar el email_used que se envió)
        $emailUsed = $otpRow->email_used;

        // Autenticación: obtener o crear User por person_id
        DB::beginTransaction();
        try {
            $user = User::where('person_id', $person->id)->first();

            $createdNow = false;

            if (!$user) {
                $createdNow = true;
                $user = new User();
                $user->person_id = $person->id;
                $user->nickname = $this->generateNickname($person);
                $user->email = $emailUsed;

                // Password fuerte aleatoria (no se envía por correo)
                $user->password = Hash::make(Str::random(24));
                $user->save();

                // Nota: NO asigno roles aquí para no afectar tu sistema actual.
                // Si lo deseas, se puede asignar rol según si es apprentice/employee/contractor.
            } else {
                // Mantener email coherente con el usado para OTP (opcional)
                if (!empty($emailUsed) && $user->email !== $emailUsed) {
                    // Si ya hay un user con otro correo, NO lo cambio automáticamente si no quieres afectar el sistema.
                    // Si quieres sincronizar, cambia esta regla.
                }
            }

            // Activación / forzar cambio de contraseña
            $activation = AccountActivation::firstOrCreate(
                ['person_id' => $person->id],
                [
                    'activated_at' => null,
                    'last_login_at' => null,
                    'must_change_password' => true,
                ]
            );

            if (is_null($activation->activated_at)) {
                $activation->activated_at = now();
                $activation->must_change_password = true;
            }

            $activation->last_login_at = now();

            // Si el user fue creado en este momento, forzamos sí o sí
            if ($createdNow) {
                $activation->must_change_password = true;
            }

            $activation->save();

            // Login
            Auth::login($user);

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'No fue posible iniciar sesión. Intenta nuevamente.');
        }

        // Redirección: si debe cambiar contraseña
        $activation = AccountActivation::where('person_id', $person->id)->first();
        if ($activation && $activation->must_change_password) {
            return redirect()->route('otp.password.change.form');
        }

        return redirect()->intended(route('cefa.home'));
    }

    /**
     * Valida si la persona puede usar OTP login:
     * - aprendiz (apprentices)
     * - instructor contratista (contractors)
     * - instructor planta (employees)
     */
    private function isEligible(int $personId): bool
    {
        $isApprentice = Apprentice::where('person_id', $personId)->exists();
        $isContractor = Contractor::where('person_id', $personId)->exists();
        $isEmployee   = Employee::where('person_id', $personId)->exists();

        return $isApprentice || $isContractor || $isEmployee;
    }

    /**
     * Selecciona correo con prioridad: misena > sena > personal
     */
    private function pickEmail(Person $person): ?string
    {
        $candidates = [
            $person->misena_email ?? null,
            $person->sena_email ?? null,
            $person->personal_email ?? null,
        ];

        foreach ($candidates as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && str_contains($email, '@')) {
                return $email;
            }
        }
        return null;
    }

    /**
     * Genera OTP numérico
     */
    private function generateOtp(int $length = 6): string
    {
        $min = (int) pow(10, $length - 1);
        $max = (int) pow(10, $length) - 1;
        return (string) random_int($min, $max);
    }

    /**
     * Normaliza documento: solo dígitos (si manejas CE con letras, ajusta)
     */
    private function normalizeDocument(string $document): string
    {
        $document = trim($document);
        // Si tus documentos pueden tener letras (CE), elimina esta línea y solo haz trim.
        return preg_replace('/\D+/', '', $document);
    }

    /**
     * Nickname base (si el user no existe)
     */
    private function generateNickname(Person $person): string
    {
        $base = Str::ascii(($person->first_name ?? '') . ($person->first_last_name ?? ''));
        $base = strtoupper(preg_replace('/\s+/', '', $base));
        $base = substr($base, 0, 6);

        if ($base === '') {
            $base = 'USER';
        }

        // Evitar colisión simple
        $suffix = substr((string) ($person->document_number ?? ''), -2);
        return $base . $suffix;
    }
}
