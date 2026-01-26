<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Modules\SICA\Entities\Role;
use Modules\SICA\Entities\Person;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Traits\UserTrait;
use Illuminate\Support\Facades\Hash;
use Modules\GDF\Entities\PersonAreaBudgetAssignment;


class User extends Authenticatable implements Auditable
{
    use SoftDeletes,
        HasApiTokens,
        Notifiable,
        UserTrait,
        \OwenIt\Auditing\Auditable;

    /**
     * Asignación masiva
     */
    protected $fillable = [
        'nickname',
        'person_id',
        'email',
        'image',
        'password',
        'force_password_change', // ✅ NUEVO (no afecta nada existente)
    ];

    /**
     * Campos ocultos
     */
    protected $hidden = [
        'password',
        'remember_token',
        'created_at',
        'updated_at'
    ];

    /**
     * Casts
     */
    protected $casts = [
        'email_verified_at'     => 'datetime',
        'force_password_change' => 'boolean', // ✅ NUEVO
    ];

    /**
     * Fechas
     */
    protected $dates = [
        'deleted_at'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * Tokens de acceso (link mágico)
     */
    public function loginTokens() // ✅ NUEVO (NO interfiere con nada)
    {
        return $this->hasMany(LoginToken::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS (NO OBLIGATORIOS, PERO ÚTILES)
    |--------------------------------------------------------------------------
    */

    public function mustChangePassword(): bool
    {
        return (bool) $this->force_password_change;
    }

    public function requirePasswordChange(): void
    {
        $this->force_password_change = true;
        $this->save();
    }

    public function clearPasswordChange(): void
    {
        $this->force_password_change = false;
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | BOOT (SE DEJA EXACTAMENTE IGUAL)
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {

            // ⚠️ NO SE TOCA ESTA LÓGICA
            if (empty($user->password)) {

                $first_name = Str::ascii($user->person->first_name);
                $first_last_name = Str::ascii($user->person->first_last_name);

                $password = Hash::make(
                    ucfirst(
                        strtolower(
                            substr($first_name, 0, 2)
                                . substr($first_last_name, 0, 2)
                                . substr($user->person->document_number, -4)
                        )
                    )
                );

                $user->password = $password;

                $user_id = $user->person->id;

                session(['passwords.' . $user_id => $password]);

                $password = session('passwords.' . $user_id);
            }
        });
    }

    public function gdfAreas()
    {
        return $this->belongsToMany(\Modules\GDF\Entities\Area::class, 'gdf_area_user', 'user_id', 'area_id')
            ->withPivot(['active', 'scope', 'assigned_by'])
            ->withTimestamps();
    }


    public function supervisedAssignments()
    {
        return $this->hasMany(PersonAreaBudgetAssignment::class, 'supervisor_id');
    }
    public function activeGdfAreas()
    {
        return $this->belongsToMany(\Modules\GDF\Entities\Area::class, 'gdf_area_user')
            ->withPivot(['scope', 'active'])
            ->wherePivot('active', 1);
    }
}
