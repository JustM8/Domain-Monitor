<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function subject()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function actionLabel(): string
    {
        $subjectName = $this->subjectLabel();

        return match ($this->action) {
            'site.created' => __('portal.activity_site_created', ['name' => $subjectName]),
            'site.updated' => __('portal.activity_site_updated', ['name' => $subjectName]),
            'site.deleted' => __('portal.activity_site_deleted', ['name' => $subjectName]),
            'site.restored' => __('portal.activity_site_restored', ['name' => $subjectName]),
            'site.checked' => __('portal.activity_site_checked', ['name' => $subjectName]),
            'site.enabled' => __('portal.activity_site_enabled', ['name' => $subjectName]),
            'site.disabled' => __('portal.activity_site_disabled', ['name' => $subjectName]),
            'company.created' => __('portal.activity_company_created', ['name' => $subjectName]),
            'company.updated' => __('portal.activity_company_updated', ['name' => $subjectName]),
            'company.deleted' => __('portal.activity_company_deleted', ['name' => $subjectName]),
            'company.restored' => __('portal.activity_company_restored', ['name' => $subjectName]),
            'hosting.created' => __('portal.activity_hosting_created', ['name' => $subjectName]),
            'hosting.updated' => __('portal.activity_hosting_updated', ['name' => $subjectName]),
            'hosting.deleted' => __('portal.activity_hosting_deleted', ['name' => $subjectName]),
            'hosting.restored' => __('portal.activity_hosting_restored', ['name' => $subjectName]),
            'hosting_account.created' => __('portal.activity_hosting_account_created', ['name' => $subjectName]),
            'hosting_account.updated' => __('portal.activity_hosting_account_updated', ['name' => $subjectName]),
            'hosting_account.deleted' => __('portal.activity_hosting_account_deleted', ['name' => $subjectName]),
            'hosting_account.restored' => __('portal.activity_hosting_account_restored', ['name' => $subjectName]),
            'ftp.created' => __('portal.activity_ftp_created', ['name' => $subjectName]),
            'ftp.updated' => __('portal.activity_ftp_updated', ['name' => $subjectName]),
            'ftp.deleted' => __('portal.activity_ftp_deleted', ['name' => $subjectName]),
            'ftp.restored' => __('portal.activity_ftp_restored', ['name' => $subjectName]),
            'status.created' => __('portal.activity_status_created', ['name' => $subjectName]),
            'status.updated' => __('portal.activity_status_updated', ['name' => $subjectName]),
            'status.deleted' => __('portal.activity_status_deleted', ['name' => $subjectName]),
            'status.restored' => __('portal.activity_status_restored', ['name' => $subjectName]),
            'telegram.requested' => __('portal.activity_telegram_requested', ['name' => $subjectName]),
            'telegram.approved' => __('portal.activity_telegram_approved', ['name' => $subjectName]),
            'telegram.revoked' => __('portal.activity_telegram_revoked', ['name' => $subjectName]),
            'user.toggle_active' => __('portal.activity_user_access_changed'),
            'user.verification_sent' => __('portal.activity_verification_sent'),
            default => Str::headline(str_replace(['.', '_'], ' ', $this->action)),
        };
    }

    public function subjectLabel(): string
    {
        $subject = $this->subject;

        if (! $subject) {
            return class_basename((string) $this->subject_type) . ' #' . $this->subject_id;
        }

        if (method_exists($subject, 'displayName')) {
            return (string) $subject->displayName();
        }

        foreach (['name', 'title', 'host', 'email', 'login', 'provider'] as $attribute) {
            $value = $subject->{$attribute} ?? null;
            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($subject::class) . ' #' . $subject->getKey();
    }

    public function subjectTypeLabel(): string
    {
        $type = class_basename((string) ($this->subject_type ?? ''));

        return match ($type) {
            'Site' => __('portal.activity_type_site'),
            'Company' => __('portal.activity_type_company'),
            'Hosting' => __('portal.activity_type_hosting'),
            'HostingAccount' => __('portal.activity_type_hosting_account'),
            'FtpAccount' => __('portal.activity_type_ftp'),
            'Status' => __('portal.activity_type_status'),
            'User' => __('portal.activity_type_user'),
            default => $type ?: __('portal.empty'),
        };
    }

    public function changedFields(int $limit = 3): array
    {
        $before = data_get($this->properties, 'before', []);
        $after = data_get($this->properties, 'after', []);

        if (! is_array($before) || ! is_array($after)) {
            return [];
        }

        $changes = [];

        foreach ($after as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'], true)) {
                continue;
            }

            if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                $changes[] = $this->fieldLabel($key);
            }
        }

        return array_values(array_unique(array_filter($changes)));
    }

    public function changeSummary(int $limit = 3): ?string
    {
        $changes = array_slice($this->changedFields($limit), 0, $limit);

        if (! $changes) {
            return null;
        }

        return __('portal.activity_changed_fields', [
            'fields' => implode(', ', $changes),
        ]);
    }

    public function icon(): string
    {
        return match (true) {
            str_contains($this->action, 'checked') => 'bi-shield-check',
            str_contains($this->action, 'disabled') => 'bi-pause-circle',
            str_contains($this->action, 'enabled') => 'bi-play-circle',
            str_contains($this->action, 'deleted') => 'bi-trash3',
            str_contains($this->action, 'restored') => 'bi-arrow-counterclockwise',
            str_contains($this->action, 'updated') => 'bi-pencil-square',
            str_contains($this->action, 'created') => 'bi-plus-circle',
            str_contains($this->action, 'status') => 'bi-tags',
            default => 'bi-activity',
        };
    }

    protected function fieldLabel(string $field): string
    {
        return match ($field) {
            'name' => __('portal.name'),
            'title' => __('portal.name'),
            'url' => __('portal.site_url'),
            'admin_url' => __('portal.admin_url'),
            'admin_login' => __('portal.admin_login'),
            'admin_password' => __('portal.admin_password'),
            'company_id' => __('portal.company'),
            'status_id' => __('portal.status'),
            'site_type' => __('portal.site_product_type'),
            'environment' => __('portal.environment'),
            'repo_url' => __('portal.repo_url'),
            'branch' => __('portal.branch'),
            'cms' => __('portal.cms'),
            'version' => __('portal.version'),
            'host' => __('portal.host'),
            'port' => 'Port',
            'login' => __('portal.login_label'),
            'path' => __('portal.path'),
            'requires_ip_access' => __('portal.requires_ip_access'),
            'provider' => __('portal.provider'),
            'panel_url' => __('portal.panel_url'),
            'ssh_host' => __('portal.ssh_host'),
            'ssh_port' => __('portal.ssh_port'),
            'ssh_login' => __('portal.ssh_login'),
            'ssh_password' => __('portal.ssh_password'),
            'telegram_chat_id' => __('portal.telegram_chat_id'),
            'telegram_username' => __('portal.telegram_username'),
            'telegram_link_token' => __('portal.telegram_link_token'),
            'telegram_link_requested_at' => __('portal.telegram_link_requested_at'),
            'telegram_link_expires_at' => __('portal.telegram_link_expires_at'),
            'telegram_verified_at' => __('portal.telegram_verified_at'),
            'full_name' => __('portal.full_name'),
            'email' => __('portal.email'),
            'role_id' => __('portal.role'),
            'is_active' => __('portal.active'),
            default => Str::headline(str_replace('_', ' ', $field)),
        };
    }
}
