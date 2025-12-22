<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BudgetAddition extends Model
{
    use HasFactory;

    protected $table = 'budget_additions';

    protected $fillable = [
        'budget_id', 'amount', 'justification', 'approved_by', 'approved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }
}
