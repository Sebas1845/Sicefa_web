<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BudgetPercentage extends Model
{
    use HasFactory;

    protected $table = 'budget_percentages';

    protected $fillable = [
        'budget_id', 'percentage', 'active',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }
}
