<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\GDF\Entities\TravelSegment;

class MunicipalityRate extends Model
{
    use HasFactory;

    protected $table = 'municipality_rates';

    protected $fillable = [
        'municipality_name', 'transport_amount', 'active',
    ];

    protected $casts = [
        'transport_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function travelSegments()
    {
        return $this->hasMany(TravelSegment::class, 'municipality_rate_id');
    }
}
