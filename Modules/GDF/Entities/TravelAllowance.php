<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelAllowance extends Model
{
    protected $table = 'travel_allowances';

    // Tipos (ajústalos a tu negocio)
    public const TYPE_FUEL     = 'fuel';
    public const TYPE_LODGING  = 'lodging';
    public const TYPE_MEALS    = 'meals';
    public const TYPE_PER_DIEM = 'per_diem';

    // Estados típicos (ajústalos si tienes otros)
    public const ST_DRAFT     = 'draft';
    public const ST_LIQUIDATED= 'liquidated';
    public const ST_APPROVED  = 'approved';
    public const ST_REJECTED  = 'rejected';

    protected $fillable = [
        'travel_request_id',
        'allowance_type',
        'status',

        'unit_amount',
        'units',
        'calculated_amount',
        'approved_amount',

        'description',
        'applies_to',
        'budget_item_id',
        'created_by',
    ];

    protected $casts = [
        'travel_request_id'  => 'integer',
        'budget_item_id'     => 'integer',
        'created_by'         => 'integer',
        'units'              => 'integer',

        'unit_amount'        => 'decimal:2',
        'calculated_amount'  => 'decimal:2',
        'approved_amount'    => 'decimal:2',
    ];

    // =========================
    // RELACIONES
    // =========================
    public function travelRequest(): BelongsTo
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    // Si tu BudgetItem está en otro módulo, cambia el namespace
    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(\Modules\GDF\Entities\BudgetItem::class, 'budget_item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    // =========================
    // SCOPES ÚTILES
    // =========================
    public function scopeActive($q)
    {
        return $q->whereIn('status', [self::ST_DRAFT, self::ST_LIQUIDATED, self::ST_APPROVED]);
    }

    public function scopeType($q, string $type)
    {
        return $q->where('allowance_type', $type);
    }
}
