<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TravelRequest extends Model
{
    use HasFactory;

    protected $table = 'travel_requests';

    protected $fillable = [
        'module',
        'source',
        'source_request_id',
        'radicado_code',
        'radicated_at',
        'radicated_by',

        'area_id',
        'budget_item_id',
        'budget_id',

        'person_id',
        'person_type',
        'employee_id',
        'contractor_id',

        'request_type',
        'origin',
        'destination',
        'start_date',
        'end_date',
        'notes',

        'status',
        'created_by',
        'submitted_at',
        'approved_at',

        'total_transport',
        'total_per_diem',
        'total_other',
        'total_amount',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'radicated_at' => 'datetime',
        'start_date'   => 'date',
        'end_date'     => 'date',
    ];

    /* =========================
     | Relaciones
     * ========================= */

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class);
    }

    public function person()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Person::class);
    }

    public function employee()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Employee::class);
    }

    public function contractor()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Contractor::class);
    }


    public function costs()
    {
        return $this->hasMany(TravelCost::class);
    }

    public function reviews()
    {
        return $this->hasMany(TravelReview::class);
    }

    public function logs()
    {
        return $this->hasMany(TravelLog::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function radicator()
    {
        return $this->belongsTo(\App\Models\User::class, 'radicated_by');
    }

    /* =========================
     | Helpers de negocio
     * ========================= */

    public function isSitrav(): bool
    {
        return $this->module === 'sitrav';
    }

    public function isGdf(): bool
    {
        return $this->module === 'gdf';
    }

    public function requiresEvidenceFor(string $stage): bool
    {
        return $this->attachments()
            ->where('required_for', $stage)
            ->where('approved', false)
            ->exists();
    }
    public function segments()
    {
        return $this->hasMany(\Modules\GDF\Entities\TravelSegment::class, 'travel_request_id');
    }
}
