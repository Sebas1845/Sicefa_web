<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Contractor;
use App\Models\User;

class PersonAreaBudgetAssignment extends Model
{
    protected $table = 'person_area_budget_assignments';

    protected $fillable = [
        'person_id',
        'contractor_id',
        'area_id',
        'budget_item_id',
        'supervisor_id',
        'start_date',
        'end_date',
        'is_active',
        'is_primary',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'is_active'  => 'boolean',
        'is_primary' => 'boolean',
    ];

    /* =========================
     |        RELACIONES
     ========================= */

    /** Persona base (People) */
    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    /** Contrato (solo si es contratista) */
    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    /** Área GDF */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /** Rubro / Budget Item */
    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class);
    }

    /** Supervisor (usuario coordinador) */
    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /* =========================
     |        SCOPES
     ========================= */

    /** Solo asignaciones activas */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Asignaciones vigentes hoy */
    public function scopeCurrentlyValid($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', now()->toDateString());
        })->where(function ($q) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', now()->toDateString());
        });
    }

    /** Asignaciones principales */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /* =========================
     |      HELPERS LÓGICOS
     ========================= */

    /** ¿Está vigente hoy? */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_date && now()->lt($this->start_date)) {
            return false;
        }

        if ($this->end_date && now()->gt($this->end_date)) {
            return false;
        }

        return true;
    }
}
