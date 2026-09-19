<?php

namespace App\Modules\Monitoring\Services;

use App\Models\User;
use App\Modules\TelegramAccess\Services\TelegramBotService;
use Illuminate\Support\Facades\DB;

class MonitoringNotifications
{
    public function recipientIds(): array
    {
        return json_decode(DB::table('monitoring_settings')->where('id', 1)->value('recipient_ids') ?? '[]', true) ?: [];
    }

    public function enqueue(int $incident, string $event): void
    {
        foreach ($this->recipientIds() as $id) {
            DB::table('monitoring_notifications')->insertOrIgnore([
                'incident_id' => $incident, 'user_id' => $id, 'event' => $event,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function deliver(int $maxBatches = 3, ?float $deadline = null): void
    {
        $rows = DB::table('monitoring_notifications')->whereNull('sent_at')->whereNull('cancelled_at')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(100)->get()->groupBy('user_id');
        $batches = 0;
        foreach ($rows as $userId => $notifications) {
            $user = User::with('role')->find($userId);
            if (! $user || ! $user->canPortal('monitoring.read') || ! $user->canUseAccessBot() || ! $user->telegramIsLinked() || ! in_array((int) $userId, $this->recipientIds(), true)) {
                DB::table('monitoring_notifications')->whereIn('id', $notifications->pluck('id'))->update(['cancelled_at' => now()]);

                continue;
            }
            foreach ($notifications->chunk(8) as $chunk) {
                if ($batches++ >= $maxBatches || ($deadline !== null && microtime(true) + 16 >= $deadline)) {
                    return;
                }
                $lines = ['<b>Моніторинг сайтів</b>'];
                foreach ($chunk as $item) {
                    $incident = DB::table('monitoring_incidents')->find($item->incident_id);
                    $site = DB::table('sites')->find($incident->site_id);
                    $label = ['down' => '🔴 Недоступний', 'recovered' => '🟢 Відновився', 'planned' => '⏸ Планово вимкнений'][$item->event];
                    $lines[] = $label.': '.e(mb_substr($site->name, 0, 100)).' · '.e(mb_substr($site->url, 0, 150)).' ('.$item->created_at.')';
                }
                $result = app(TelegramBotService::class)->sendMessage($user->telegram_chat_id, implode("\n", $lines));
                foreach ($chunk as $item) {
                    DB::table('monitoring_notifications')->where('id', $item->id)->update([
                        'attempts' => $item->attempts + 1,
                        'sent_at' => ($result['ok'] ?? false) ? now() : null,
                        'next_attempt_at' => now()->addMinutes(min(30, 5 * ($item->attempts + 1))), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }
}
