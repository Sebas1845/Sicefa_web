<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class TravelSegment extends Model
{
    protected $table = 'travel_segments';

    protected $fillable = [
        'travel_request_id',
        'departure_at',
        'return_at',

        'origin_place',
        'destination_place',
        'destination_type',

        'municipality_rate_id',
        'village_rate_id',

        'transport_type',
        'trip_type',
        'trips',

        'is_cancelled',
        'change_reason',

        'transport_cost',
        'per_diem_cost',
        'other_cost',
        'total_cost',

        'notes',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'return_at'    => 'datetime',
        'is_cancelled' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    public function municipalityRate()
    {
        return $this->belongsTo(MunicipalityRate::class);
    }

    public function villageRate()
    {
        return $this->belongsTo(VillageRate::class);
    }

    public function calculateTotal(): float
    {
        return (
            ($this->transport_cost * $this->trips) +
            $this->per_diem_cost +
            $this->other_cost
        );
    }
}
