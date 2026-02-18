<?php

namespace Modules\GTH\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Contractor;

class CertificateRequest extends Model
{
    protected $table = 'certificate_requests';

    protected $fillable = [
        'person_id',
        'contractor_id',
        'contract_year',
        'status',
        'requested_at',
        'processed_at',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
        'contract_year' => 'integer',
    ];

    // Relaciones
    public function person()
    {
        return $this->belongsTo(Person::class);
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }
}