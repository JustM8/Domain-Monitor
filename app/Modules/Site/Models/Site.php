<?php

namespace App\Modules\Site\Models;

use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Shared\Traits\HasPortalAudit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Site extends Model
{
    use HasFactory;
    use HasPortalAudit;
    use SoftDeletes;

    public const SITE_TYPE_LABELS = [
        'site' => 'portal.site_type_site',
        '3d' => 'portal.site_type_3d',
        'devbase' => 'portal.site_type_devbase',
    ];

    public const ENVIRONMENT_LABELS = [
        'prod' => 'portal.environment_prod',
        'dev' => 'portal.environment_dev',
    ];

    protected $fillable = [
        'name',
        'url',
        'api_token',
        'site_type',
        'environment',
        'display_mode',
        'embed_origins',
        'admin_url',
        'admin_login',
        'admin_password',
        'status_id',
        'company_id',
        'repo_url',
        'branch',
        'cms',
        'version',
        'ssl',
        'last_backup_at',
        'last_synced_at',
        'last_sync_status',
        'last_sync_error',
        'remote_control_enabled',
        'monitoring_enabled',
        'is_active',
        'disabled_reason',
        'disabled_by',
        'disabled_at',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'embed_origins' => 'array',
        'control_version' => 'integer',
        'confirmed_control_version' => 'integer',
        'ssl' => 'boolean',
        'is_active' => 'boolean',
        'remote_control_enabled' => 'boolean',
        'last_backup_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'disabled_at' => 'datetime',
    ];

    protected $hidden = [
        'admin_password',
        'api_token',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $site) {
            if (! $site->isForceDeleting()) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($site) {
                    self::query()->lockForUpdate()->findOrFail($site->id);
                    app(\App\Modules\Monitoring\Services\MonitorManager::class)->siteDeleted($site);
                });
            }
        });
        static::restored(function (self $site) {
            app(\App\Modules\Monitoring\Services\MonitorManager::class)->siteRestored($site);
        });
        static::created(function (self $site) {
            app(\App\Modules\Monitoring\Services\MonitorManager::class)->primary($site, $site->monitoringIntent ?? true);
            $site->monitoringIntent = null;
        });
    }

    private ?bool $monitoringIntent = null;

    public function setMonitoringEnabledAttribute($value): void
    {
        $this->monitoringIntent = (bool) $value;
    }

    public function getMonitoringEnabledAttribute(): bool
    {
        return $this->monitoringIntent ?? (bool) $this->primaryMonitor()->value('enabled');
    }

    public function monitors()
    {
        return $this->hasMany(\App\Modules\Monitoring\Models\Monitor::class);
    }

    public function primaryMonitor()
    {
        return $this->hasOne(\App\Modules\Monitoring\Models\Monitor::class)->where('slot', 'primary_http');
    }

    public function status()
    {
        return $this->belongsTo(\App\Modules\Shared\Models\Status::class);
    }

    public function company()
    {
        return $this->belongsTo(\App\Modules\Shared\Models\Company::class);
    }

    public function ftpAccounts()
    {
        return $this->belongsToMany(FtpAccount::class, 'site_ftp_accounts')
            ->withTimestamps();
    }

    public function hostingAccounts()
    {
        return $this->belongsToMany(HostingAccount::class, 'site_hosting')
            ->withPivot(['is_main'])
            ->withTimestamps();
    }

    public function revisions()
    {
        return $this->hasMany(SiteRevision::class);
    }

    public function activityLogs()
    {
        return $this->morphMany(\App\Modules\Shared\Models\ActivityLog::class, 'subject');
    }

    public static function siteTypeOptions(): array
    {
        return array_map(fn ($label) => __($label), self::SITE_TYPE_LABELS);
    }

    public static function environmentOptions(): array
    {
        return array_map(fn ($label) => __($label), self::ENVIRONMENT_LABELS);
    }

    public function siteTypeLabel(): string
    {
        $key = self::SITE_TYPE_LABELS[$this->site_type] ?? null;

        return $key ? __($key) : (string) $this->site_type;
    }

    public function environmentLabel(): string
    {
        $key = self::ENVIRONMENT_LABELS[$this->environment] ?? null;

        return $key ? __($key) : (string) $this->environment;
    }

    public function decryptedAdminPassword(): ?string
    {
        if (! $this->admin_password) {
            return null;
        }

        try {
            $plain = Crypt::decryptString($this->admin_password);
            if (preg_match('/^s:\d+:".*";$/s', $plain)) {
                $legacy = @unserialize($plain, ['allowed_classes' => false]);
                if (is_string($legacy) && serialize($legacy) === $plain) {
                    return $legacy;
                }
            }

            return $plain;
        } catch (\Throwable) {
            try {
                return decrypt($this->admin_password);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function projectTypeOptions(): array
    {
        return self::environmentOptions();
    }

    public function projectTypeLabel(): string
    {
        return $this->environmentLabel();
    }
}
