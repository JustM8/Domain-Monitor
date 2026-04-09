<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_email',
        'check_interval'
    ];

    public static function get()
    {
        return self::first() ?? self::create([]);
    }
}
