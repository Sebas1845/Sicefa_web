<?php

namespace Modules\SIGAC\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnvironmentRound extends Model
{
    use HasFactory;

    protected $table = 'environment_rounds';

    protected $fillable = [
        'date',
        'shift',
        'created_by',
        'started_at',
        'finished_at',
        'is_locked',
    ];

    protected $casts = [
        'date'        => 'date',
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'is_locked'   => 'boolean',
    ];

    // ------------- Relaciones -------------

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries()
    {
        return $this->hasMany(EnvironmentRoundEntry::class, 'round_id');
    }

    // ------------- Scopes útiles -------------

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForShift($query, $shift)
    {
        return $query->where('shift', $shift);
    }
}
