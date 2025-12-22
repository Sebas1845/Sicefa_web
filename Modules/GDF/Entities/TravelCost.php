<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelCost extends Model
{
    use HasFactory;

    protected $table = 'travel_costs';

    protected $fillable = [
        'travel_request_id', 'cost_type', 'description', 'amount', 'applies_to',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }
}
