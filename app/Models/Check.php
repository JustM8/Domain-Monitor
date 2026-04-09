<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'checked_at',
        'status_code',
        'response_time',
        'is_success',
        'error'
    ];

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }
}
