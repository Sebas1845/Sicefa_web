<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\GDF\Entities\Area;
use Modules\GDF\Entities\BudgetItem;
use Modules\GDF\Entities\BudgetPercentage;
use Modules\GDF\Entities\BudgetAddition;
use Modules\GDF\Entities\BudgetMovement;
use Modules\GDF\Entities\TravelRequest;

class Budget extends Model
{
    use HasFactory;

    protected $table = 'budgets';

    protected $fillable = [
        'year', 'area_id', 'budget_item_id',
        'initial_amount', 'current_amount', 'active',
    ];

    protected $casts = [
        'year' => 'integer',
        'initial_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }

    public function percentages()
    {
        return $this->hasMany(BudgetPercentage::class, 'budget_id');
    }

    public function additions()
    {
        return $this->hasMany(BudgetAddition::class, 'budget_id');
    }

    public function movements()
    {
        return $this->hasMany(BudgetMovement::class, 'budget_id');
    }

    public function travelRequests()
    {
        return $this->hasMany(TravelRequest::class, 'budget_id');
    }
}
