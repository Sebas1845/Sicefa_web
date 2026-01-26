<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class AreaUser extends Model
{
    protected $table = 'gdf_area_user';

    protected $fillable = [
        'user_id',
        'area_id',
        'assigned_by',
        'active',
        'scope',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_by');
    }
}
