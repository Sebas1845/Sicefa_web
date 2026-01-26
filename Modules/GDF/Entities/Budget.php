<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Budget extends Model
{
    use HasFactory;

    protected $table = 'budgets';

    protected $fillable = [
        'year',
        'area_id',
        'budget_item_id',
        'initial_amount',
        'current_amount',
        'active',
    ];

    protected $casts = [
        'year'           => 'integer',
        'initial_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'active'         => 'boolean',
    ];

    // -------------------------
    // RELACIONES BASE (seguras)
    // -------------------------
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }


    // -------------------------
    // RELACIONES OPCIONALES
    // Solo déjalas si tus tablas tienen budget_id
    // -------------------------
    public function movements()
    {
        // Requiere: budget_movements.budget_id
        return $this->hasMany(BudgetMovement::class, 'budget_id');
    }

    public function additions()
    {
        // Requiere: budget_additions.budget_id
        return $this->hasMany(BudgetAddition::class, 'budget_id');
    }

    public function travelRequests()
    {
        // Requiere: travel_requests.budget_id
        return $this->hasMany(TravelRequest::class, 'budget_id');
    }

    // -------------------------
    // HELPERS ÚTILES
    // -------------------------
    public function percentageFor(string $module): float
    {
        $p = $this->percentages->firstWhere('module', $module);
        return (float) ($p->percentage ?? 0);
    }

    public function cupFor(string $module): float
    {
        return (float) $this->current_amount * ($this->percentageFor($module) / 100);
    }
    public function areaAllocations()
    {
        return $this->hasMany(
            \Modules\GDF\Entities\BudgetAreaAllocation::class,
            'budget_id'
        );
    }

    public function percentages()
    {
        return $this->hasMany(
            \Modules\GDF\Entities\BudgetPercentage::class,
            'budget_id'
        );
    }
}
