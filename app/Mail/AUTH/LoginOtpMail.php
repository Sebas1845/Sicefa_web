<?php

namespace App\Mail\AUTH;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $code,
        public int $ttlMinutes = 10
    ) {}

    public function build()
    {
        return $this->subject('Código de seguridad - Inicio de sesión')
            ->view('sica::auth.otp.email_code')
            ->with([
                'code' => $this->code,
                'ttlMinutes' => $this->ttlMinutes,
            ]);
    }
}
