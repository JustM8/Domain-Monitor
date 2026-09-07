<?php

namespace App\Modules\Ftp\Models;

use App\Modules\Shared\Traits\HasPortalAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class FtpAccount extends Model
{
    use HasFactory;
    use SoftDeletes;
    use HasPortalAudit;

    protected $fillable = [
        'site_id',
        'company_id',
        'host',
        'port',
        'login',
        'password',
        'path',
        'requires_ip_access',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'requires_ip_access' => 'boolean',
    ];

    public function site()
    {
        return $this->belongsTo(\App\Modules\Site\Models\Site::class);
    }

    public function sites()
    {
        return $this->belongsToMany(\App\Modules\Site\Models\Site::class, 'site_ftp_accounts')
            ->withTimestamps();
    }

    public function company()
    {
        return $this->belongsTo(\App\Modules\Shared\Models\Company::class);
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
}
