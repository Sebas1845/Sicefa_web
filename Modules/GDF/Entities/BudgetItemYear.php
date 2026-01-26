<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BudgetItemYear extends Model
{
    use HasFactory;

    protected $table = 'budget_item_years';

    protected $fillable = [
        'budget_item_id',
        'year',
        'active',
        'starts_on',
        'ends_on',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
        'year'   => 'integer',
        'starts_on' => 'date',
        'ends_on'   => 'date',
    ];

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }
}
