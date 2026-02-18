<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TravelDocument extends Model
{
    use SoftDeletes;

    protected $table = 'travel_request_documents';

    protected $fillable = [
        'travel_request_id',
        'document_type',
        'title',
        'original_name',
        'stored_name',
        'path',
        'disk',
        'mime_type',
        'size_bytes',
        'status',
        'notes',
        'review_notes',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
        'is_required',
    ];

    protected $casts = [
        'size_bytes'  => 'integer',
        'is_required' => 'boolean',
        'reviewed_at' => 'datetime',
        'deleted_at'  => 'datetime',
    ];

    // Opcional: constantes para evitar strings mágicos
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';

    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(\App\Models\User::class, 'uploaded_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }

    public function scopeSubmitted($q)
    {
        return $q->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) ($this->size_bytes ?? 0);
        if ($bytes <= 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return rtrim(rtrim(number_format($bytes, 2, ',', '.'), '0'), ',') . ' ' . $units[$i];
    }

    public function getIsPdfAttribute(): bool
    {
        return (string)($this->mime_type ?? '') === 'application/pdf';
    }
}
