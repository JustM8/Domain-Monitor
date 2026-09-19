<?php

namespace Tests\Feature;

use App\Modules\Monitoring\Services\MonitoringNotifications;
use App\Modules\Monitoring\Services\SiteMonitor;
use App\Modules\Shared\Http\OutboundAddressPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(OutboundAddressPolicy::class, new class extends OutboundAddressPolicy
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        config(['monitoring.pause_ms' => 0]);
    }

    public function test_two_failures_open_one_incident_and_recovery_closes_it(): void
    {
        $site = $this->site();
        $user = $this->portalUser('pm', 'active', ['telegram_chat_id' => '77', 'telegram_verified_at' => now()]);
        DB::table('monitoring_settings')->insert(['id' => 1, 'recipient_ids' => json_encode([$user->id])]);
        Http::fake(['example.com*' => Http::sequence()->push('', 500)->push('', 500)->push('', 503)->push('OK', 200), 'api.telegram.org/*' => Http::response(['ok' => true])]);
        $monitor = app(SiteMonitor::class);
        $monitor->check($site);
        $this->assertDatabaseCount('monitoring_incidents', 0);
        $monitor->check($site);
        $monitor->check($site);
        $this->assertDatabaseCount('monitoring_incidents', 1);
        $this->assertDatabaseCount('monitoring_notifications', 1);
        $monitor->check($site);
        $this->assertDatabaseHas('monitoring_incidents', ['site_id' => $site->id, 'close_reason' => 'up']);
        $this->assertDatabaseCount('monitoring_notifications', 2);
        app(MonitoringNotifications::class)->deliver();
        $this->assertSame(2, DB::table('monitoring_notifications')->whereNotNull('sent_at')->count());
        $this->assertDatabaseHas('monitoring_daily', ['site_id' => $site->id, 'checks' => 4, 'successful' => 1]);
    }

    public function test_confirmed_planned_downtime_is_not_an_outage(): void
    {
        $site = $this->site(['is_active' => false]);
        $site->forceFill(['control_version' => 2, 'confirmed_control_version' => 2, 'confirmed_state' => 'disabled'])->save();
        Http::fake(['*' => Http::response('', 503)]);
        app(SiteMonitor::class)->check($site);
        app(SiteMonitor::class)->check($site);
        $this->assertDatabaseHas('monitoring_states', ['site_id' => $site->id, 'availability' => 'planned']);
        $this->assertDatabaseCount('monitoring_incidents', 0);
        $site->forceFill(['control_version' => 3])->save();
        app(SiteMonitor::class)->check($site);
        app(SiteMonitor::class)->check($site);
        $this->assertDatabaseCount('monitoring_incidents', 1);
    }

    public function test_cron_checks_only_due_prod_sites_and_respects_batch_and_lock(): void
    {
        $prod = $this->site();
        $other = $this->site(['name' => 'Second']);
        $dev = $this->site(['environment' => 'dev']);
        Http::fake(['*' => Http::response('OK')]);
        config(['monitoring.batch_size' => 1]);
        $this->artisan('monitoring:run')->assertSuccessful();
        $this->assertDatabaseHas('monitoring_checks', ['site_id' => $prod->id]);
        $this->assertDatabaseMissing('monitoring_checks', ['site_id' => $dev->id]);
        $this->artisan('monitoring:run')->assertSuccessful();
        $this->assertDatabaseHas('monitoring_checks', ['site_id' => $other->id]);
        $lock = Cache::lock('monitoring:run', 600);
        $lock->get();
        $this->artisan('monitoring:run')->assertSuccessful();
        $lock->release();
        $this->assertDatabaseCount('monitoring_runs', 2);
    }

    public function test_failed_notifications_retry_and_blocked_recipient_is_cancelled(): void
    {
        $site = $this->site();
        $user = $this->portalUser('admin', 'active', ['telegram_chat_id' => '88', 'telegram_verified_at' => now()]);
        DB::table('monitoring_settings')->insert(['id' => 1, 'recipient_ids' => json_encode([$user->id])]);
        $incident = DB::table('monitoring_incidents')->insertGetId(['site_id' => $site->id, 'opened_at' => now()]);
        $notifications = app(MonitoringNotifications::class);
        $notifications->enqueue($incident, 'down');
        Http::fake(['*' => Http::response(['ok' => false], 429)]);
        $notifications->deliver();
        $this->assertDatabaseHas('monitoring_notifications', ['sent_at' => null, 'attempts' => 1]);
        $user->update(['is_active' => false, 'approval_status' => 'blocked']);
        $this->travel(6)->minutes();
        $notifications->deliver();
        $this->assertSame(1, DB::table('monitoring_notifications')->whereNotNull('cancelled_at')->count());
        Http::assertSentCount(1);
    }
}
