<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelSegment extends Model
{
    use HasFactory;

    protected $table = 'travel_segments';

    protected $fillable = [
        'travel_request_id',
        'departure_at','return_at',
        'origin_place','destination_place','destination_type',
        'municipality_rate_id','village_rate_id',
        'transport_type',
        'transport_cost','per_diem_cost','other_cost','total_cost',
        'notes',
    ];

    protected $casts = [
        'departure_at' => 'datetime',
        'return_at' => 'datetime',
        'transport_cost' => 'decimal:2',
        'per_diem_cost' => 'decimal:2',
        'other_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    public function municipalityRate()
    {
        return $this->belongsTo(MunicipalityRate::class, 'municipality_rate_id');
    }

    public function villageRate()
    {
        return $this->belongsTo(VillageRate::class, 'village_rate_id');
    }
}
