<?php

namespace Tests\Feature;

use App\Modules\Shared\Http\OutboundAddressPolicy;
use App\Modules\Shared\Http\SafeHttp;
use App\Modules\Site\Services\SiteControlService;
use App\Modules\Site\Services\SiteSyncService;
use App\Modules\TelegramSupport\Services\SupportUpdateHandler;
use App\Modules\TelegramSupport\Services\SupportWebhookInbox;
use App\Modules\TelegramSupport\Services\TelegramSupportBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class PatchRecoveryTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    public function test_interrupted_webhook_keeps_partial_work_and_does_not_repeat_external_action(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 10]])]);
        $baseline = DB::transactionLevel();
        $handler = new class($baseline) extends SupportUpdateHandler
        {
            public function __construct(private int $baseline) {}

            public function handle(array $payload, TelegramSupportBotService $bot)
            {
                if (DB::transactionLevel() !== $this->baseline) {
                    throw new \LogicException('Unexpected enclosing transaction');
                }
                DB::table('monitoring_settings')->where('id', 1)->update(['default_timeout_seconds' => 4]);
                $bot->sendMessage('5', 'Test');
                throw new \RuntimeException('Simulated interruption after external delivery');
            }
        };
        $inbox = app(SupportWebhookInbox::class);
        $bot = app(TelegramSupportBotService::class);
        $payload = ['update_id' => 800, 'message' => ['text' => 'private content']];
        $inbox->handle($payload, $handler, $bot);
        $inbox->handle($payload, $handler, $bot);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('monitoring_settings', ['id' => 1, 'default_timeout_seconds' => 4]);
        $row = DB::table('telegram_webhook_receipts')->first();
        $this->assertSame('needs_review', $row->status);
        $this->assertStringNotContainsString('private content', $row->payload);
        $this->assertSame($payload, json_decode(Crypt::decryptString($row->payload), true));
        $this->actingAs($this->portalUser('manager'))->get('/portal/support')->assertOk()->assertSee('Перервані події Telegram: 1');
        $this->artisan('telegram-support:review', ['--resolve' => 800])->assertSuccessful();
        $inbox->handle($payload, $handler, $bot);
        Http::assertSentCount(1);
        $this->assertDatabaseHas('telegram_webhook_receipts', ['update_id' => 800, 'status' => 'resolved', 'payload' => null]);
    }

    public function test_cron_recovers_abandoned_runs_without_marking_recent_webhooks(): void
    {
        $site = $this->site(['environment' => 'dev']);
        $run = DB::table('monitoring_runs')->insertGetId(['started_at' => now()->subHour()]);
        foreach ([801 => now()->subHour(), 802 => now()] as $id => $date) {
            DB::table('telegram_webhook_receipts')->insert(['bot' => 'support', 'update_id' => $id, 'status' => 'processing', 'started_at' => $date]);
        }
        DB::table('site_control_attempts')->insert(['site_id' => $site->id, 'version' => 1, 'desired_state' => 'active', 'started_at' => now()->subHour()]);
        $this->artisan('monitoring:run')->assertSuccessful();
        $this->assertDatabaseHas('monitoring_runs', ['id' => $run, 'status' => 'interrupted']);
        $this->assertDatabaseHas('telegram_webhook_receipts', ['update_id' => 801, 'status' => 'needs_review']);
        $this->assertDatabaseHas('telegram_webhook_receipts', ['update_id' => 802, 'status' => 'processing']);
        $this->assertDatabaseHas('site_control_attempts', ['site_id' => $site->id, 'status' => 'interrupted']);
    }

    public function test_commands_restore_revisions_and_keep_each_attempt_without_secrets(): void
    {
        $this->app->instance(OutboundAddressPolicy::class, new class extends OutboundAddressPolicy
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        $user = $this->portalUser('pm');
        $this->actingAs($user);
        $site = $this->site(['remote_control_enabled' => true]);
        $site = app(SiteControlService::class)->change($site, $user, false, 'Contract pause');
        Http::fake(['*' => Http::sequence()->push(['status' => 'active', 'version' => 1])->push(['status' => 'disabled', 'version' => 1])]);
        $service = app(SiteSyncService::class);
        $this->assertFalse($service->sync($site, 'disabled')['ok']);
        $this->assertTrue($service->sync($site, 'disabled')['ok']);
        $this->assertDatabaseHas('site_revisions', ['site_id' => $site->id, 'change_type' => 'disable', 'changed_by' => $user->id]);
        $this->assertDatabaseHas('site_control_attempts', ['site_id' => $site->id, 'status' => 'failed', 'user_id' => $user->id]);
        $this->assertDatabaseHas('site_control_attempts', ['site_id' => $site->id, 'status' => 'confirmed', 'version' => 1]);
        $this->get('/portal/sites/'.$site->id)->assertOk()->assertSee('Історія команд керування')->assertSee('Підтверджено');
        $this->assertStringNotContainsString('site-test-token', json_encode(DB::table('site_control_attempts')->get()));
        app(SiteControlService::class)->change($site, $user, true, 'Resolved');
        $this->assertDatabaseHas('site_revisions', ['site_id' => $site->id, 'change_type' => 'enable']);
    }

    public function test_internal_exceptions_are_exact_get_only_and_never_cross_origin_redirects(): void
    {
        $policy = new class extends OutboundAddressPolicy
        {
            protected function resolve(string $host): array
            {
                return match ($host) {
                    'internal.example' => ['10.0.0.5'],'changed.example' => ['10.0.0.6'],default => ['93.184.216.34']
                };
            }
        };
        config(['outbound.internal_targets' => ['https://internal.example:443' => ['10.0.0.5'], 'http://169.254.169.254:80' => ['169.254.169.254'], 'https://changed.example:443' => ['10.0.0.5']]]);
        $this->assertSame('10.0.0.5', $policy->target('https://internal.example/path')['ip']);
        foreach ([['https://internal.example', true], ['http://internal.example', false], ['https://changed.example', false], ['http://169.254.169.254', false]] as [$url,$control]) {
            try {
                $policy->target($url, $control);
                $this->fail('Unsafe exception allowed');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://internal.example'])]);
        try {
            (new SafeHttp($policy))->get('https://public.example');
            $this->fail('Cross-origin internal redirect allowed');
        } catch (\InvalidArgumentException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_unknown_telegram_transport_result_is_held_for_review(): void
    {
        Http::fake(['*' => Http::response('gateway timeout', 504)]);
        $handler = new class extends SupportUpdateHandler
        {
            public function handle(array $payload, TelegramSupportBotService $bot)
            {
                $bot->createForumTopic('5', 'Test');
            }
        };
        $bot = app(TelegramSupportBotService::class);
        $inbox = app(SupportWebhookInbox::class);
        $inbox->handle(['update_id' => 803], $handler, $bot);
        $inbox->handle(['update_id' => 803], $handler, $bot);
        Http::assertSentCount(1);
        $this->assertFalse($bot->handlingWebhook);
        $this->assertDatabaseHas('telegram_webhook_receipts', ['update_id' => 803, 'status' => 'needs_review']);
    }
}
