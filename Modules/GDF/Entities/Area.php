<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\GDF\Entities\Budget;
use Modules\GDF\Entities\TravelRequest;

class Area extends Model
{
    use HasFactory;

    protected $table = 'areas';

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function budgets()
    {
        return $this->hasMany(Budget::class, 'area_id');
    }

    public function travelRequests()
    {
        return $this->hasMany(TravelRequest::class, 'area_id');
    }
    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class, 'gdf_area_user', 'area_id', 'user_id')
            ->withPivot(['active', 'assigned_by', 'scope'])
            ->withTimestamps();
    }

    public function activeUsers()
    {
        return $this->users()->wherePivot('active', true);
    }
    public function personAssignments()
    {
        return $this->hasMany(PersonAreaBudgetAssignment::class);
    }
    // Rubros permitidos (pivote con metadata)
    public function areaBudgetItems()
    {
        return $this->hasMany(\Modules\GDF\Entities\AreaBudgetItem::class, 'area_id');
    }

    // Rubros permitidos (solo catálogo BudgetItem)
    public function allowedBudgetItems()
    {
        return $this->belongsToMany(
            \Modules\GDF\Entities\BudgetItem::class,
            'area_budget_items',
            'area_id',
            'budget_item_id'
        )->withPivot(['active', 'created_by', 'updated_by'])
            ->wherePivot('active', true);
    }
}
