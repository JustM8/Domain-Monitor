<?php

namespace App\Modules\Site\Services;

use App\Modules\Shared\Http\SafeHttp;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;

class SiteSyncService
{
    public function __construct(private SafeHttp $http) {}

    public function sync(Site $site, string $state, array $payload = []): array
    {
        $site = $site->fresh();
        $state = $site->is_active ? 'active' : 'disabled';
        $version = $site->control_version;
        if (! $site->remote_control_enabled) {
            return ['ok' => false, 'status' => 'skipped', 'message' => 'Керування вимкнено.'];
        }
        $attempt = \Illuminate\Support\Facades\DB::table('site_control_attempts')->insertGetId([
            'site_id' => $site->id, 'user_id' => auth()->id(), 'version' => $version,
            'desired_state' => $state, 'started_at' => now(),
        ]);
        $ok = false;
        $message = 'Дочірній сайт не підтвердив команду.';
        try {
            $token = Crypt::decryptString($site->api_token);
            $response = $this->http->postControl(rtrim($site->url, '/').'/api/portal/sync', $token, [
                'site_id' => $site->id, 'state' => $state, 'version' => $version,
                'reason' => $site->disabled_reason, 'issued_at' => now()->timestamp,
                'display_mode' => $site->display_mode, 'embed_origins' => $site->embed_origins ?: [],
            ]);
            $ok = $response->successful() && $response->json('status') === $state && $response->json('version') === $version;
            $message = $ok ? 'Команду підтверджено дочірнім сайтом.' : 'HTTP '.$response->status().': стан або версія не підтверджені.';
        } catch (\Throwable $e) {
            $message = $e instanceof \InvalidArgumentException ? $e->getMessage() : 'Помилка з’єднання, TLS або ключа керування.';
        }
        $update = ['last_synced_at' => now(), 'last_sync_status' => $ok ? $state : 'failed', 'last_sync_error' => $ok ? null : $message];
        if ($ok) {
            $update += ['confirmed_state' => $state, 'confirmed_control_version' => $version];
        }
        // A response to an old command must never overwrite a newer decision or changed target.
        $updated = Site::query()->whereKey($site->id)->where('control_version', $version)->where('url', $site->url)->where('remote_control_enabled', true)->update($update);

        \Illuminate\Support\Facades\DB::table('site_control_attempts')->where('id', $attempt)->update([
            'status' => $updated === 0 ? 'stale' : ($ok ? 'confirmed' : 'failed'),
            'message' => $updated === 0 ? 'Відповідь на попередню версію команди.' : $message, 'finished_at' => now(),
        ]);
        if ($updated === 0) {
            return ['ok' => false, 'status' => 'stale', 'message' => 'Відповідь застаріла: параметри керування змінилися. Повторіть синхронізацію.'];
        }

        return ['ok' => $ok && $updated > 0, 'status' => $ok ? $state : 'failed', 'message' => $message];
    }
}
