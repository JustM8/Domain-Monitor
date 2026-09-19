<?php

namespace App\Modules\Monitoring\Services;

use App\Models\User;
use App\Modules\Site\Models\Site;
use App\Modules\TelegramAccess\Services\TelegramBotService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonitoringNotifications
{
    public function recipientIds(?int $siteId = null): array
    {
        $specific = $siteId ? DB::table('sites')->where('id', $siteId)->value('monitoring_recipient_ids') : null;
        $ids = json_decode($specific ?? '[]', true) ?: [];
        if (! $ids) {
            $ids = json_decode(DB::table('monitoring_settings')->where('id', 1)->value('recipient_ids') ?? '[]', true) ?: [];
        }

        return array_map('intval', $ids);
    }

    public function enqueue(int $incident, string $event): void
    {
        $siteId = DB::table('monitoring_incidents')->where('id', $incident)->value('site_id');
        foreach ($this->recipientIds($siteId) as $id) {
            DB::table('monitoring_notifications')->insertOrIgnore([
                'incident_id' => $incident, 'user_id' => $id, 'event' => $event,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function deliver(int $maxBatches = 3, ?float $deadline = null): void
    {
        $lock = Cache::lock('monitoring:delivery', max(120, $maxBatches * 20 + 30));
        if (! $lock->get()) {
            return;
        }
        try {
            $rows = DB::table('monitoring_notifications')->whereNull('sent_at')->whereNull('cancelled_at')
                ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                ->orderBy('id')->limit(100)->get()->groupBy('user_id');
            $batches = 0;
            foreach ($rows as $userId => $notifications) {
                $user = User::with('role')->find($userId);
                $eligible = $user && $user->canPortal('monitoring.read') && $user->canUseAccessBot() && $user->telegramIsLinked();
                $notifications = $notifications->filter(function ($item) use ($eligible, $userId) {
                    $incident = DB::table('monitoring_incidents')->find($item->incident_id);
                    $site = $incident ? Site::find($incident->site_id) : null;
                    if (! $eligible || ! $site || ! $site->monitoring_enabled
                        || ! in_array((int) $userId, $this->recipientIds($site->id), true)) {
                        DB::table('monitoring_notifications')->where('id', $item->id)->update(['cancelled_at' => now()]);

                        return false;
                    }

                    return true;
                });
                foreach ($notifications->chunk(3) as $chunk) {
                    if ($batches++ >= $maxBatches || ($deadline !== null && microtime(true) + 16 >= $deadline)) {
                        return;
                    }
                    $lines = ['<b>Моніторинг сайтів</b>'];
                    foreach ($chunk as $item) {
                        $incident = DB::table('monitoring_incidents')->find($item->incident_id);
                        $site = Site::find($incident->site_id);
                        if (! $site) {
                            continue;
                        }
                        $label = ['down' => '🔴 Недоступний', 'recovered' => '🟢 Відновився', 'planned' => '⏸ Планово вимкнений'][$item->event];
                        $start = CarbonImmutable::parse($incident->opened_at, 'UTC');
                        $end = $incident->closed_at ? CarbonImmutable::parse($incident->closed_at, 'UTC') : CarbonImmutable::now('UTC');
                        $lines[] = $label.': '.e(mb_substr($site->name, 0, 100))."\n"
                            .e(mb_substr($site->url, 0, 180))."\n"
                            .'Початок: '.$start->timezone(config('monitoring.timezone'))->format('d.m H:i')
                            .' · до останньої події: '.MonitoringReport::duration($end->timestamp - $start->timestamp)."\n"
                            .e(mb_substr($incident->error ?? '', 0, 180))."\n"
                            .'<a href="'.e(route('portal.monitoring.show', $site)).'">Історія перевірок</a>';
                    }
                    $result = app(TelegramBotService::class)->sendMessage($user->telegram_chat_id, implode("\n\n", $lines));
                    foreach ($chunk as $item) {
                        DB::table('monitoring_notifications')->where('id', $item->id)->update([
                            'attempts' => $item->attempts + 1, 'sent_at' => ($result['ok'] ?? false) ? now() : null,
                            'next_attempt_at' => now()->addMinutes(min(30, 5 * ($item->attempts + 1))), 'updated_at' => now(),
                        ]);
                    }
                }
            }
        } finally {
            $lock->release();
        }
    }
}
