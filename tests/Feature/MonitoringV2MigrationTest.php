<?php

namespace Tests\Feature;

use App\Modules\Monitoring\Services\MonitoringInstaller;
use App\Modules\Monitoring\Services\MonitorManager;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class MonitoringV2MigrationTest extends TestCase
{
    use CreatesPortalRecords;

    public function test_existing_business_rows_survive_cutover_and_partial_ddl_retry(): void
    {
        config(['database.connections.v2_upgrade' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        $old = DB::getDefaultConnection();
        DB::setDefaultConnection('v2_upgrade');
        try {
            foreach (glob(database_path('migrations/*.php')) as $path) {
                if (! str_contains($path, '2026_10_05')) {
                    (require $path)->up();
                }
            }
            $company = \App\Modules\Shared\Models\Company::create(['name' => 'Existing company']);
            $u = $this->portalUser();
            DB::table('monitoring_settings')->insert(['id' => 1, 'recipient_ids' => json_encode([$u->id])]);
            $site = Site::withoutEvents(fn () => $this->site(['company_id' => $company->id, 'admin_password' => Crypt::encryptString('existing-password'), 'is_active' => false]));
            $disabled = Site::withoutEvents(fn () => $this->site(['name' => 'Disabled']));
            $ftp = \App\Modules\Ftp\Models\FtpAccount::create(['company_id' => $company->id, 'host' => 'ftp.example.com', 'password' => Crypt::encryptString('ftp-secret')]);
            $site->ftpAccounts()->attach($ftp);
            $hosting = \App\Modules\Hosting\Models\Hosting::create(['name' => 'Host']);
            $account = \App\Modules\Hosting\Models\HostingAccount::create(['hosting_id' => $hosting->id, 'company_id' => $company->id, 'title' => 'Account', 'password' => Crypt::encryptString('hosting-secret')]);
            $site->hostingAccounts()->attach($account);
            DB::table('site_control_attempts')->insert(['site_id' => $site->id, 'version' => 4, 'desired_state' => 'disabled', 'status' => 'confirmed', 'started_at' => '2026-01-01 00:00:00']);
            DB::table('sites')->where('id', $site->id)->update(['monitoring_enabled' => true, 'monitoring_url' => 'https://example.com/health', 'monitoring_status_codes' => '[200,204]', 'monitoring_content' => 'OK', 'monitoring_recipient_ids' => json_encode([$u->id]), 'monitoring_interval' => 17, 'control_version' => 4, 'confirmed_control_version' => 4, 'confirmed_state' => 'disabled']);
            DB::table('sites')->where('id', $disabled->id)->update(['monitoring_enabled' => false]);
            $tables = ['users', 'companies', 'ftp_accounts', 'hostings', 'hosting_accounts', 'site_ftp_accounts', 'site_hosting', 'support_tickets', 'support_messages', 'support_clients', 'support_sessions', 'site_control_attempts', 'site_revisions', 'activity_logs'];
            $baseline = [];
            foreach ($tables as $t) {
                $baseline[$t] = json_encode(DB::table($t)->get());
            }
            $business = fn () => DB::table('sites')->orderBy('id')->get()->map(fn ($r) => array_diff_key((array) $r, array_flip(MonitoringInstaller::LEGACY_COLUMNS)))->all();
            $sitesBefore = $business();
            $installer = app(MonitoringInstaller::class);
            try {
                $installer->install(function ($stage) {
                    if ($stage === 'schema') {
                        throw new \RuntimeException('simulated partial DDL');
                    }
                });
                $this->fail('Expected interruption');
            } catch (\RuntimeException $e) {
                $this->assertSame('simulated partial DDL', $e->getMessage());
            }
            $this->assertSame('schema', DB::table('monitoring_v2_install')->value('stage'));
            $this->assertSame(2, DB::table('monitoring_v2_manifest')->count());
            try {
                $installer->install(function ($stage) {
                    if ($stage === 'initialized') {
                        throw new \RuntimeException('simulated before Site DROP');
                    }
                });
                $this->fail('Expected interruption');
            } catch (\RuntimeException $e) {
                $this->assertSame('simulated before Site DROP', $e->getMessage());
            }
            $installer->install();
            $installer->install();
            $this->assertSame($sitesBefore, $business());
            foreach ($tables as $t) {
                $this->assertSame($baseline[$t], json_encode(DB::table($t)->get()), $t);
            }
            $this->assertSame('existing-password', $site->fresh()->decryptedAdminPassword());
            $this->assertSame('ftp-secret', Crypt::decryptString($ftp->fresh()->password));
            $this->assertSame('hosting-secret', Crypt::decryptString($account->fresh()->password));
            $this->assertSame(2, DB::table('monitoring_monitors')->count());
            $this->assertSame(1, DB::table('monitoring_periods')->count());
            $m = app(MonitorManager::class)->primary($site);
            $this->assertNull($m->custom_interval_seconds);
            $this->assertSame('https://example.com/health', json_decode($m->config, true)['url']);
            $this->assertSame([$u->id], DB::table('monitoring_monitor_recipients')->pluck('user_id')->all());
            $this->assertSame([$u->id], DB::table('monitoring_default_recipients')->pluck('user_id')->all());
            $this->assertSame(['unknown'], DB::table('monitoring_states')->distinct()->pluck('availability')->all());
            $this->assertSame(0, DB::table('monitoring_events')->count());
            foreach (MonitoringInstaller::LEGACY_COLUMNS as $c) {
                $this->assertFalse(Schema::hasColumn('sites', $c));
            }
        } finally {
            DB::setDefaultConnection($old);
            DB::purge('v2_upgrade');
        }
    }
}
