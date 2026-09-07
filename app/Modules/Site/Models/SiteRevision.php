<?php

namespace App\Modules\Site\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'changed_by',
        'change_type',
        'before_data',
        'after_data',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
    ];

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'changed_by');
    }
}
