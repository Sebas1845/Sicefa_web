<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\GDF\Entities\Budget;
use Modules\GDF\Entities\TravelRequest;

class Area extends Model
{
    use HasFactory;

    protected $table = 'areas';

    protected $fillable = [
        'name', 'description', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'area_id');
    }

    public function travelRequests()
    {
        return $this->hasMany(TravelRequest::class, 'area_id');
    }
}
