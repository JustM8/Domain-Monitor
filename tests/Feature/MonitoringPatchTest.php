<?php

namespace Tests\Feature;

use App\Modules\Monitoring\Services\MonitoringNotifications;
use App\Modules\Monitoring\Services\MonitoringReport;
use App\Modules\Monitoring\Services\SiteMonitor;
use App\Modules\Shared\Http\OutboundAddressPolicy;
use App\Modules\Site\Services\SiteMetadataService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class MonitoringPatchTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-09-19 00:00:00', 'UTC'));
        $this->app->instance(OutboundAddressPolicy::class, new class extends OutboundAddressPolicy
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
        config(['monitoring.pause_ms' => 0]);
        $this->fake(['*' => Http::response('Healthy', 200)]);
    }

    private function fake($responses): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    private function at(string $time): void
    {
        $this->travelTo(Carbon::parse('2026-09-19 '.$time, 'UTC'));
    }

    private function report($site, string $from = '2026-09-19 00:00:00', ?string $to = null): array
    {
        return app(MonitoringReport::class)->build([$site->id], [
            'from' => CarbonImmutable::parse($from, 'UTC')->timezone('Europe/Kyiv'),
            'to' => CarbonImmutable::parse($to ?? now(), 'UTC')->timezone('Europe/Kyiv'),
            'group' => 'day', 'period' => 'custom', 'first' => $from,
        ]);
    }

    public function test_manual_diagnostics_do_not_change_schedule_history_or_incidents(): void
    {
        $site = $this->site();
        $monitor = app(SiteMonitor::class);
        $monitor->check($site);
        $state = (array) DB::table('monitoring_states')->first();
        $metrics = (array) DB::table('monitoring_metrics')->first();
        $this->fake(['*' => Http::response('', 500)]);
        $this->travel(2)->minutes();
        $monitor->check($site, true);
        $monitor->check($site, true);
        $this->assertSame($state, (array) DB::table('monitoring_states')->first());
        $this->assertSame($metrics, (array) DB::table('monitoring_metrics')->first());
        $this->assertDatabaseCount('monitoring_incidents', 0);
        $this->assertDatabaseCount('monitoring_checks', 3);
        $this->assertDatabaseHas('monitoring_daily', ['checks' => 1, 'successful' => 1]);
    }

    public function test_cron_selects_enabled_dev_and_skips_paused_prod_without_control_calls(): void
    {
        $paused = $this->site(['monitoring_enabled' => false]);
        $dev = $this->site(['environment' => 'dev', 'monitoring_enabled' => true]);
        $normal = $this->site(['environment' => 'prod', 'remote_control_enabled' => false]);
        $deleted = $this->site();
        $deleted->delete();
        $this->artisan('monitoring:run')->assertSuccessful();
        $this->assertDatabaseMissing('monitoring_checks', ['site_id' => $paused->id]);
        $this->assertDatabaseMissing('monitoring_checks', ['site_id' => $deleted->id]);
        $this->assertDatabaseHas('monitoring_checks', ['site_id' => $dev->id]);
        $this->assertDatabaseHas('monitoring_checks', ['site_id' => $normal->id]);
        Http::assertSentCount(2);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_time_uptime_and_coverage_are_not_check_counts_and_survive_pruning(): void
    {
        $site = $this->site();
        $monitor = app(SiteMonitor::class);
        $this->fake(['*' => Http::sequence()->push('OK')->push('', 500)->push('', 500)->push('OK')->push('OK')]);
        $monitor->check($site);
        $this->at('00:05:00');
        $monitor->check($site);
        $this->at('00:06:00');
        $monitor->check($site);
        $this->assertDatabaseHas('monitoring_incidents', [
            'opened_at' => '2026-09-19 00:05:00', 'detected_at' => '2026-09-19 00:06:00',
        ]);
        $this->at('00:10:00');
        $monitor->check($site);
        $this->at('00:40:00');
        $monitor->check($site);
        $report = $this->report($site);
        $this->assertSame(300, $report['total']['down']);
        $this->assertSame(900, $report['total']['up']);
        $this->assertSame(1200, $report['total']['unknown']);
        $this->assertEquals(75, $report['total']['uptime']);
        $this->assertEquals(50, $report['total']['coverage']);
        $this->assertSame(300, $report['longest']);
        DB::table('monitoring_checks')->delete();
        DB::table('monitoring_daily')->delete();
        $this->assertSame($report['total'], $this->report($site)['total']);
    }

    public function test_pause_resume_and_delete_do_not_count_unmonitored_time(): void
    {
        $site = $this->site();
        $monitor = app(SiteMonitor::class);
        $metadata = app(SiteMetadataService::class);
        $monitor->check($site);
        $this->at('00:05:00');
        $site = $metadata->update($site, ['monitoring_enabled' => false]);
        $this->at('00:30:00');
        $site = $metadata->update($site, ['monitoring_enabled' => true]);
        $this->at('00:35:00');
        $monitor->check($site);
        $this->at('00:40:00');
        $site->delete();
        $this->at('01:00:00');
        $total = $this->report($site)['total'];
        $this->assertSame(900, $total['expected']);
        $this->assertSame(600, $total['up']);
        $this->assertSame(300, $total['unknown']);
        $this->assertEquals(66.67, $total['coverage']);
        $site->restore();
        $this->assertSame(1, DB::table('monitoring_periods')->whereNull('ended_at')->count());
        $this->assertNull(DB::table('monitoring_states')->first()->last_checked_at);
    }

    public function test_planned_status_keeps_actual_http_result_and_is_excluded_from_uptime(): void
    {
        $site = $this->site(['is_active' => false]);
        $site->forceFill(['confirmed_state' => 'disabled', 'confirmed_control_version' => 0])->save();
        $monitor = app(SiteMonitor::class);
        $monitor->check($site);
        $this->assertDatabaseHas('monitoring_checks', ['availability' => 'planned', 'technical_availability' => 'up']);
        $this->at('00:05:00');
        $site->forceFill(['is_active' => true])->save();
        $monitor->check($site);
        $this->at('00:10:00');
        $total = $this->report($site)['total'];
        $this->assertSame(300, $total['planned']);
        $this->assertSame(300, $total['up']);
        $this->assertEquals(100, $total['uptime']);
        $this->assertEquals(100, $total['coverage']);
    }

    public function test_failure_threshold_and_minute_anchor_avoid_extra_interval(): void
    {
        $site = $this->site(['monitoring_failure_threshold' => 2, 'monitoring_interval' => 5, 'environment' => 'dev', 'monitoring_enabled' => true]);
        $this->fake(['*' => Http::response('', 503)]);
        $this->at('00:00:07');
        app(SiteMonitor::class)->check($site);
        $this->assertDatabaseHas('monitoring_states', ['next_check_at' => '2026-09-19 00:01:00']);
        $this->assertDatabaseCount('monitoring_incidents', 0);
        $this->at('00:01:03');
        app(SiteMonitor::class)->check($site);
        $this->assertDatabaseHas('monitoring_incidents', ['opened_at' => '2026-09-19 00:00:07']);
        $this->assertDatabaseHas('monitoring_states', ['next_check_at' => '2026-09-19 00:06:00']);
    }

    public function test_pm_can_edit_monitoring_but_developer_cannot_promote_control_mode(): void
    {
        $site = $this->site();
        $url = '/portal/monitoring/sites/'.$site->id.'/settings';
        $this->actingAs($this->portalUser('pm'))->put($url, [
            'monitoring_enabled' => '0', 'monitoring_interval' => 1,
            'monitoring_failure_threshold' => 1, 'monitoring_status_codes' => '200,204',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse($site->fresh()->monitoring_enabled);
        $this->assertSame([200, 204], $site->fresh()->monitoring_status_codes);
        $developer = $this->portalUser('developer');
        $this->actingAs($developer)->put($url, ['monitoring_enabled' => '1'])->assertForbidden();
        $this->post('/portal/sites', [
            'name' => 'Forbidden control', 'url' => 'https://example.com', 'site_type' => 'site',
            'environment' => 'prod', 'remote_control_enabled' => 1,
        ])->assertSessionHasErrors('remote_control_enabled');
        $this->post('/portal/sites', [
            'name' => 'Observed dev', 'url' => 'https://example.com', 'site_type' => 'site',
            'environment' => 'dev', 'monitoring_enabled' => 1, 'monitoring_interval' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sites', ['name' => 'Observed dev', 'monitoring_enabled' => true, 'remote_control_enabled' => false]);
    }

    public function test_detaching_disabled_or_unconfirmed_child_is_rejected_on_both_paths(): void
    {
        $site = $this->site(['remote_control_enabled' => true, 'is_active' => false]);
        $this->actingAs($this->portalUser())->post('/portal/sites/'.$site->id.'/remote-control')
            ->assertSessionHasErrors('remote_control_enabled');
        try {
            app(SiteMetadataService::class)->update($site, ['remote_control_enabled' => false]);
            $this->fail('Must not strand the disabled child.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remote_control_enabled', $e->errors());
        }
        $site->forceFill(['is_active' => true, 'control_version' => 2, 'confirmed_state' => 'active', 'confirmed_control_version' => 2])->save();
        $this->post('/portal/sites/'.$site->id.'/remote-control')->assertSessionHasNoErrors();
        $this->assertFalse($site->fresh()->remote_control_enabled);
        $this->post('/portal/sites/'.$site->id.'/sync')->assertStatus(422);
        $this->post('/portal/sites/'.$site->id.'/disable', ['disabled_reason' => 'Test'])->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_changed_probe_target_during_http_does_not_commit_a_stale_result(): void
    {
        $site = $this->site();
        $this->fake(function () use ($site) {
            app(SiteMetadataService::class)->update($site, ['monitoring_url' => 'https://example.com/new']);

            return Http::response('OK');
        });
        try {
            app(SiteMonitor::class)->check($site);
            $this->fail('Stale result must be discarded.');
        } catch (\App\Modules\Monitoring\Services\CheckAlreadyRunning $e) {
            $this->assertDatabaseCount('monitoring_checks', 0);
        }
        $this->assertSame('https://example.com/new', $site->fresh()->monitoring_url);
    }

    public function test_content_and_status_expectations_detect_false_200_and_accept_expected_401(): void
    {
        $site = $this->site(['monitoring_content' => 'Expected']);
        app(SiteMonitor::class)->check($site, true);
        $this->assertDatabaseHas('monitoring_checks', ['error_kind' => 'content', 'technical_availability' => 'down']);
        $site = app(SiteMetadataService::class)->update($site, ['monitoring_content' => null, 'monitoring_status_codes' => [401]]);
        $this->fake(['*' => Http::response('', 401)]);
        $this->assertSame('up', app(SiteMonitor::class)->check($site, true)['availability']);
    }

    public function test_specific_recipients_override_global_and_removed_recipient_is_cancelled(): void
    {
        $global = $this->portalUser('pm', 'active', ['telegram_chat_id' => '77', 'telegram_verified_at' => now()]);
        $specific = $this->portalUser('admin', 'active', ['telegram_chat_id' => '88', 'telegram_verified_at' => now()]);
        DB::table('monitoring_settings')->insert(['id' => 1, 'recipient_ids' => json_encode([$global->id])]);
        $site = $this->site(['monitoring_recipient_ids' => [$specific->id]]);
        $incident = DB::table('monitoring_incidents')->insertGetId(['site_id' => $site->id, 'opened_at' => now()]);
        $notifications = app(MonitoringNotifications::class);
        $notifications->enqueue($incident, 'down');
        $this->assertDatabaseHas('monitoring_notifications', ['user_id' => $specific->id]);
        $this->assertDatabaseMissing('monitoring_notifications', ['user_id' => $global->id]);
        $site->update(['monitoring_recipient_ids' => null]);
        $notifications->deliver();
        $this->assertSame(1, DB::table('monitoring_notifications')->whereNotNull('cancelled_at')->count());
        Http::assertNothingSent();
    }

    public function test_empty_history_is_not_100_percent_and_report_pages_render(): void
    {
        $site = $this->site();
        $this->at('00:05:00');
        $total = $this->report($site)['total'];
        $this->assertNull($total['uptime']);
        $this->assertEquals(0, $total['coverage']);
        $this->assertSame(300, $total['unknown']);
        $this->actingAs($this->portalUser('pm'))->get('/portal/monitoring?period=today')->assertOk()->assertSee('Немає даних');
        app(SiteMonitor::class)->check($site);
        $this->at('00:10:00');
        $this->get('/portal/monitoring/sites/'.$site->id.'?period=today')->assertOk()
            ->assertSee('Покриття перевірками')->assertSee('Середня відповідь')->assertSee('Налаштування цього сайту');
        $this->get('/portal/monitoring?period=custom&from=2026-09-20&to=2026-09-19')->assertSessionHasErrors('to');
    }

    public function test_dst_day_has_23_hours_and_histograms_merge_by_sample_count(): void
    {
        $site = $this->site(['monitoring_enabled' => false]);
        $this->travelTo(Carbon::parse('2026-03-30 12:00:00', 'UTC'));
        DB::table('monitoring_periods')->insert(['site_id' => $site->id, 'started_at' => '2026-03-28 22:00:00', 'ended_at' => '2026-03-29 21:00:00']);
        DB::table('monitoring_spans')->insert(['site_id' => $site->id, 'started_at' => '2026-03-28 22:00:00', 'ended_at' => '2026-03-29 21:00:00', 'availability' => 'up']);
        DB::table('monitoring_metrics')->insert([
            ['site_id' => $site->id, 'day' => '2026-03-29', 'samples' => 99, 'response_sum' => 9900, 'response_max' => 100, 'histogram' => '{"100":99}'],
            ['site_id' => $site->id, 'day' => '2026-03-30', 'samples' => 1, 'response_sum' => 1000, 'response_max' => 1000, 'histogram' => '{"1000":1}'],
        ]);
        $service = app(MonitoringReport::class);
        $range = $service->range(Request::create('/', 'GET', ['period' => 'custom', 'from' => '2026-03-29', 'to' => '2026-03-29', 'group' => 'week']), [$site->id]);
        $report = $service->build([$site->id], $range);
        $this->assertSame(23 * 3600, $report['total']['expected']);
        $this->assertSame(99, $report['total']['samples']); // next day must not leak into the same weekly bucket
        $range['to'] = CarbonImmutable::parse('2026-03-31', 'Europe/Kyiv');
        $combined = $service->build([$site->id], $range);
        $this->assertSame('≤ 100 мс', $combined['total']['p95']);
        $this->assertSame(109, $combined['total']['average']);
    }

    public function test_migration_preserves_sites_tokens_modes_and_does_not_invent_old_time(): void
    {
        $migration = require database_path('migrations/2026_09_19_000001_extend_site_monitoring.php');
        $migration->down();
        $id = DB::table('sites')->insertGetId([
            'name' => 'Existing', 'url' => 'https://example.com', 'environment' => 'prod',
            'site_type' => 'site', 'is_active' => true, 'remote_control_enabled' => true,
            'api_token' => 'existing-ciphertext', 'control_version' => 7,
        ]);
        $dev = DB::table('sites')->insertGetId(['name' => 'Dev', 'url' => 'https://example.com', 'environment' => 'dev']);
        config(['monitoring.interval_minutes' => 12]);
        $migration->up();
        $this->assertDatabaseHas('sites', ['id' => $id, 'monitoring_interval' => 12]);
        $this->assertDatabaseHas('sites', ['id' => $dev, 'monitoring_interval' => 12]);
        $this->assertDatabaseHas('sites', ['id' => $id, 'monitoring_enabled' => true, 'api_token' => 'existing-ciphertext', 'remote_control_enabled' => true, 'control_version' => 7]);
        $this->assertDatabaseHas('sites', ['id' => $dev, 'monitoring_enabled' => false]);
        $this->assertDatabaseCount('monitoring_periods', 1);
        $this->assertDatabaseCount('monitoring_spans', 0);
        $this->assertDatabaseCount('monitoring_metrics', 0);
    }

    public function test_pause_and_resume_during_request_invalidates_the_old_observation(): void
    {
        $site = $this->site();
        $this->fake(function () use ($site) {
            $service = app(SiteMetadataService::class);
            $paused = $service->update($site, ['monitoring_enabled' => false]);
            $service->update($paused, ['monitoring_enabled' => true]);

            return Http::response('OK');
        });
        $this->expectException(\App\Modules\Monitoring\Services\CheckAlreadyRunning::class);
        try {
            app(SiteMonitor::class)->check($site);
        } finally {
            $this->assertDatabaseCount('monitoring_checks', 0);
            $this->assertSame(2, $site->fresh()->monitoring_revision);
        }
    }

    public function test_stale_success_is_not_listed_as_currently_available(): void
    {
        $site = $this->site();
        app(SiteMonitor::class)->check($site);
        $this->at('01:00:00');
        $this->actingAs($this->portalUser('pm'))->get('/portal/monitoring?availability=up')
            ->assertOk()->assertSee('Сайтів за цими фільтрами немає.');
        $this->get('/portal/monitoring?availability=unknown')->assertOk()->assertSee($site->name);
        $this->get('/portal/monitoring/sites/'.$site->id)->assertOk()->assertSee('Немає свіжих автоматичних даних');
    }

    public function test_site_settings_validate_limits_and_attach_audit_to_site(): void
    {
        $site = $this->site();
        $this->actingAs($this->portalUser('pm'));
        $url = '/portal/monitoring/sites/'.$site->id.'/settings';
        $this->put($url, ['monitoring_timeout' => 100, 'monitoring_interval' => 0, 'monitoring_status_codes' => '200,broken'])
            ->assertSessionHasErrors(['monitoring_timeout', 'monitoring_interval']);
        $this->put($url, ['monitoring_status_codes' => '200,broken'])->assertSessionHasErrors('monitoring_status_codes');
        $this->put($url, ['monitoring_interval' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('activity_logs', ['action' => 'monitoring.site_settings_updated',
            'subject_type' => \App\Modules\Site\Models\Site::class, 'subject_id' => $site->id]);
    }

    public function test_partial_migration_can_resume_without_resetting_existing_settings_or_history(): void
    {
        $site = $this->site(['monitoring_interval' => 17]);
        $site->forceFill(['control_version' => 9])->save();
        $key = $site->api_token;
        app(SiteMonitor::class)->check($site, true);
        DB::table('sites')->where('id', $site->id)->update(['monitoring_enabled' => false]);
        $period = (array) DB::table('monitoring_periods')->first();
        $check = (array) DB::table('monitoring_checks')->first();
        \Illuminate\Support\Facades\Schema::drop('monitoring_spans');
        \Illuminate\Support\Facades\Schema::drop('monitoring_metrics');
        \Illuminate\Support\Facades\Schema::table('monitoring_states', fn ($t) => $t->dropColumn('first_failed_at'));
        config(['monitoring.interval_minutes' => 3]);
        $migration = require database_path('migrations/2026_09_19_000001_extend_site_monitoring.php');
        $migration->up();
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('monitoring_spans'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('monitoring_metrics'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('monitoring_states', 'first_failed_at'));
        $this->assertDatabaseHas('sites', ['id' => $site->id, 'monitoring_enabled' => false,
            'monitoring_interval' => 17, 'api_token' => $key, 'control_version' => 9]);
        $this->assertDatabaseCount('monitoring_periods', 1);
        $this->assertSame($period, (array) DB::table('monitoring_periods')->first());
        $this->assertSame($check, (array) DB::table('monitoring_checks')->first());
    }
}
