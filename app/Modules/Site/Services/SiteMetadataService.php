<?php

namespace App\Modules\Site\Services;

use App\Modules\Monitoring\Services\MonitoringHistory;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SiteMetadataService
{
    public function update(Site $site, array $data): Site
    {
        return DB::transaction(function () use ($site, $data) {
            $record = Site::query()->lockForUpdate()->findOrFail($site->id);
            $wasEnabled = $record->monitoring_enabled;
            $wasManaged = $record->remote_control_enabled;
            $record->fill($data);
            if ($wasManaged && ($record->isDirty('url') || ! $record->remote_control_enabled)) {
                // Never strand a disabled child or one whose last command may still be in flight.
                if (! $record->is_active || ($record->control_version > 0
                    && ($record->confirmed_state !== 'active' || $record->confirmed_control_version !== $record->control_version))) {
                    throw ValidationException::withMessages([
                        'remote_control_enabled' => 'Спочатку увімкніть сайт і синхронізуйте поточну команду до підтвердження. Потім змініть режим або адресу.',
                    ]);
                }
            }
            if ($record->isDirty(['url', 'monitoring_enabled', 'monitoring_interval', 'monitoring_timeout',
                'monitoring_failure_threshold', 'monitoring_status_codes', 'monitoring_url', 'monitoring_content'])) {
                $record->monitoring_revision++;
            }
            $history = app(MonitoringHistory::class);
            $changedTarget = $record->isDirty(['url', 'monitoring_url', 'monitoring_status_codes', 'monitoring_content']);
            if ($wasEnabled && ! $record->monitoring_enabled) {
                $history->stop($record);
            } elseif ($changedTarget || $record->isDirty(['monitoring_interval', 'monitoring_failure_threshold'])) {
                $history->reset($record, $changedTarget ? 'target_changed' : 'settings_changed');
            }
            if ($record->isDirty('url')) {
                $record->forceFill([
                    'remote_control_enabled' => false,
                    'api_token' => Crypt::encryptString(Str::random(60)),
                    'confirmed_state' => null, 'confirmed_control_version' => null,
                ]);
            }
            if ($record->isDirty(['url', 'display_mode', 'embed_origins', 'remote_control_enabled'])) {
                $record->control_version++;
            }
            $record->save();
            if (! $wasEnabled && $record->monitoring_enabled) {
                $history->reset($record, 'resumed');
                $history->start($record);
            }

            return $record;
        });
    }
}
