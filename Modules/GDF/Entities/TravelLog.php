<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class TravelLog extends Model
{
    protected $table = 'travel_logs';

    public $timestamps = false;

    protected $fillable = [
        'travel_request_id',
        'user_id',
        'action',
        'description',
        'created_at',
    ];

    public function request()
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

