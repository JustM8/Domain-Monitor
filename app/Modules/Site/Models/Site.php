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
        'monitoring_enabled', 'monitoring_interval', 'monitoring_timeout',
        'monitoring_failure_threshold', 'monitoring_status_codes', 'monitoring_url',
        'monitoring_content', 'monitoring_recipient_ids',
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
        'monitoring_enabled' => 'boolean',
        'monitoring_revision' => 'integer',
        'monitoring_interval' => 'integer',
        'monitoring_timeout' => 'integer',
        'monitoring_failure_threshold' => 'integer',
        'monitoring_status_codes' => 'array',
        'monitoring_recipient_ids' => 'array',
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
        static::creating(function (self $site) {
            if ($site->monitoring_enabled === null) {
                $site->monitoring_enabled = $site->environment === 'prod';
            }
            $site->monitoring_interval ??= max(1, min(60, (int) config('monitoring.interval_minutes', 5)));
            $site->monitoring_timeout ??= 6;
            $site->monitoring_failure_threshold ??= 2;
        });
        static::deleting(function (self $site) {
            if (! $site->isForceDeleting()) {
                \Illuminate\Support\Facades\DB::transaction(function () use ($site) {
                    self::query()->lockForUpdate()->findOrFail($site->id);
                    app(\App\Modules\Monitoring\Services\MonitoringHistory::class)->stop($site, 'deleted');
                });
            }
        });
        static::restoring(function (self $site) {
            $site->monitoring_revision++;
        });
        static::restored(function (self $site) {
            if ($site->monitoring_enabled) {
                app(\App\Modules\Monitoring\Services\MonitoringHistory::class)->start($site);
            }
        });
        static::created(function (self $site) {
            if ($site->monitoring_enabled) {
                app(\App\Modules\Monitoring\Services\MonitoringHistory::class)->start($site);
            }
        });
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
