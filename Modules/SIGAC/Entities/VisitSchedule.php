<?php

namespace Modules\SIGAC\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SICA\Entities\Environment;
use Modules\SICA\Entities\Person;
use Modules\SIGAC\Entities\VisitRequest;
use App\Models\User;

class VisitSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_request_id',
        'person_in_charge_id',
        'notification_email',
        'activity',
        'date',
        'start_time',
        'end_time',
        'environment_id',
        'observations',
        'authorization_path',
        'status',
        'check_in_at',
        'check_out_at',
        'security_user_id',
        'security_authorized_at',
        'security_authorized_by',
        'security_authorization_source',
    ];

    protected $casts = [
        'date'                  => 'date',
        'check_in_at'           => 'datetime',
        'check_out_at'          => 'datetime',
        'security_authorized_at' => 'datetime',
    ];


    public function visitRequest()
    {
        return $this->belongsTo(VisitRequest::class);
    }

    public function environment()
    {
        return $this->belongsTo(Environment::class);
    }

    public function personInCharge()
    {
        return $this->belongsTo(Person::class, 'person_in_charge_id');
    }

    public function securityUser()
    {
        return $this->belongsTo(User::class, 'security_user_id');
    }
}
