<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class TravelCost extends Model
{
    protected $table = 'travel_costs';

    protected $fillable = [
        'travel_request_id',
        'cost_type',
        'description',
        'amount',
        'applies_to',
    ];

    public function request()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }
}
