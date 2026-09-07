<?php

namespace App\Modules\Hosting\Models;

use App\Modules\Shared\Traits\HasPortalAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class HostingAccount extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasPortalAudit;

    protected $fillable = [
        'hosting_id',
        'company_id',
        'title',
        'login',
        'password',
        'ssh_host',
        'ssh_port',
        'ssh_login',
        'ssh_password',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
        'ssh_password',
    ];

    public function hosting()
    {
        return $this->belongsTo(Hosting::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Modules\Shared\Models\Company::class);
    }

    public function sites()
    {
        return $this->belongsToMany(\App\Modules\Site\Models\Site::class, 'site_hosting')
            ->withPivot(['is_main'])
            ->withTimestamps();
    }

    public function decryptedPassword(): ?string
    {
        if (! $this->password) {
            return null;
        }

        try {
            return Crypt::decryptString($this->password);
        } catch (\Throwable) {
            return null;
        }
    }

    public function decryptedSshPassword(): ?string
    {
        if (! $this->ssh_password) {
            return null;
        }

        try {
            return Crypt::decryptString($this->ssh_password);
        } catch (\Throwable) {
            return null;
        }
    }
}
