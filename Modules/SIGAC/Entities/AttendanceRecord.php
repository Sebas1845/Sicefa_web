<?php

namespace Modules\SIGAC\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\SICA\Entities\Person;
use Modules\SICA\Entities\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $table = 'attendance_records';

    protected $fillable = [
        'attendance_date',
        'attendance_time',
        'course_id',
        'instructor_id',
        'apprentice_id',
        'attendance_status',
        'evidence',
        'observations',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relaciones
    |--------------------------------------------------------------------------
    */

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor()
    {
        return $this->belongsTo(Person::class, 'instructor_id');
    }

    public function apprentice()
    {
        return $this->belongsTo(Person::class, 'apprentice_id');
    }

    protected static function newFactory()
    {
        return \Modules\SIGAC\Database\factories\AttendanceRecordFactory::new();
    }
}
