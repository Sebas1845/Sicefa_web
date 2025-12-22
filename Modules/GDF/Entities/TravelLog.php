<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelLog extends Model
{
    use HasFactory;

    protected $table = 'travel_logs';
    public $timestamps = false; // only created_at

    protected $fillable = [
        'travel_request_id', 'user_id', 'action', 'description', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }
}
