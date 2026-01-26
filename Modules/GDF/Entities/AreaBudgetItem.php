<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AreaBudgetItem extends Model
{
    use HasFactory;

    protected $table = 'area_budget_items';

    protected $fillable = [
        'area_id',
        'budget_item_id',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // Relaciones
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }

    // Scopes útiles
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }
}
