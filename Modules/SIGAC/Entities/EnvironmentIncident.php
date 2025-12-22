<?php

namespace Modules\SIGAC\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnvironmentIncident extends Model
{
    use HasFactory;

    protected $table = 'environment_incidents';

    protected $fillable = [
        'environment_id',
        'schedule_id',
        'instructor_id',
        'reported_by',
        'reported_at',
        'source',
        'type',
        'description',
        'status',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
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
        // Ajusta el namespace según tu modelo real del cronograma
        return $this->belongsTo(
            \Modules\SIGAC\Entities\InstructorProgramOutcome::class,
            'schedule_id'
        );
    }

    public function instructor()
    {
        // OJO: casi seguro el Instructor está en el módulo SICA
        // Cambia el namespace si es necesario
        return $this->belongsTo(
            \Modules\SICA\Entities\InstructorProgram::class,
            'instructor_id'
        );
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    // ------------- Scopes -------------

    public function scopeOpen($query)
    {
        return $query->where('status', 'ABIERTA');
    }

    public function scopeForEnvironment($query, $environmentId)
    {
        return $query->where('environment_id', $environmentId);
    }
}
