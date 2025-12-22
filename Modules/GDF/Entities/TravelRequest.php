<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class TravelRequest extends Model
{
    use HasFactory;

    protected $table = 'travel_requests';

    protected $fillable = [
        'area_id','budget_item_id','budget_id',
        'person_id','person_type','employee_id','contractor_id',
        'request_type','origin','destination','start_date','end_date','notes',
        'status','created_by','submitted_at','approved_at',
        'total_transport','total_per_diem','total_other','total_amount',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_transport' => 'decimal:2',
        'total_per_diem' => 'decimal:2',
        'total_other' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function budgetItem()
    {
        return $this->belongsTo(BudgetItem::class, 'budget_item_id');
    }

    public function budget()
    {
        return $this->belongsTo(Budget::class, 'budget_id');
    }

    public function segments()
    {
        return $this->hasMany(TravelSegment::class, 'travel_request_id');
    }

    public function costs()
    {
        return $this->hasMany(TravelCost::class, 'travel_request_id');
    }

    public function reviews()
    {
        return $this->hasMany(TravelReview::class, 'travel_request_id');
    }

    public function logs()
    {
        return $this->hasMany(TravelLog::class, 'travel_request_id');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class, 'travel_request_id');
    }
}
