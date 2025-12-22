<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelReview extends Model
{
    use HasFactory;

    protected $table = 'travel_reviews';

    protected $fillable = [
        'travel_request_id', 'reviewer_id', 'action', 'comments',
    ];

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }
}
