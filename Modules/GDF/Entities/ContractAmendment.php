<?php
namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class ContractAmendment extends \Illuminate\Database\Eloquent\Model {
  protected $fillable = [
    'contractor_id','type','amount_addition','new_end_date','notes','created_by'
  ];

  public function contractor() {
    return $this->belongsTo(\Modules\SICA\Entities\Contractor::class, 'contractor_id');
  }

  public function documents() {
    return $this->hasMany(ContractDocument::class, 'amendment_id');
  }
}
