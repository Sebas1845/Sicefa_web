<?php

namespace Modules\SICA\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\GDF\Entities\ContractAmendment;
use OwenIt\Auditing\Contracts\Auditable;
use Modules\GDF\Entities\ContractDocument;
use Modules\GDF\Entities\GdfContractScope;




class ContractorType extends Model implements Auditable
{

    use \OwenIt\Auditing\Auditable, // Seguimientos de cambios realizados en BD
        SoftDeletes; // Borrado suave

    protected $fillable = ['name']; // Atributos modificables (asignación masiva)

    protected $dates = ['deleted_at']; // Atributos que deben ser tratados como objetos Carbon

    protected $hidden = [ // Atributos ocultos para no representarlos en las salidas con formato JSON
        'created_at',
        'updated_at'
    ];

    // MUTADORES Y ACCESORES
    public function setNameAttribute($value)
    { // Convierte el primer carácter en mayúscula del dato name (MUTADOR)
        $this->attributes['name'] = ucfirst($value);
    }

    // RELACIONES
    public function contractors()
    { // Accede a todos los registros de contratistas que le pertenecen a este tipo de contratación
        return $this->hasMany(Contractor::class);
    }

    // Modules/SICA/Entities/Contractor.php (o donde esté)
    public function amendments()
    {
        return $this->hasMany(ContractAmendment::class, 'contractor_id');
    }

    public function documents()
    {
        return $this->hasMany(ContractDocument::class, 'contractor_id');
    }

    public function gdfScopes()
    {
        return $this->hasMany(GdfContractScope::class, 'contractor_id');
    }

    /** Fecha fin efectiva: base vs últimas prórrogas */
    public function effectiveEndDate(): ?string
    {
        $base = $this->contract_end_date; // date
        $maxExtension = $this->amendments()
            ->where('type', 'EXTENSION')
            ->max('new_end_date');

        if (!$base) return $maxExtension;
        if (!$maxExtension) return $base;
        return $maxExtension > $base ? $maxExtension : $base;
    }

    public function isActive(): bool
    {
        if (($this->state ?? 'Activo') !== 'Activo') return false;
        $end = $this->effectiveEndDate();
        if (!$end) return true; // si no hay fecha fin, lo tratas como activo
        return now()->toDateString() <= $end;
    }
}
