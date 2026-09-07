<?php

namespace App\Modules\Hosting\Models;

use App\Modules\Shared\Traits\HasPortalAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hosting extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasPortalAudit;

    protected $fillable = [
        'name',
        'provider',
        'panel_url',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function accounts()
    {
        return $this->hasMany(HostingAccount::class);
    }

    public function sites()
    {
        return $this->belongsToMany(\App\Modules\Site\Models\Site::class, 'site_hosting')
            ->withPivot(['is_main'])
            ->withTimestamps();
    }
}
