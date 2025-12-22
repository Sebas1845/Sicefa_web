<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BudgetMovement extends Model
{
    use HasFactory;

    protected $table = 'budget_movements';
    public $timestamps = false; // only created_at

    protected $fillable = [
        'budget_id', 'travel_request_id', 'type', 'amount', 'description', 'created_by', 'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }
}
