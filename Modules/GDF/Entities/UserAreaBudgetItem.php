<?php

namespace Modules\GDF\Entities; 
// Ajusta el namespace si este modelo vive en otro módulo (ej. App\Models)

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserAreaBudgetItem extends Model
{
    use HasFactory;

    protected $table = 'user_area_budget_items';

    protected $fillable = [
        'user_id',
        'area_id',
        'budget_item_id',
        'scope_role',
        'applies_all_areas',
        'applies_all_budget_items',
        'is_active',
    ];

    protected $casts = [
        'applies_all_areas'        => 'boolean',
        'applies_all_budget_items' => 'boolean',
        'is_active'                => 'boolean',
    ];

    /* =====================================================
     | Relaciones
     ===================================================== */

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function area()
    {
        return $this->belongsTo(\Modules\GDF\Entities\Area::class);
        // Ajusta si Area está en otro módulo/namespace
    }

    public function budgetItem()
    {
        return $this->belongsTo(\Modules\GDF\Entities\BudgetItem::class);
        // Ajusta namespace si corresponde
    }

    /* =====================================================
     | Scopes reutilizables
     ===================================================== */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForScopeRole($query, string $scopeRole)
    {
        return $query->where('scope_role', $scopeRole);
    }

    public function scopeForArea($query, int $areaId)
    {
        return $query->where(function ($q) use ($areaId) {
            $q->where('applies_all_areas', true)
              ->orWhere('area_id', $areaId);
        });
    }

    public function scopeForBudgetItem($query, int $budgetItemId)
    {
        return $query->where(function ($q) use ($budgetItemId) {
            $q->where('applies_all_budget_items', true)
              ->orWhere('budget_item_id', $budgetItemId);
        });
    }

    /* =====================================================
     | Helpers de negocio (muy útiles en Policies)
     ===================================================== */

    public function appliesToArea(int $areaId): bool
    {
        return $this->applies_all_areas || $this->area_id === $areaId;
    }

    public function appliesToBudgetItem(int $budgetItemId): bool
    {
        return $this->applies_all_budget_items || $this->budget_item_id === $budgetItemId;
    }
}
