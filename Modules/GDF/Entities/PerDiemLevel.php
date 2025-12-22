<?php

namespace Modules\GDF\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PerDiemLevel extends Model
{
    use HasFactory;

    protected $table = 'per_diem_levels';

    protected $fillable = [
        'name', 'description', 'min_salary', 'max_salary', 'daily_amount', 'active',
    ];

    protected $casts = [
        'min_salary' => 'decimal:2',
        'max_salary' => 'decimal:2',
        'daily_amount' => 'decimal:2',
        'active' => 'boolean',
    ];
}
