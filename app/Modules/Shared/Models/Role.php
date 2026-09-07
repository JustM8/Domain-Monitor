<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'label',
        'sort_order',
    ];

    public function users()
    {
        return $this->hasMany(\App\Models\User::class, 'role_id');
    }
}
