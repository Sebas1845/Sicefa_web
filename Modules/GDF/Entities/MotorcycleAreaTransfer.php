<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class MotorcycleAreaTransfer extends Model
{
    protected $table = 'motorcycle_area_transfers';

    protected $fillable = [
        'motorcycle_id','from_area_id','to_area_id','assigned_by','assigned_at','notes'
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function motorcycle()
    {
        return $this->belongsTo(Motorcycle::class, 'motorcycle_id');
    }

    public function fromArea()
    {
        return $this->belongsTo(Area::class, 'from_area_id');
    }

    public function toArea()
    {
        return $this->belongsTo(Area::class, 'to_area_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }
}
