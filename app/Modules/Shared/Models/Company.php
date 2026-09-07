<?php

namespace App\Modules\Shared\Models;

use App\Modules\Shared\Traits\HasPortalAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasPortalAudit;

    protected $fillable = [
        'name',
        'manager_name',
        'contact',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function sites()
    {
        return $this->hasMany(\App\Modules\Site\Models\Site::class);
    }

    public function ftpAccounts()
    {
        return $this->hasMany(\App\Modules\Ftp\Models\FtpAccount::class);
    }

    public function hostingAccounts()
    {
        return $this->hasMany(\App\Modules\Hosting\Models\HostingAccount::class);
    }
}
