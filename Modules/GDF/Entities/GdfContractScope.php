<?php
namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;

class GdfContractScope extends Model {
  protected $table = 'gdf_contract_scopes';

  protected $fillable = ['contractor_id','area_id','budget_item_id','is_allowed','max_amount'];

  public function contractor() {
    return $this->belongsTo(\Modules\SICA\Entities\Contractor::class, 'contractor_id');
  }
}
