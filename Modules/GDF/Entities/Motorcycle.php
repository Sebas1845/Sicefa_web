<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class Motorcycle extends Model
{
    protected $table = 'motorcycles';

    protected $fillable = [
        'plate','brand','model','entry_date',
        'current_area_id','current_odometer','status','created_by'
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    public function currentArea()
    {
        return $this->belongsTo(Area::class, 'current_area_id');
    }

    public function transfers()
    {
        return $this->hasMany(MotorcycleAreaTransfer::class, 'motorcycle_id')->latest();
    }

    public function assignments()
    {
        return $this->hasMany(MotorcycleAssignment::class, 'motorcycle_id')->latest();
    }

    public function incidents()
    {
        return $this->hasMany(MotorcycleIncident::class, 'motorcycle_id')->latest();
    }

    public function maintenances()
    {
        return $this->hasMany(MotorcycleMaintenance::class, 'motorcycle_id')->latest();
    }
}