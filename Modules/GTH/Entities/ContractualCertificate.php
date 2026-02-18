<?php

namespace Modules\GTH\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContractualCertificate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'contractor_id',
        'certificate_number',
        'center_name',
        'center_address',
        'version_code',
        'title_line_1',
        'title_line_2',
        'intro_text',
        'contract_number_display',
        'contract_date',
        'contract_object_custom',
        'execution_start_date',
        'execution_end_date',
        'total_value_custom',
        'total_value_words',
        'monthly_payment_custom',
        'contract_state_custom',
        'obligations',
        'expedition_date',
        'expedition_text',
        'projected_by',
        'projected_by_role',
        'reviewed_by',
        'reviewed_by_role',
        'director_name',
        'director_role',
        'logo_path',
        'logo_color',
        'line_spacing',
        'font_size',
        'font_family',
        'status',
        'gender',
        
        'place_of_issue',
        'payment_type',
        'monthly_payment',
        'unit_hour_value',
        'observations',
        'notes',
        'created_by',
        'issued_by',
        'issued_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'obligations' => 'array',
        'expedition_date' => 'date',
        'contract_date' => 'date',
        'execution_start_date' => 'date',
        'execution_end_date' => 'date',
        'issued_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'total_value_custom' => 'decimal:2',
        'monthly_payment_custom' => 'decimal:2',
    ];

    // Relaciones
    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    // Métodos de utilidad
    public function markAsIssued($userId = null)
    {
        $this->update([
            'status' => 'issued',
            'issued_by' => $userId,
            'issued_at' => now(),
        ]);
    }

    public function generateCertificateNumber()
    {
        if (!$this->certificate_number) {
            $year = now()->year;
            $lastCert = self::whereYear('created_at', $year)
                ->whereNotNull('certificate_number')
                ->orderBy('id', 'desc')
                ->first();
            
            $number = $lastCert ? (int)substr($lastCert->certificate_number, -4) + 1 : 1;
            $this->certificate_number = "CERT-{$year}-" . str_pad($number, 4, '0', STR_PAD_LEFT);
            $this->save();
        }
        
        return $this->certificate_number;
    }
}