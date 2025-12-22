<?php

namespace Modules\SICA\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Facades\Auth; // <-- agrega esto

class ProductiveUnitWarehouse extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable, SoftDeletes;

    protected $fillable = ['productive_unit_id','warehouse_id'];
    protected $dates = ['deleted_at'];
    protected $hidden = ['created_at','updated_at'];

    // ======= RELACIONES =======
    public function cash_counts(){ return $this->hasMany(CashCount::class); }
    public function inventories(){ return $this->hasMany(Inventory::class); }
    public function productive_unit(){ return $this->belongsTo(ProductiveUnit::class); }
    public function warehouse(){ return $this->belongsTo(Warehouse::class); }
    public function warehouse_movements(){ return $this->hasMany(WarehouseMovement::class); }

    // ======= NUEVO: resolver la PUW activa para la app =======
    public static function getAppPuw(): self
    {
        $query = static::with(['warehouse','productive_unit']);

        // 1) Si está seleccionada en sesión
        if ($id = session('puw_id')) {
            if ($puw = (clone $query)->find($id)) {
                return $puw;
            }
        }

        // 2) Si el usuario tiene asignada una PUW
        if ($user = Auth::user()) {
            $field = 'productive_unit_warehouse_id'; // ajusta si tu campo tiene otro nombre
            if (!empty($user->{$field})) {
                if ($puw = (clone $query)->find($user->{$field})) {
                    return $puw;
                }
            }
        }

        // 3) Fallback: primera PUW disponible
        return $query->firstOrFail();
    }
}
