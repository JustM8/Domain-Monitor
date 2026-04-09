<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'domain',
        'is_active',
        'check_interval',
        'timeout',
        'method'
    ];

    protected $casts = [
        'last_checked_at' => 'datetime',
    ];
    public function checks()
    {
        return $this->hasMany(Check::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lastCheck()
    {
        return $this->hasOne(Check::class)->latestOfMany('checked_at');
    }

    public function getHostAttribute()
    {
        return parse_url($this->domain, PHP_URL_HOST) ?? $this->domain;
    }
}
