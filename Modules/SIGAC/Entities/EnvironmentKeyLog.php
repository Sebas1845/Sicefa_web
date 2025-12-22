<?php

namespace Modules\SIGAC\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnvironmentKeyLog extends Model
{
    use HasFactory;

    protected $table = 'environment_key_logs';

    protected $fillable = [
        'environment_id',
        'schedule_id',
        'instructor_id',
        'given_by',
        'taken_at',
        'returned_at',
    ];

    protected $casts = [
        'taken_at'   => 'datetime',
        'returned_at'=> 'datetime',
    ];

    // ------------- Relaciones -------------

    public function environment()
    {
        return $this->belongsTo(
            \Modules\SIGAC\Entities\EnvironmentInstructorProgram::class,
            'environment_id'
        );
    }

    public function schedule()
    {
        // Ajustar namespace según tu modelo real de horarios
        return $this->belongsTo(
            \Modules\SIGAC\Entities\InstructorProgramOutcome::class,
            'schedule_id'
        );
    }

    public function instructor()
    {
        return $this->belongsTo(
            \Modules\SICA\Entities\InstructorProgram::class,
            'instructor_id'
        );
    }

    public function giver()
    {
        return $this->belongsTo(User::class, 'given_by');
    }

    // Helpers

    public function getIsReturnedAttribute()
    {
        return ! is_null($this->returned_at);
    }
}
