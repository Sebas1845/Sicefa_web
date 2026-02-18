<?php

namespace Modules\SIGAC\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\SICA\Entities\Person;
use OwenIt\Auditing\Contracts\Auditable;

class InstructorProgramPerson extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    // ✅ IMPORTANTE: tabla real (según tu BD)
    protected $table = 'instructor_program_people';

    // ✅ PARA firstOrCreate / create / update
    protected $fillable = [
        'instructor_program_id',
        'person_id',
    ];

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function instructor_program()
    {
        return $this->belongsTo(InstructorProgram::class, 'instructor_program_id');
    }
}
