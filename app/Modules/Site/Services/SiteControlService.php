<?php

namespace App\Modules\Site\Services;

use App\Models\User;
use App\Modules\Shared\Models\ActivityLog;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\DB;

class SiteControlService
{
    public function change(Site $site, User $actor, bool $active, string $reason): Site
    {
        abort_unless($actor->fresh('role')->canPortal('sites.control'), 403);

        return DB::transaction(function () use ($site, $actor, $active, $reason) {
            $locked = Site::query()->lockForUpdate()->findOrFail($site->id);
            abort_unless($locked->remote_control_enabled, 422, 'Спочатку увімкніть дистанційне керування та підключіть дочірній сайт.');
            $before = $locked->only(['is_active', 'disabled_reason', 'control_version']);
            $locked->forceFill([
                'is_active' => $active, 'disabled_reason' => $active ? null : $reason,
                'disabled_at' => $active ? null : now(), 'disabled_by' => $active ? null : $actor->id,
                'control_version' => $locked->control_version + 1,
            ])->save();
            $log = new ActivityLog(['user_id' => $actor->id, 'action' => $active ? 'site.enabled' : 'site.disabled', 'properties' => ['reason' => $reason, 'version' => $locked->control_version]]);
            $log->subject()->associate($locked);
            $log->save();
            \App\Modules\Site\Models\SiteRevision::create([
                'site_id' => $locked->id, 'changed_by' => $actor->id, 'change_type' => $active ? 'enable' : 'disable',
                'before_data' => $before, 'after_data' => $locked->only(['is_active', 'disabled_reason', 'control_version']) + ['reason' => $reason],
            ]);

            return $locked;
        });
    }
}
