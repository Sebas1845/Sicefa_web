<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\GDF\Entities\Area;
use App\Models\User;

class MotorcycleAreaQuota extends Model
{
    use HasFactory;

    protected $table = 'motorcycle_area_quotas';

    protected $fillable = [
        'year',
        'area_id',
        'quota_total',
        'active',
        'set_by',
        'notes',
    ];

    protected $casts = [
        'year'   => 'integer',
        'area_id'=> 'integer',
        'quota_total'  => 'integer',
        'active' => 'boolean',
    ];

    // -------------------------
    // Relaciones
    // -------------------------
    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function setter()
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    // -------------------------
    // Scopes útiles
    // -------------------------
    public function scopeActive($q)
    {
        return $q->where('active', true);
    }

    public function scopeYear($q, int $year)
    {
        return $q->where('year', $year);
    }

    public function scopeForArea($q, int $areaId)
    {
        return $q->where('area_id', $areaId);
    }

    // -------------------------
    // Helper (opcional)
    // -------------------------
    public static function quotaFor(int $areaId, int $year): int
    {
        return (int) static::query()
            ->active()
            ->where('area_id', $areaId)
            ->where('year', $year)
            ->value('quota_total') ?? 0;
    }
}
