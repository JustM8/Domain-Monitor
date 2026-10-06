<?php

namespace Tests\Feature;

use App\Modules\Monitoring\Services\MonitorCertificates;
use App\Modules\Monitoring\Services\MonitorEvents;
use App\Modules\Monitoring\Services\MonitorHeartbeat;
use App\Modules\Monitoring\Services\MonitorHistory;
use App\Modules\Monitoring\Services\MonitoringSettings;
use App\Modules\Monitoring\Services\MonitoringTime;
use App\Modules\Monitoring\Services\MonitorManager;
use App\Modules\Monitoring\Services\MonitorRetention;
use App\Modules\Monitoring\Services\MonitorRunner;
use App\Modules\Monitoring\Services\TcpProbe;
use App\Modules\Shared\Http\OutboundAddressPolicy;
use App\Modules\Site\Services\SiteMetadataService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class MonitoringV2Test extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 10:00:00', 'UTC'));
        $this->app->instance(\App\Modules\Monitoring\Services\MonitoringAddressPolicy::class, new class extends \App\Modules\Monitoring\Services\MonitoringAddressPolicy
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        Http::fake(['https://example.com*' => Http::response('healthy', 200)]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function monitor(): object
    {
        return app(MonitorManager::class)->primary($this->site());
    }

    private function state(object $m): object
    {
        return DB::table('monitoring_states')->where('monitor_id', $m->id)->first();
    }

    private function probeResult(bool $up = true): array
    {
        return ['up' => $up, 'response_ms' => 120, 'http_status' => $up ? 200 : 503, 'error_kind' => $up ? null : 'http', 'certificate' => null];
    }

    private function tick(object $m, bool $up = true): void
    {
        $s = $this->state($m);
        CarbonImmutable::setTestNow(MonitoringTime::parse($s->next_due_at)->max(MonitoringTime::now()));
        $runner = app(MonitorRunner::class);
        $c = $runner->claim($m->id);
        $this->assertNotNull($c);
        $this->assertTrue($runner->commit($c, $this->probeResult($up)));
    }

    public function test_1000_healthy_probes_have_compressed_storage_and_no_raw_stream(): void
    {
        $m = $this->monitor();
        for ($i = 0; $i < 1000; $i++) {
            $this->tick($m);
        }
        $this->assertSame(1, DB::table('monitoring_states')->count());
        $this->assertLessThanOrEqual(2, DB::table('monitoring_spans')->count());
        $this->assertLessThanOrEqual(5, DB::table('monitoring_rollups')->count());
        $this->assertSame(1000, (int) DB::table('monitoring_rollups')->sum('sample_count'));
        $this->assertSame(0, DB::table('monitoring_diagnostics')->count());
        $this->assertSame(0, DB::table('monitoring_events')->count());
        $this->assertSame(0, DB::table('monitoring_incidents')->count());
        $this->assertFalse(Schema::hasTable('monitoring_checks'));
        foreach (DB::table('monitoring_rollups')->get() as $r) {
            $this->assertSame((int) $r->expected_us, $r->up_us + $r->down_us + $r->unknown_us + $r->planned_us);
        }
    }

    public function test_confirmation_repeated_failures_recovery_and_unknown_accounting(): void
    {
        $m = $this->monitor();
        $this->tick($m, false);
        $first = $this->state($m)->first_failed_at;
        $this->assertSame('unknown', $this->state($m)->availability);
        $this->assertSame('confirming_failure', $this->state($m)->phase);
        $this->assertSame(0, DB::table('monitoring_events')->count());
        $this->assertSame(60, MonitoringTime::now()->diffInSeconds(MonitoringTime::parse($this->state($m)->next_due_at)));
        for ($i = 0; $i < 100; $i++) {
            $this->tick($m, false);
        }
        $this->assertSame(1, DB::table('monitoring_incidents')->count());
        $this->assertSame(1, DB::table('monitoring_events')->count());
        $this->assertLessThan(20, DB::table('monitoring_diagnostics')->count());
        $incident = DB::table('monitoring_incidents')->first();
        $this->assertSame($first, $incident->started_at);
        $this->assertTrue(MonitoringTime::parse($incident->detected_at)->gt(MonitoringTime::parse($first)));
        $this->tick($m);
        $this->assertSame('up', $this->state($m)->availability);
        $this->assertDatabaseHas('monitoring_incidents', ['id' => $incident->id, 'close_reason' => 'recovered']);
        $this->assertSame(2, DB::table('monitoring_events')->count());
    }

    public function test_manual_check_is_isolated_and_bounded(): void
    {
        $m = $this->monitor();
        $before = $this->state($m);
        $r = app(MonitorRunner::class)->manual($m->id);
        $this->assertTrue($r['up']);
        $this->assertTrue($r['persisted']);
        $after = $this->state($m);
        unset($before->manual_day, $before->manual_attempts, $after->manual_day, $after->manual_attempts);
        $this->assertEquals($before, $after);
        $this->assertSame(0, DB::table('monitoring_rollups')->count());
        $this->assertSame(0, DB::table('monitoring_events')->count());
        for ($i = 1; $i < 20; $i++) {
            app(MonitorRunner::class)->manual($m->id);
        }
        $this->expectException(\RuntimeException::class);
        app(MonitorRunner::class)->manual($m->id);
    }

    public function test_claim_fencing_consumption_revision_and_expired_lease(): void
    {
        $m = $this->monitor();
        DB::table('monitoring_states')->where('monitor_id', $m->id)->update(['next_due_at' => MonitoringTime::store(MonitoringTime::now())]);
        $r = app(MonitorRunner::class);
        $old = $r->claim($m->id);
        $this->assertNull($r->claim($m->id));
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(121));
        $new = $r->claim($m->id);
        $this->assertFalse($r->commit($old, $this->probeResult()));
        $this->assertTrue($r->commit($new, $this->probeResult()));
        $this->assertFalse($r->commit($new, $this->probeResult()));
        CarbonImmutable::setTestNow(MonitoringTime::parse($this->state($m)->next_due_at));
        $c = $r->claim($m->id);
        app(MonitorManager::class)->reset($m->id, 'configuration_changed');
        $this->assertFalse($r->commit($c, $this->probeResult()));
    }

    public function test_freshness_gaps_are_unknown_and_backlog_skips_catchup_slots(): void
    {
        $m = $this->monitor();
        $this->tick($m);
        $observed = MonitoringTime::now();
        CarbonImmutable::setTestNow($observed->addSeconds(1800));
        $this->assertSame('unknown', MonitorHistory::availability($this->state($m)));
        $this->tick($m);
        $this->assertGreaterThanOrEqual(1200 * 1000000, DB::table('monitoring_rollups')->sum('unknown_us'));
        $this->assertTrue(MonitoringTime::parse($this->state($m)->next_due_at)->gt(MonitoringTime::now()));
    }

    public function test_global_custom_interval_phasing_and_heartbeat_independence(): void
    {
        $m = $this->monitor();
        $custom = app(MonitorManager::class)->create($m->site_id, ['name' => 'Health', 'type' => 'http', 'enabled' => true, 'custom_interval_seconds' => 600, 'config' => ['url' => 'https://example.com/health']]);
        $hb = app(MonitorManager::class)->create($m->site_id, ['name' => 'Backup', 'type' => 'heartbeat', 'enabled' => true, 'config' => ['expected_interval_seconds' => 86400, 'grace_seconds' => 300]]);
        $deadline = $this->state($hb)->deadline_at;
        $this->assertNull($m->custom_interval_seconds);
        $this->assertLessThan(300, MonitoringTime::now()->diffInSeconds(MonitoringTime::parse($this->state($m)->next_due_at)));
        $data = MonitoringSettings::DEFAULTS + ['tcp_allowed_ports' => [80, 443]];
        $data['default_check_interval_seconds'] = 900;
        app(MonitoringSettings::class)->update($data);
        $this->assertSame(900, app(MonitoringSettings::class)->effective($m)['interval']);
        $this->assertSame(600, app(MonitoringSettings::class)->effective($custom)['interval']);
        $this->assertSame($deadline, $this->state($hb)->deadline_at);
    }

    public function test_kyiv_new_york_dst_and_php_timezone_contract(): void
    {
        $old = date_default_timezone_get();
        date_default_timezone_set('America/Los_Angeles');
        try {
            $utc = MonitoringTime::store(CarbonImmutable::parse('2026-10-05 23:20', 'Europe/Kyiv'));
            $this->assertSame('2026-10-05 20:20:00.000000', $utc);
            $this->assertSame('05.10.2026 23:20:00', MonitoringTime::display($utc));
            $this->assertSame('05.10.2026 16:20:00', MonitoringTime::display($utc, 'America/New_York'));
            foreach (['2026-03-29' => 82800, '2026-10-25' => 90000] as $day => $seconds) {
                $a = CarbonImmutable::parse($day, 'Europe/Kyiv');
                $parts = [];
                app(MonitorHistory::class)->split($a->utc(), $a->addDay()->utc(), function ($d, $us) use (&$parts) {
                    $parts[$d] = $us;
                });
                $this->assertSame([$day => $seconds * 1000000], $parts);
            }
            $user = $this->portalUser();
            $this->actingAs($user)->get('/portal/monitoring')->assertOk();
            $this->artisan('monitoring:run')->assertSuccessful();
            $this->assertSame('2026-10-05 10:00:00.000000', DB::table('monitoring_runs')->latest('id')->value('started_at'));
        } finally {
            date_default_timezone_set($old);
        }
    }

    public function test_heartbeat_initial_miss_ping_duplicate_rotation_revoke_and_observer_gap(): void
    {
        $site = $this->site();
        $h = app(MonitorHeartbeat::class);
        $m = app(MonitorManager::class)->create($site->id, ['name' => 'DB backup', 'type' => 'heartbeat', 'enabled' => true, 'config' => ['expected_interval_seconds' => 600, 'grace_seconds' => 60]]);
        $this->assertSame('unknown', $this->state($m)->availability);
        $token = $h->rotate($m->id);
        $stored = DB::table('monitoring_heartbeat_credentials')->first();
        $this->assertStringNotContainsString($token, json_encode($stored));
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(661));
        $h->evaluate();
        $this->assertSame('down', $this->state($m)->availability);
        $h->evaluate();
        $this->assertSame(1, DB::table('monitoring_incidents')->count());
        $this->assertTrue($h->accept($token, 'job-1'));
        $this->assertSame('up', $this->state($m)->availability);
        $deadline = $this->state($m)->deadline_at;
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(30));
        $this->assertTrue($h->accept($token, 'job-1'));
        $this->assertSame($deadline, $this->state($m)->deadline_at);
        $this->assertNull($this->state($m)->response_ms);
        $new = $h->rotate($m->id, 60);
        $this->assertTrue($h->accept($token));
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(61));
        $this->assertFalse($h->accept($token));
        $this->assertTrue($h->accept($new));
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(800));
        $h->evaluate();
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(121));
        $this->assertSame('unknown', MonitorHistory::availability($this->state($m)));
        $h->revoke($m->id);
        $this->assertFalse($h->accept($new));
        $this->assertSame(0, DB::table('monitoring_diagnostics')->count());
    }

    public function test_heartbeat_https_post_endpoint_and_body_timestamp_is_not_authoritative(): void
    {
        $site = $this->site();
        $m = app(MonitorManager::class)->create($site->id, ['name' => 'Job', 'type' => 'heartbeat', 'enabled' => true, 'config' => ['expected_interval_seconds' => 600, 'grace_seconds' => 60]]);
        $token = app(MonitorHeartbeat::class)->rotate($m->id);
        $this->postJson('/api/monitoring/heartbeat', [], ['Authorization' => 'Bearer '.$token])->assertStatus(400);
        $this->postJson('https://localhost/api/monitoring/heartbeat', ['job_run_id' => 'j', 'timestamp' => '2001-01-01'], ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->assertSame(MonitoringTime::store(MonitoringTime::now()), $this->state($m)->last_ping_at);
        $this->get('/api/monitoring/heartbeat')->assertStatus(405);
    }

    public function test_ssl_warning_critical_immediate_critical_renewal_and_temporary_error(): void
    {
        $m = $this->monitor();
        $s = $this->state($m);
        $ssl = app(MonitorCertificates::class);
        $cert = ['target_key' => hash('sha256', 'example.com:443'), 'fingerprint' => str_repeat('a', 64), 'not_before' => MonitoringTime::store(MonitoringTime::now()->subDays(90)), 'not_after' => MonitoringTime::store(MonitoringTime::now()->addDays(13))];
        DB::transaction(fn () => $ssl->observe($m, $s, $cert, MonitoringTime::now()));
        $this->assertSame(1, DB::table('monitoring_events')->where('type', 'ssl_warning')->count());
        CarbonImmutable::setTestNow(MonitoringTime::now()->addDays(11));
        DB::transaction(fn () => $ssl->observe($m, $s, $cert, MonitoringTime::now()));
        $this->assertSame(1, DB::table('monitoring_events')->where('type', 'ssl_critical')->count());
        $ssl->observe($m, $s, null, MonitoringTime::now(), 'tls');
        $this->assertSame($cert['fingerprint'], DB::table('monitoring_certificates')->value('fingerprint'));
        $cert['fingerprint'] = str_repeat('b', 64);
        $cert['not_after'] = MonitoringTime::store(MonitoringTime::now()->addDays(90));
        DB::transaction(fn () => $ssl->observe($m, $s, $cert, MonitoringTime::now()));
        $this->assertSame(1, DB::table('monitoring_events')->where('type', 'ssl_renewed')->count());
        $other = $this->monitor();
        $cert['not_after'] = MonitoringTime::store(MonitoringTime::now()->addDays(2));
        DB::transaction(fn () => $ssl->observe($other, $this->state($other), $cert, MonitoringTime::now()));
        $this->assertSame(0, DB::table('monitoring_events')->where('monitor_id', $other->id)->where('type', 'ssl_warning')->count());
        $this->assertSame(1, DB::table('monitoring_events')->where('monitor_id', $other->id)->where('type', 'ssl_critical')->count());
    }

    public function test_tcp_all_addresses_must_be_public_pinned_ip_and_port_policy(): void
    {
        $tcp = new class extends TcpProbe
        {
            public array $ips = [];

            public ?string $connected = null;

            protected function resolve(string $host): array
            {
                return $this->ips;
            }

            protected function connect(string $numeric, int $port, float $timeout): bool
            {
                $this->connected = $numeric;

                return true;
            }
        };
        foreach ([['10.1.2.3'], ['127.0.0.1'], ['fc00::1'], ['fe80::1'], ['::1'], ['::ffff:127.0.0.1'], ['169.254.169.254'], ['93.184.216.34', '192.168.1.1']] as $ips) {
            $tcp->ips = $ips;
            $this->assertFalse($tcp->run(['hostname' => 'db.example.com', 'port' => 443], 6)['up']);
            $this->assertNull($tcp->connected);
        }
        $tcp->ips = ['93.184.216.34'];
        $this->assertTrue($tcp->run(['hostname' => 'db.example.com', 'port' => 443], 6)['up']);
        $this->assertSame('93.184.216.34', $tcp->connected);
        $this->assertFalse($tcp->run(['hostname' => 'db.example.com', 'port' => 6379], 6)['up']);
    }

    public function test_recipient_modes_immutable_snapshot_retry_and_current_eligibility(): void
    {
        $u = $this->portalUser('pm', 'active', ['telegram_chat_id' => '77', 'telegram_verified_at' => now()]);
        DB::table('monitoring_default_recipients')->insert(['user_id' => $u->id]);
        $m = $this->monitor();
        $events = app(MonitorEvents::class);
        $this->assertSame([$u->id], $events->recipients($m));
        $m->recipient_mode = 'explicit';
        $this->assertSame([], $events->recipients($m));
        $m->recipient_mode = 'mute';
        $this->assertSame([], $events->recipients($m));
        $this->tick($m, false);
        $this->tick($m, false);
        $event = DB::table('monitoring_events')->first();
        DB::table('sites')->where('id', $m->site_id)->update(['name' => 'Renamed']);
        $this->assertStringContainsString('Test site', $events->message($event));
        Http::fake(['api.telegram.org/*' => Http::sequence()->push(['ok' => false, 'error_code' => 429], 429)->push(['ok' => true])]);
        $events->deliver();
        $this->assertDatabaseHas('monitoring_deliveries', ['status' => 'retry_wait', 'attempts' => 1]);
        CarbonImmutable::setTestNow(MonitoringTime::now()->addSeconds(301));
        $events->deliver();
        $this->assertDatabaseHas('monitoring_deliveries', ['status' => 'sent', 'attempts' => 2]);
        $this->tick($m);
        $u->update(['is_active' => false]);
        $events->deliver();
        $this->assertDatabaseHas('monitoring_deliveries', ['status' => 'cancelled']);
    }

    public function test_quota_exhaustion_does_not_lose_incident(): void
    {
        $m = $this->monitor();
        $at = MonitoringTime::now();
        for ($i = 0; $i < 448; $i++) {
            DB::table('monitoring_diagnostics')->insert(['monitor_id' => $m->id, 'kind' => 'manual', 'day' => $at->subDays(1)->toDateString(), 'recorded_at' => MonitoringTime::store($at), 'expires_at' => MonitoringTime::store($at->addDays(14)), 'evidence' => '{}']);
        }
        $this->tick($m, false);
        $this->tick($m, false);
        $this->assertSame(448, DB::table('monitoring_diagnostics')->count());
        $this->assertSame(1, DB::table('monitoring_incidents')->count());
        $this->assertGreaterThan(0, $this->state($m)->diagnostics_suppressed);
    }

    public function test_pause_url_revision_soft_delete_restore_and_planned_read_only(): void
    {
        $site = $this->site(['remote_control_enabled' => false]);
        $m = app(MonitorManager::class)->primary($site);
        $this->tick($m, false);
        $this->tick($m, false);
        $before = $site->fresh()->only(['api_token', 'is_active', 'control_version', 'confirmed_state']);
        app(SiteMetadataService::class)->update($site, ['monitoring_enabled' => false]);
        $this->assertNull($this->state($m)->next_due_at);
        $this->assertDatabaseHas('monitoring_incidents', ['close_reason' => 'paused']);
        $this->assertSame(1, DB::table('monitoring_events')->count());
        $this->assertSame($before, $site->fresh()->only(array_keys($before)));
        app(SiteMetadataService::class)->update($site, ['monitoring_enabled' => true]);
        $this->assertSame('unknown', $this->state($m)->availability);
        DB::table('sites')->where('id', $site->id)->update(['is_active' => false, 'confirmed_state' => 'disabled', 'control_version' => 2, 'confirmed_control_version' => 2]);
        $this->tick($m);
        $this->assertSame('planned', $this->state($m)->availability);
        $this->assertSame(false, (bool) $site->fresh()->is_active);
        $site->delete();
        $this->assertNull($this->state($m)->period_id);
        $settings = MonitoringSettings::DEFAULTS + ['tcp_allowed_ports' => [80, 443]];
        $settings['default_check_interval_seconds'] = 600;
        app(MonitoringSettings::class)->update($settings);
        $this->assertNull($this->state($m)->period_id);
        $site->restore();
        $this->assertSame('unknown', $this->state($m)->availability);
        $this->assertNotNull($this->state($m)->period_id);
    }

    public function test_pruning_preserves_monthly_aggregates_and_long_span_prefix(): void
    {
        $m = $this->monitor();
        $this->tick($m);
        CarbonImmutable::setTestNow(MonitoringTime::now()->addDays(100));
        app(MonitorRetention::class)->prune();
        $this->assertGreaterThan(0, DB::table('monitoring_rollups')->count());
        $this->assertGreaterThanOrEqual(MonitoringTime::now()->setTimezone('Europe/Kyiv')->startOfDay()->subDays(90)->utc(), MonitoringTime::parse(DB::table('monitoring_spans')->min('started_at')));
        DB::table('monitoring_rollups')->insert(['monitor_id' => $m->id, 'grain' => 'day', 'bucket_key' => '2020-01-01', 'expected_us' => 100, 'up_us' => 100, 'finalized' => true]);
        app(MonitorRetention::class)->prune();
        $this->assertDatabaseHas('monitoring_rollups', ['grain' => 'month', 'bucket_key' => '2020-01', 'up_us' => 100]);
        $this->assertDatabaseMissing('monitoring_rollups', ['grain' => 'day', 'bucket_key' => '2020-01-01']);
        app(MonitorRetention::class)->prune();
        $this->assertDatabaseHas('monitoring_rollups', ['grain' => 'month', 'bucket_key' => '2020-01', 'up_us' => 100]);
    }

    public function test_unchanged_site_checkbox_and_global_interval_leave_custom_observations_intact(): void
    {
        $site = $this->site();
        $m = app(MonitorManager::class)->primary($site);
        $this->tick($m, false);
        $this->tick($m, false);
        $before = $this->state($m);
        app(SiteMetadataService::class)->update($site, ['name' => 'Renamed', 'monitoring_enabled' => true]);
        $this->assertEquals($before, $this->state($m));
        $custom = app(MonitorManager::class)->create($site->id, ['name' => 'Custom', 'type' => 'http', 'enabled' => true, 'custom_interval_seconds' => 600, 'config' => []]);
        $this->tick($custom);
        $before = $this->state($custom);
        $data = MonitoringSettings::DEFAULTS + ['tcp_allowed_ports' => [80, 443]];
        $data['default_check_interval_seconds'] = 900;
        app(MonitoringSettings::class)->update($data);
        $this->assertEquals($before, $this->state($custom));
    }

    public function test_display_preference_does_not_change_calendar_or_latency_histogram(): void
    {
        $company = \App\Modules\Shared\Models\Company::create(['name' => 'Timezone']);
        $site = $this->site(['company_id' => $company->id]);
        DB::table('monitoring_company_preferences')->insert(['company_id' => $company->id, 'display_timezone' => 'America/New_York']);
        $this->assertSame('America/New_York', MonitoringTime::zone($site->id));
        DB::table('monitoring_site_preferences')->insert(['site_id' => $site->id, 'display_timezone' => 'Asia/Tokyo']);
        $this->assertSame('Asia/Tokyo', MonitoringTime::zone($site->id));
        $m = app(MonitorManager::class)->primary($site);
        $this->tick($m);
        $summary = MonitorHistory::summary(app(MonitorHistory::class)->report($m->id));
        $this->assertSame(250, $summary['p95']);
        $this->assertSame(1, $summary['sample_count']);
        $this->assertSame(MonitoringTime::now()->setTimezone('Europe/Kyiv')->toDateString(), DB::table('monitoring_rollups')->value('bucket_key'));
    }

    public function test_confirmation_retry_can_exceed_short_custom_observation_freshness(): void
    {
        DB::table('monitoring_settings')->where('id', 1)->update(['failure_retry_seconds' => 300]);
        $site = $this->site();
        $m = app(MonitorManager::class)->create($site->id, ['name' => 'Short cadence', 'type' => 'http', 'enabled' => true, 'custom_interval_seconds' => 60, 'config' => []]);
        $this->tick($m, false);
        $this->tick($m, false);
        $this->assertSame('down', $this->state($m)->availability);
        $this->assertSame(1, DB::table('monitoring_incidents')->count());
    }

    public function test_dns_budget_expiry_is_bounded_before_worker_dispatch(): void
    {
        $policy = new class extends \App\Modules\Monitoring\Services\MonitoringAddressPolicy
        {
            public function lookup(): array
            {
                return $this->resolve('localhost');
            }
        };
        $policy->budget(hrtime(true) / 1e9 - 1);
        $this->expectExceptionMessage('Monitoring DNS timeout');
        $policy->lookup();
    }

    public function test_http_subsecond_remaining_budget_is_not_cast_to_unlimited_timeout(): void
    {
        $policy = new class extends OutboundAddressPolicy
        {
            protected function resolve(string $host): array
            {
                usleep(1200000);

                return ['93.184.216.34'];
            }
        };
        Http::fake(['budget.example/*' => function ($request, $options) {
            $this->assertGreaterThan(0, $options['timeout']);
            $this->assertLessThan(1, $options['timeout']);

            return Http::response('OK');
        }]);
        (new \App\Modules\Shared\Http\SafeHttp($policy))->get('https://budget.example/', 2, null, true);
    }

    public function test_read_only_verification_baseline_and_credentials(): void
    {
        $this->site();
        $path = storage_path('logs/v2-test-baseline-'.bin2hex(random_bytes(8)).'.json');
        try {
            $this->artisan('monitoring:verify', ['--save' => $path])->assertSuccessful();
            $this->artisan('monitoring:verify', ['--baseline' => $path])->assertSuccessful();
            $this->assertStringNotContainsString('site-test-token', file_get_contents($path));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_ssl_markers_survive_event_delivery_prune_and_wrong_target_is_rejected(): void
    {
        $m = $this->monitor();
        $s = $this->state($m);
        $ssl = app(MonitorCertificates::class);
        $cert = ['target_key' => hash('sha256', 'other.example:443'), 'fingerprint' => str_repeat('c', 64), 'not_before' => MonitoringTime::store(MonitoringTime::now()->subDays(80)), 'not_after' => MonitoringTime::store(MonitoringTime::now()->addDays(2))];
        $ssl->observe($m, $s, $cert, MonitoringTime::now());
        $this->assertSame(0, DB::table('monitoring_certificates')->count());
        $cert['target_key'] = hash('sha256', 'example.com:443');
        $ssl->observe($m, $s, $cert, MonitoringTime::now());
        DB::table('monitoring_events')->delete();
        $ssl->observe($m, $s, $cert, MonitoringTime::now());
        $this->assertSame(0, DB::table('monitoring_events')->count());
        $this->assertTrue((bool) DB::table('monitoring_certificates')->value('critical_emitted'));
    }

    public function test_ui_settings_validation_permissions_and_monitor_creation(): void
    {
        $m = $this->monitor();
        $admin = $this->portalUser();
        $this->actingAs($admin)->get('/portal/monitoring')->assertOk();
        $this->get('/portal/monitoring/monitors/'.$m->id)->assertOk();
        $this->get('/portal/monitoring/settings')->assertOk();
        $this->get('/portal/sites/'.$m->site_id)->assertOk();
        $data = MonitoringSettings::DEFAULTS + ['tcp_allowed_ports' => '80,443'];
        $this->post('/portal/monitoring/settings', $data)->assertRedirect();
        $data['ssl_critical_days'] = 30;
        $this->post('/portal/monitoring/settings', $data)->assertSessionHasErrors('ssl_critical_days');
        $this->post('/portal/monitoring/monitors', ['site_id' => $m->site_id, 'name' => 'Health', 'type' => 'http', 'enabled' => 1, 'url' => 'https://example.com/health'])->assertRedirect();
        $this->assertSame(2, DB::table('monitoring_monitors')->count());
        $developer = $this->portalUser('developer');
        $this->actingAs($developer)->get('/portal/monitoring')->assertForbidden();
        $this->post('/portal/monitoring/settings', $data)->assertForbidden();
    }
}
