<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\GDF\Entities\Budget;
use Modules\GDF\Entities\TravelRequest;

class BudgetItem extends Model
{
    use HasFactory;

    protected $table = 'budget_items';

    protected $fillable = [
        'code', 'name', 'description', 'allow_staff', 'allow_contractors', 'active',
    ];

    protected $casts = [
        'allow_staff' => 'boolean',
        'allow_contractors' => 'boolean',
        'active' => 'boolean',
    ];

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'budget_item_id');
    }

    public function travelRequests()
    {
        return $this->hasMany(TravelRequest::class, 'budget_item_id');
    }
}
