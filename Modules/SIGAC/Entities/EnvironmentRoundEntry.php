<?php

namespace Modules\SIGAC\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnvironmentRoundEntry extends Model
{
    use HasFactory;

    protected $table = 'environment_round_entries';

    protected $fillable = [
        'round_id',
        'schedule_id',
        'present_in_environment',
        'attendance_status',
        'observations',
        'is_dirty',
        'ac_status',
        'other_issues',
        'marked_for_relocation',
        'suggested_environment_id',
        'updated_by',
    ];

    protected $casts = [
        'is_dirty'              => 'boolean',
        'marked_for_relocation' => 'boolean',
    ];

    // ------------- Relaciones -------------

    public function round()
    {
        return $this->belongsTo(EnvironmentRound::class, 'round_id');
    }

    /**
     * Relación con el horario del cronograma.
     * OJO: Ajusta el namespace si tu modelo se llama diferente.
     */
    public function schedule()
    {
        return $this->belongsTo(
            \Modules\SIGAC\Entities\InstructorProgramOutcome::class,
            'schedule_id'
        );
    }

    /**
     * Ambiente sugerido al que se desea mover.
     * OJO: Ajusta el namespace de Environment según tu proyecto.
     */
    public function suggestedEnvironment()
    {
        return $this->belongsTo(
            \Modules\SIGAC\Entities\EnvironmentInstructorProgram::class,
            'suggested_environment_id'
        );
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
