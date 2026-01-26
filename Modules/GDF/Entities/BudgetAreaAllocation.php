<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BudgetAreaAllocation extends Model
{
    use HasFactory;

    protected $table = 'budget_area_allocations';

    protected $fillable = [
        'budget_id',
        'area_id',
        'percentage',
        'allocated_amount',
        'active',
    ];

    protected $casts = [
        'percentage'       => 'decimal:2',
        'allocated_amount' => 'decimal:2',
        'active'           => 'boolean',
    ];

    /* =====================
     * Relaciones
     * ===================== */

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
}
