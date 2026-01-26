<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class TravelReview extends Model
{
    protected $table = 'travel_reviews';

    protected $fillable = [
        'travel_request_id',
        'reviewer_id',
        'action',
        'comments',
    ];

    public function request()
    {
        return $this->belongsTo(TravelRequest::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewer_id');
    }
}
