<?php

namespace App\Modules\Monitoring\Console;

use App\Modules\Monitoring\Services\MonitoringInstaller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VerifyMonitoring extends Command
{
    protected $signature = 'monitoring:verify {--save= : Save private business baseline before cutover} {--baseline= : Compare business baseline after cutover}';

    protected $description = 'Read-only business integrity and V2 schema verification; never prints credentials';

    public function handle(): int
    {
        $tables = ['sites', 'users', 'companies', 'ftp_accounts', 'hostings', 'hosting_accounts', 'site_ftp_accounts', 'site_hosting', 'site_revisions', 'site_control_attempts', 'activity_logs', 'support_clients', 'support_tickets', 'support_messages', 'support_sessions', 'telegram_webhook_receipts'];
        $snapshot = [];
        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->error('Missing business table: '.$table);

                return self::FAILURE;
            }
            $columns = Schema::getColumnListing($table);
            if ($table === 'sites') {
                $columns = array_values(array_diff($columns, MonitoringInstaller::LEGACY_COLUMNS));
            }
            $query = DB::table($table)->select($columns);
            foreach (in_array('id', $columns, true) ? ['id'] : $columns as $column) {
                $query->orderBy($column);
            }
            $hash = hash_init('sha256');
            $count = 0;
            foreach ($query->cursor() as $row) {
                hash_update($hash, json_encode((array) $row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
                $count++;
            }
            $snapshot[$table] = ['count' => $count, 'sha256' => hash_final($hash)];
        }
        if ($path = $this->option('save')) {
            if (file_exists($path)) {
                $this->error('Refusing to overwrite baseline.');

                return self::FAILURE;
            }
            if (file_put_contents($path, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
                return self::FAILURE;
            }
            @chmod($path, 0600);
            $this->info('Private baseline saved.');

            return self::SUCCESS;
        }
        if ($path = $this->option('baseline')) {
            $before = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $changed = [];
            foreach ($snapshot as $table => $value) {
                if (($before[$table] ?? null) !== $value) {
                    $changed[] = $table;
                }
            }
            if ($changed) {
                $this->error('Business snapshot differs: '.implode(', ', $changed).'. Investigate legitimate writes before approving cutover.');

                return self::FAILURE;
            }
        }
        if (! Schema::hasTable('monitoring_v2_install') || DB::table('monitoring_v2_install')->value('stage') !== 'complete') {
            $this->error('V2 cutover incomplete.');

            return self::FAILURE;
        }
        if (DB::table('sites')->whereNull('deleted_at')->whereNotExists(fn ($q) => $q->selectRaw('1')->from('monitoring_monitors')->whereColumn('monitoring_monitors.site_id', 'sites.id')->where('slot', 'primary_http'))->exists()) {
            $this->error('Primary Monitor missing.');

            return self::FAILURE;
        }
        if (DB::table('monitoring_monitors')->count() !== DB::table('monitoring_states')->count()) {
            $this->error('State count differs.');

            return self::FAILURE;
        }
        foreach (['sites' => ['admin_password', 'api_token'], 'ftp_accounts' => ['password'], 'hosting_accounts' => ['password']] as $table => $fields) {
            foreach (DB::table($table)->cursor() as $row) {
                foreach ($fields as $field) {
                    if (! empty($row->$field)) {
                        try {
                            Crypt::decryptString($row->$field);
                        } catch (\Throwable) {
                            try {
                                decrypt($row->$field);
                            } catch (\Throwable) {
                                $this->error('Credential decrypt failed: '.$table.' '.$row->id.' '.$field);

                                return self::FAILURE;
                            }
                        }
                    }
                }
            }
        }
        $this->info('Business baseline and V2 initialization verified; credentials decrypt.');

        return self::SUCCESS;
    }
}
