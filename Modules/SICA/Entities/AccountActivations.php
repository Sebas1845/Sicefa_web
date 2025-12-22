<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountActivation extends Model
{
    protected $table = 'account_activations';

    protected $fillable = [
        'person_id',
        'activated_at',
        'last_login_at',
        'must_change_password',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
        'last_login_at' => 'datetime',
        'must_change_password' => 'boolean',
    ];

    /**
     * Marca la cuenta como activada (primer ingreso)
     */
    public function activate(): void
    {
        if (is_null($this->activated_at)) {
            $this->activated_at = now();
            $this->must_change_password = true;
        }

        $this->last_login_at = now();
        $this->save();
    }

    /**
     * Marca que ya no es necesario cambiar la contraseña
     */
    public function passwordChanged(): void
    {
        $this->must_change_password = false;
        $this->save();
    }
}

