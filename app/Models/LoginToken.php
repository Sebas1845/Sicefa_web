<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class LoginToken extends Model
{
    protected $fillable = [
        'user_id',
        'token',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }

    public function markAsUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }
}
