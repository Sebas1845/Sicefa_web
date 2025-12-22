<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VillageRate extends Model
{
    use HasFactory;

    protected $table = 'village_rates';

    protected $fillable = [
        'village_name', 'municipality_name', 'transport_amount', 'active',
    ];

    protected $casts = [
        'transport_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function travelSegments()
    {
        return $this->hasMany(TravelSegment::class, 'village_rate_id');
    }
}
