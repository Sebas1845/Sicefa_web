<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    protected $table = 'attachments';

    protected $fillable = [
        'travel_request_id',
        'file_path',
        'file_type',
        'category',
        'required_for',
        'approved',
        'amount',
        'uploaded_by',
    ];

    protected $casts = [
        'approved' => 'boolean',
    ];

    public function request()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }
}
