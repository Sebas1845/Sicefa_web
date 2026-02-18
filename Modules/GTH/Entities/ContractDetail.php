<?php

namespace Modules\GTH\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class ContractDetail extends Model
{
    use SoftDeletes;

    protected $table = 'contract_details';

    protected $fillable = [
        'contractor_id',
        'contract_number_formatted',
        'contract_date',
        'expedition_date',
        'execution_start_date',
        'execution_end_date',
        'status',
        'payment_type',
        'monthly_payment',
        'unit_hour_value',
        'warehouse_id',
        'contract_version',
        'modification_notes',
        'last_modified_date',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'expedition_date' => 'date',
        'execution_start_date' => 'date',
        'execution_end_date' => 'date',
        'last_modified_date' => 'date',
        'monthly_payment' => 'decimal:2',
        'unit_hour_value' => 'decimal:2',
    ];

    // =========================
    // RELACIONES
    // =========================

    public function contractor()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Contractor::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Warehouse::class);
    }

}
