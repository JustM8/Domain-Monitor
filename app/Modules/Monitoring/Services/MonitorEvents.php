<?php

namespace App\Modules\Monitoring\Services;

use App\Models\User;
use App\Modules\TelegramAccess\Services\TelegramBotService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MonitorEvents
{
    public function recipients(object $m): array
    {
        return match ($m->recipient_mode) {
            'mute' => [], 'explicit' => DB::table('monitoring_monitor_recipients')->where('monitor_id', $m->id)->pluck('user_id')->map(fn ($v) => (int) $v)->all(),
            default => DB::table('monitoring_default_recipients')->pluck('user_id')->map(fn ($v) => (int) $v)->all(),
        };
    }

    public static function target(string $target): string
    {
        $p = parse_url($target);
        if (! $p || ! isset($p['host'])) {
            return mb_substr(preg_replace('/[^a-zA-Z0-9.:\-]/', '', $target), 0, 255);
        }

        return ($p['scheme'] ?? 'https').'://'.$p['host'].(isset($p['port']) ? ':'.$p['port'] : '').($p['path'] ?? '/');
    }

    public function emit(object $m, object $s, string $type, CarbonImmutable $at, array $details = []): int
    {
        $site = DB::table('sites')->where('id', $m->site_id)->first();
        $c = json_decode($m->config, true);
        $sequence = ++$s->event_sequence;
        $snapshot = ['monitor_name' => $m->name, 'site_name' => $site->name, 'target' => self::target($c['url'] ?? $c['hostname'] ?? $site->url),
            'timezone' => MonitoringTime::zone($m->site_id), 'transition' => $type] + $details;
        $id = DB::table('monitoring_events')->insertGetId(['monitor_id' => $m->id, 'event_sequence' => $sequence, 'incident_id' => $s->active_incident_id, 'type' => $type,
            'occurred_at' => MonitoringTime::store($at), 'snapshot' => json_encode($snapshot)]);
        DB::table('monitoring_states')->where('monitor_id', $m->id)->update(['event_sequence' => $sequence]);
        foreach ($this->recipients($m) as $uid) {
            DB::table('monitoring_deliveries')->insertOrIgnore(['event_id' => $id, 'user_id' => $uid, 'next_attempt_at' => MonitoringTime::store($at)]);
        }

        return $id;
    }

    public function message(object $event): string
    {
        $d = json_decode($event->snapshot, true);
        $label = ['monitor_down' => '🔴 DOWN', 'monitor_recovered' => '🟢 Відновлено', 'heartbeat_missed' => '🔴 Heartbeat пропущено', 'heartbeat_recovered' => '🟢 Heartbeat відновлено', 'ssl_warning' => '⚠️ SSL warning', 'ssl_critical' => '🔴 SSL critical', 'ssl_renewed' => '🟢 SSL оновлено'][$event->type] ?? $event->type;

        return $label."\n".$d['site_name'].' / '.$d['monitor_name']."\n".$d['target']."\n".MonitoringTime::display($event->occurred_at, $d['timezone']).' '.$d['timezone']."\n".($d['cause'] ?? '')
            .(isset($d['started_at']) ? "\nIncident start: ".MonitoringTime::display($d['started_at'], $d['timezone']) : '');
    }

    public function deliver(int $limit = 20, ?float $budget = null): int
    {
        MonitoringTime::session();
        $at = MonitoringTime::store(MonitoringTime::now());
        $sent = 0;
        $rows = DB::table('monitoring_deliveries')->whereIn('status', ['pending', 'retry_wait', 'claimed'])->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', $at))
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $at))->orderBy('next_attempt_at')->orderBy('id')->limit($limit)->get();
        foreach ($rows as $d) {
            if ($budget && microtime(true) > $budget - 20) {
                break;
            }
            $token = Str::random(40);
            $claimed = DB::table('monitoring_deliveries')->where('id', $d->id)->whereIn('status', ['pending', 'retry_wait', 'claimed'])
                ->where(fn ($q) => $q->whereNull('lease_until')->orWhere('lease_until', '<=', $at))
                ->update(['status' => 'claimed', 'claim_token' => $token, 'lease_until' => MonitoringTime::store(MonitoringTime::now()->addSeconds(90)), 'attempts' => $d->attempts + 1]);
            if (! $claimed) {
                continue;
            }
            $event = DB::table('monitoring_events')->where('id', $d->event_id)->first();
            $m = DB::table('monitoring_monitors')->where('id', $event->monitor_id)->first();
            $u = User::with('role')->find($d->user_id);
            $allowed = $u && $u->canPortal('monitoring.read') && $u->canUseAccessBot() && $u->telegramIsLinked() && $m->enabled && DB::table('sites')->where('id', $m->site_id)->whereNull('deleted_at')->exists() && in_array((int) $u->id, $this->recipients($m), true);
            $ack = DB::table('monitoring_deliveries')->where('id', $d->id)->where('claim_token', $token)->where('status', 'claimed');
            if (! $allowed) {
                $ack->update(['status' => 'cancelled', 'terminal_at' => $at, 'claim_token' => null]);

                continue;
            }
            if (MonitoringTime::parse($event->occurred_at)->lt(MonitoringTime::now()->subDay()) || $d->attempts >= 12) {
                $ack->update(['status' => 'failed_terminal', 'terminal_at' => $at, 'claim_token' => null]);

                continue;
            }
            try {
                $result = app(TelegramBotService::class)->sendMessage($u->telegram_chat_id, $this->message($event), ['parse_mode' => null]);
            } catch (\Throwable) {
                $result = ['ok' => false];
            }
            $now = MonitoringTime::store(MonitoringTime::now());
            if ($result['ok'] ?? false) {
                $ack->update(['status' => 'sent', 'sent_at' => $now, 'terminal_at' => $now, 'claim_token' => null, 'lease_until' => null]);
                $sent++;
            } elseif (in_array($result['error_code'] ?? null, [400, 403], true)) {
                $ack->update(['status' => 'failed_terminal', 'terminal_at' => $now, 'claim_token' => null]);
            } else {
                $ack->update(['status' => 'retry_wait', 'lease_until' => null, 'claim_token' => null, 'next_attempt_at' => MonitoringTime::store(MonitoringTime::now()->addSeconds(min(1800, 300 * (2 ** min(5, $d->attempts)))))]);
            }
        }

        return $sent;
    }
}
