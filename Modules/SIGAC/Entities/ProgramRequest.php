<?php

namespace Modules\SIGAC\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Program;
use Modules\SICA\Entities\Municipality;
use OwenIt\Auditing\Contracts\Auditable;

class ProgramRequest extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [ // Atributos modificables (asignación masiva)
        'person_id',
        'program_id',
        'special_program_id',
        'municipality_id',
        'hours',
        'start_date',
        'end_date',
        'quotas',
        'address',
        'observation',
        'empresa',
        'applicant',
        'email',
        'telephone',
        'date_characterization',
        'code_empresa',
        'code_course',
        'date_inscription',
        'state'
    ];

    protected $dates = ['deleted_at']; // Atributos que deben ser tratados como objetos Carbon

    protected $hidden = ['created_at', 'updated_at']; // Atributos ocultos para no representarlos en las salidas con formato JSON

    protected static function newFactory()
    {
        return \Modules\SIGAC\Database\factories\ProgramRequestFactory::new();
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function program_request_dates()
    {
        return $this->hasMany(ProgramRequestDate::class);
    }

    public function program_request_documents()
    {
        return $this->hasMany(ProgramRequestDocument::class);
    }

    public function special_program()
    {
        return $this->belongsTo(SpecialProgram::class);
    }
    public function dates()
    {
        // OJO: ajusta el namespace/clase si tu modelo se llama distinto
        return $this->hasMany(\Modules\SIGAC\Entities\ProgramRequestDate::class, 'program_request_id');
    }
    public function documents()
    {
        // OJO: ajusta el namespace/clase si tu modelo se llama distinto
        return $this->hasMany(\Modules\SIGAC\Entities\ProgramRequestDocument::class, 'program_request_id');
    }
    // Si tienes tabla areas en SICA (ajusta namespace real)
    public function area()
    {
        return $this->belongsTo(\Modules\GDF\Entities\Area::class, 'area_id');
    }

    // “Rubro”: si tu solicitud guarda budget_item_id (ajusta nombres)
    public function budgetItem()
    {
        return $this->belongsTo(\Modules\GDF\Entities\BudgetItem::class, 'budget_item_id');
        // o \Modules\SICA\Entities\BudgetItem::class según tu proyecto
    }
    public function village()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Village::class, 'village_id');
    }
}
