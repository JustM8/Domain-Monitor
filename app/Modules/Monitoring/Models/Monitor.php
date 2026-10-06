<?php

namespace App\Modules\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;

class Monitor extends Model
{
    protected $table = 'monitoring_monitors';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['enabled' => 'boolean', 'config' => 'array'];

    public function site()
    {
        return $this->belongsTo(\App\Modules\Site\Models\Site::class);
    }
}
