<?php
namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContractDocument extends \Illuminate\Database\Eloquent\Model {
  protected $fillable = [
    'contractor_id','amendment_id','name','type','path','uploaded_by'
  ];

  public function contractor() {
    return $this->belongsTo(\Modules\SICA\Entities\Contractor::class, 'contractor_id');
  }

  public function amendment() {
    return $this->belongsTo(\Modules\GDF\Entities\ContractAmendment::class, 'amendment_id');
  }
}
