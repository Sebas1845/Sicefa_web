<?php

namespace Modules\SICA\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class LoginOtp extends Model
{
    protected $table = 'login_otps';

    protected $fillable = [
        'person_id',
        'email_used',
        'otp_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'created_ip',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /**
     * Indica si el OTP está expirado
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Indica si el OTP ya fue consumido
     */
    public function isConsumed(): bool
    {
        return !is_null($this->consumed_at);
    }

    /**
     * Marca el OTP como usado
     */
    public function consume(): void
    {
        $this->consumed_at = now();
        $this->save();
    }

    /**
     * Incrementa intentos fallidos
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    /**
     * Verifica un código OTP contra el hash almacenado
     */
    public function verifyCode(string $code): bool
    {
        return hash('sha256', $code) === $this->otp_hash;
    }

    /**
     * Scope: solo OTPs válidos (no expirados ni consumidos)
     */
    public function scopeValid($query)
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }
}
