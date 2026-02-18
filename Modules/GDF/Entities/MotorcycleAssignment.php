<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class MotorcycleAssignment extends Model
{
    protected $table = 'motorcycle_assignments';

    protected $fillable = [
        'motorcycle_id',
        'person_id',
        'area_id',
        'budget_item_id',

        'workflow',
        'requested_at',
        'request_reason',

        'travel_requestable_type',
        'travel_requestable_id',

        'delivered_at',
        'returned_at',
        'odometer_out',
        'odometer_in',

        'observations_out',
        'observations_in',

        'status',
        'requested_by',
        'approved_by',
        'managed_by',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'delivered_at' => 'datetime',
        'returned_at'  => 'datetime',
    ];

    /* =========================
     | Relationships
     * ========================= */

    public function motorcycle()
    {
        return $this->belongsTo(Motorcycle::class, 'motorcycle_id');
    }

    public function person()
    {
        return $this->belongsTo(\Modules\SICA\Entities\Person::class, 'person_id');
    }

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }

    /**
     * Polymorphic relation to GDF / SITRAV travel request
     */
    public function travelRequestable()
    {
        return $this->morphTo();
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function managedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'managed_by');
    }

    /* =========================
     | Helper scopes (recommended)
     * ========================= */

    public function scopeDirect($q)
    {
        return $q->where('workflow', 'direct');
    }

    public function scopeRequest($q)
    {
        return $q->where('workflow', 'request');
    }

    public function scopeActive($q)
    {
        return $q->whereIn('status', ['approved', 'delivered']);
    }
    public function travel_requestable()
{
    return $this->morphTo();
}

}
