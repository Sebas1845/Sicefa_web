<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BudgetAddition extends Model
{
    use HasFactory;

    protected $table = 'budget_additions';

    protected $fillable = [
        'budget_id',
        'amount',
        'justification',

        // Auditoría (NUEVO)
        'created_by',
        'created_area_id',

        // Aplicación (ya existía)
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'approved_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    /**
     * Usuario que creó la adición (Apoyo)
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Área desde donde se creó (académica/campesena)
     * Ajusta el modelo Area si en tu proyecto está en otro namespace.
     */
    public function createdArea()
    {
        return $this->belongsTo(\Modules\GDF\Entities\Area::class, 'created_area_id');
        // Si tu tabla de áreas NO es del módulo GDF, cambia a tu modelo real:
        // return $this->belongsTo(\Modules\SICA\Entities\Area::class, 'created_area_id');
    }

    /**
     * Usuario que aplicó/aprobó la adición
     */
    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    /**
     * Scope útil: pendientes por aplicar
     */
    public function scopePending($query)
    {
        return $query->whereNull('approved_at');
    }

    /**
     * Scope útil: aplicadas
     */
    public function scopeApplied($query)
    {
        return $query->whereNotNull('approved_at');
    }
}
