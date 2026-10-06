<?php

// Explicitly isolated local test endpoint. Never reads the application's DB credentials.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Modules\Monitoring\Services\MonitoringInstaller;
use App\Modules\Monitoring\Services\MonitoringTime;
use App\Modules\Monitoring\Services\MonitorManager;
use App\Modules\Monitoring\Services\MonitorRunner;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

config(['app.env' => 'testing', 'app.key' => 'base64:MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=', 'database.default' => 'v2_isolated', 'database.connections.v2_isolated' => [
    'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33927, 'database' => 'monitoring_v2_isolated_test', 'username' => 'root', 'password' => '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true,
]]);
DB::purge('v2_isolated');
if (($argv[1] ?? '') === '--claim') {
    echo app(MonitorRunner::class)->claim((int) $argv[2]) ? 'claimed' : 'busy';
    exit;
}
if (($argv[1] ?? '') === '--race') {
    $id = (int) DB::table('monitoring_monitors')->value('id');
    DB::table('monitoring_states')->where('monitor_id', $id)->update(['claim_token' => null, 'lease_until' => null, 'next_due_at' => MonitoringTime::store(MonitoringTime::now())]);
    $jobs = [];
    for ($i = 0; $i < 2; $i++) {
        $pipes = [];
        $process = proc_open([PHP_BINARY, __FILE__, '--claim', (string) $id], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $jobs[] = [$process, $pipes];
    }
    $results = [];
    foreach ($jobs as [$process,$pipes]) {
        fclose($pipes[0]);
        $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0) {
            throw new RuntimeException($error);
        }
    }
    sort($results);
    if ($results !== ['busy', 'claimed']) {
        throw new RuntimeException('Concurrent claims were not exclusive: '.json_encode($results));
    }
    echo 'MySQL 5.7 two-process concurrent claims: PASS'.PHP_EOL;
    exit;
}
function verify(bool $ok, string $message): void
{
    if (! $ok) {
        throw new RuntimeException($message);
    }
}
verify(! Schema::hasTable('sites'), 'Refusing nonempty test database. Create a fresh isolated database first.');
foreach (glob(database_path('migrations/*.php')) as $file) {
    if (! str_contains($file, '2026_10_05')) {
        (require $file)->up();
    }
}
$company = DB::table('companies')->insertGetId(['name' => 'Isolated test']);
$site = DB::table('sites')->insertGetId(['name' => 'Existing site', 'url' => 'https://example.com', 'company_id' => $company, 'admin_password' => Crypt::encryptString('dummy'), 'api_token' => Crypt::encryptString('dummy-token'),
    'monitoring_enabled' => true, 'monitoring_interval' => 17, 'monitoring_url' => 'https://example.com/health', 'is_active' => false, 'control_version' => 3, 'confirmed_control_version' => 3, 'confirmed_state' => 'disabled']);
$before = (array) DB::table('sites')->where('id', $site)->first();
$before = array_diff_key($before, array_flip(MonitoringInstaller::LEGACY_COLUMNS));
$installer = app(MonitoringInstaller::class);
try {
    $installer->install(function ($stage) {
        if ($stage === 'schema') {
            throw new RuntimeException('partial-DDL');
        }
    });
} catch (RuntimeException $e) {
    verify($e->getMessage() === 'partial-DDL', 'Unexpected migration error');
}
// Simulate one CREATE lost during partial DDL, without touching business tables.
Schema::drop('monitoring_certificates');
try {
    $installer->install(function ($stage) {
        if ($stage === 'initialized') {
            throw new RuntimeException('partial-Site-DROP');
        }
    });
} catch (RuntimeException $e) {
    verify($e->getMessage() === 'partial-Site-DROP', 'Unexpected initialization error');
}
$installer->install();
$installer->install();
verify($before === (array) DB::table('sites')->where('id', $site)->first(), 'Business columns changed');
verify(Crypt::decryptString(DB::table('sites')->where('id', $site)->value('admin_password')) === 'dummy', 'Credentials changed');
$m = DB::table('monitoring_monitors')->first();
verify($m->custom_interval_seconds === null, 'Legacy interval imported');
verify(DB::table('monitoring_states')->value('availability') === 'unknown', 'Wrong initial state');
if (($argv[1] ?? '') === '--upgrade-only') {
    verify(DB::selectOne('SELECT @@session.time_zone AS zone')->zone === '+00:00', 'Non-UTC session');
    echo 'MySQL '.DB::selectOne('SELECT VERSION() AS version')->version.': final cutover + mapping verification + partial DDL retry + business/credentials preservation: PASS'.PHP_EOL;
    exit;
}
date_default_timezone_set('America/Los_Angeles');
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 20:20:00.123456', 'UTC'));
// Avoid planned policy in this HTTP storage exercise; this is Monitoring config only.
$config = json_decode($m->config, true);
$config['respect_site_control'] = false;
DB::table('monitoring_monitors')->where('id', $m->id)->update(['config' => json_encode($config)]);
app(MonitorManager::class)->reset($m->id, 'test_activation');
$runner = app(MonitorRunner::class);
for ($i = 0; $i < 1000; $i++) {
    CarbonImmutable::setTestNow(MonitoringTime::parse(DB::table('monitoring_states')->value('next_due_at')));
    $claim = $runner->claim($m->id);
    verify($claim !== null, 'Claim failed');
    verify($runner->commit($claim, ['up' => true, 'response_ms' => 120, 'http_status' => 200, 'error_kind' => null, 'certificate' => null]), 'Commit failed');
}
verify(DB::table('monitoring_diagnostics')->count() === 0, 'Raw success stream');
verify(DB::table('monitoring_spans')->count() <= 3, 'Uncompressed spans');
verify((int) DB::table('monitoring_rollups')->sum('sample_count') === 1000, 'Lost samples');
verify(DB::selectOne('SELECT @@session.time_zone AS zone')->zone === '+00:00', 'Non-UTC session');
$columns = DB::select("SELECT DATA_TYPE, DATETIME_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='monitoring_v2_isolated_test' AND TABLE_NAME='monitoring_states' AND COLUMN_NAME='last_observed_at'");
verify($columns[0]->DATA_TYPE === 'datetime' && (int) $columns[0]->DATETIME_PRECISION === 6, 'Wrong timestamp type');
echo 'MySQL '.DB::selectOne('SELECT VERSION() AS version')->version.': cutover, partial DDL retry, business preservation, UTC DATETIME(6), 1000 successes: PASS'.PHP_EOL;
