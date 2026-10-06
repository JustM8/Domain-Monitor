<?php

namespace App\Modules\Monitoring\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MonitoringInstaller
{
    public const LEGACY_TABLES = ['monitoring_notifications', 'monitoring_checks', 'monitoring_daily', 'monitoring_metrics', 'monitoring_periods', 'monitoring_spans', 'monitoring_states', 'monitoring_incidents', 'monitoring_runs', 'monitoring_settings'];

    public const LEGACY_COLUMNS = ['monitoring_enabled', 'monitoring_interval', 'monitoring_timeout', 'monitoring_failure_threshold', 'monitoring_status_codes', 'monitoring_content', 'monitoring_url', 'monitoring_recipient_ids', 'monitoring_revision'];

    public function install(?callable $checkpoint = null): void
    {
        MonitoringTime::session();
        if (! Schema::hasTable('sites') || ! Schema::hasTable('users')) {
            throw new \RuntimeException('Business schema missing.');
        }
        $this->create('monitoring_v2_install', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->string('stage');
            $t->dateTime('activated_at', 6);
            $t->json('default_recipients')->nullable();
        });
        $this->create('monitoring_v2_manifest', function (Blueprint $t) {
            $t->unsignedBigInteger('site_id')->primary();
            $t->boolean('enabled');
            $t->json('config');
            $t->json('recipients')->nullable();
        });
        if (! DB::table('monitoring_v2_install')->exists()) {
            if (! Schema::hasColumn('sites', 'monitoring_enabled')) {
                throw new \RuntimeException('Legacy intent unavailable; restore manifest before cutover.');
            }
            // Persist the complete manifest and stage BEFORE any nontransactional DDL.
            DB::transaction(function () {
                DB::table('sites')->whereNull('deleted_at')->orderBy('id')->chunkById(200, function ($sites) {
                    foreach ($sites as $s) {
                        DB::table('monitoring_v2_manifest')->insert(['site_id' => $s->id, 'enabled' => $s->monitoring_enabled,
                            'config' => json_encode(['url' => $s->monitoring_url ?: null, 'status_codes' => json_decode($s->monitoring_status_codes ?? 'null', true), 'content' => $s->monitoring_content, 'respect_site_control' => true]),
                            'recipients' => $s->monitoring_recipient_ids]);
                    }
                });
                $defaults = json_decode(DB::table('monitoring_settings')->where('id', 1)->value('recipient_ids') ?? '[]', true) ?: [];
                if (! is_array($defaults) || count($defaults) > 100) {
                    throw new \RuntimeException('Invalid legacy recipients');
                }
                DB::table('monitoring_v2_install')->insert(['id' => 1, 'stage' => 'extracted', 'activated_at' => MonitoringTime::store(MonitoringTime::now()), 'default_recipients' => json_encode($defaults)]);
            });
        }
        $stage = DB::table('monitoring_v2_install')->value('stage');
        if ($checkpoint) {
            $checkpoint($stage);
        }
        if ($stage === 'complete') {
            DB::table('monitoring_v2_manifest')->delete();

            return;
        }
        if (in_array($stage, ['extracted', 'resetting'], true)) {
            DB::table('monitoring_v2_install')->update(['stage' => 'resetting']);
            foreach (self::LEGACY_TABLES as $table) {
                Schema::dropIfExists($table);
            }
            DB::table('monitoring_v2_install')->update(['stage' => 'schema']);
        }
        $this->schema(); // CREATE IF MISSING supports partial MySQL DDL retry.
        if ($checkpoint) {
            $checkpoint('schema');
        }
        if (in_array(DB::table('monitoring_v2_install')->value('stage'), ['schema'], true)) {
            DB::transaction(function () {
                DB::table('monitoring_settings')->insertOrIgnore(['id' => 1]);
                foreach (json_decode(DB::table('monitoring_v2_install')->value('default_recipients') ?? '[]', true) as $uid) {
                    if (DB::table('users')->where('id', $uid)->exists()) {
                        DB::table('monitoring_default_recipients')->insertOrIgnore(['user_id' => $uid]);
                    }
                }
                $at = MonitoringTime::parse(DB::table('monitoring_v2_install')->value('activated_at'));
                DB::table('monitoring_v2_manifest')->orderBy('site_id')->chunk(200, function ($rows) use ($at) {
                    foreach ($rows as $r) {
                        $id = DB::table('monitoring_monitors')->insertGetId(['site_id' => $r->site_id, 'slot' => 'primary_http', 'name' => 'Primary HTTP', 'type' => 'http', 'enabled' => $r->enabled, 'config' => $r->config,
                            'recipient_mode' => $r->recipients === null ? 'inherit' : 'explicit', 'activated_at' => MonitoringTime::store($at)]);
                        foreach (json_decode($r->recipients ?? '[]', true) ?: [] as $uid) {
                            if (DB::table('users')->where('id', $uid)->exists()) {
                                DB::table('monitoring_monitor_recipients')->insertOrIgnore(['monitor_id' => $id, 'user_id' => $uid]);
                            }
                        }
                        app(MonitorManager::class)->initializeState($id, (bool) $r->enabled, $at);
                    }
                });
                if (DB::table('monitoring_monitors')->where('slot', 'primary_http')->count() !== DB::table('monitoring_v2_manifest')->count()) {
                    throw new \RuntimeException('Initialization verification failed.');
                }
                DB::table('monitoring_v2_manifest')->orderBy('site_id')->chunk(200, function ($rows) use ($at) {
                    foreach ($rows as $r) {
                        $m = DB::table('monitoring_monitors')->where('site_id', $r->site_id)->where('slot', 'primary_http')->first();
                        $s = $m ? DB::table('monitoring_states')->where('monitor_id', $m->id)->first() : null;
                        $expectedIds = DB::table('users')->whereIn('id', json_decode($r->recipients ?? '[]', true) ?: [])->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
                        $actualIds = $m ? DB::table('monitoring_monitor_recipients')->where('monitor_id', $m->id)->orderBy('user_id')->pluck('user_id')->map(fn ($id) => (int) $id)->all() : [];
                        if (! $m || ! $s || (bool) $m->enabled !== (bool) $r->enabled || $m->custom_interval_seconds !== null || json_decode($m->config, true) != json_decode($r->config, true)
                            || $s->availability !== 'unknown' || $s->active_incident_id !== null || $expectedIds !== $actualIds || (bool) $s->period_id !== (bool) $r->enabled) {
                            throw new \RuntimeException('Manifest mapping verification failed.');
                        }
                        if ($s->period_id && ! MonitoringTime::parse(DB::table('monitoring_periods')->where('id', $s->period_id)->value('started_at'))->eq($at)) {
                            throw new \RuntimeException('Activation boundary verification failed.');
                        }
                    }
                });
                DB::table('monitoring_v2_install')->update(['stage' => 'initialized']);
            });
        }
        if ($checkpoint) {
            $checkpoint('initialized');
        }
        foreach (self::LEGACY_COLUMNS as $column) {
            if ($column === 'monitoring_enabled' && DB::getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX IF EXISTS sites_monitoring_enabled_index');
            }
            if (Schema::hasColumn('sites', $column)) {
                Schema::table('sites', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
        DB::table('monitoring_v2_install')->update(['stage' => 'complete']);
        DB::table('monitoring_v2_manifest')->delete();
    }

    private function create(string $name, callable $definition): void
    {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $definition);
        }
    }

    private function owned(Blueprint $t): void
    {
        $t->id();
        $t->foreignId('monitor_id')->constrained('monitoring_monitors')->cascadeOnDelete();
    }

    private function schema(): void
    {
        $this->create('monitoring_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->unsignedInteger('checks_revision')->default(1);
            foreach (MonitoringSettings::DEFAULTS as $key => $default) {
                $t->unsignedInteger($key)->default($default);
            }
            $t->json('tcp_allowed_ports')->nullable();
        });
        $this->create('monitoring_default_recipients', function (Blueprint $t) {
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary('user_id');
        });
        $this->create('monitoring_monitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('slot', 32)->nullable();
            $t->unique(['site_id', 'slot']);
            $t->string('name', 255);
            $t->string('type', 16);
            $t->boolean('enabled')->default(true);
            $t->unsignedBigInteger('revision')->default(1);
            foreach (['custom_interval_seconds', 'timeout_seconds', 'failure_threshold', 'recovery_threshold'] as $key) {
                $t->unsignedInteger($key)->nullable();
            }
            $t->string('recipient_mode', 16)->default('inherit');
            $t->json('config');
            $t->dateTime('activated_at', 6);
            $t->index(['enabled', 'type']);
        });
        $this->create('monitoring_monitor_recipients', function (Blueprint $t) {
            $t->foreignId('monitor_id')->constrained('monitoring_monitors')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->primary(['monitor_id', 'user_id']);
        });
        $this->create('monitoring_states', function (Blueprint $t) {
            $t->foreignId('monitor_id')->primary()->constrained('monitoring_monitors')->cascadeOnDelete();
            $t->string('availability', 16)->default('unknown');
            $t->string('phase', 32)->nullable();
            foreach (['generation', 'dispatch_sequence', 'event_sequence'] as $k) {
                $t->unsignedBigInteger($k)->default(0);
            }
            foreach (['failures', 'successes', 'diagnostics_suppressed'] as $k) {
                $t->unsignedInteger($k)->default(0);
            }
            foreach (['next_due_at', 'lease_until', 'last_observed_at', 'valid_until', 'cursor_at', 'first_failed_at', 'last_ping_at', 'next_expected_at', 'deadline_at', 'last_evaluated_at', 'detail_available_from'] as $k) {
                $t->dateTime($k, 6)->nullable();
            }
            $t->string('claim_token', 64)->nullable();
            $t->unsignedBigInteger('captured_revision')->nullable();
            $t->unsignedInteger('captured_checks_revision')->nullable();
            $t->unsignedBigInteger('active_incident_id')->nullable();
            $t->unsignedBigInteger('period_id')->nullable();
            $t->unsignedInteger('response_ms')->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('error_kind', 32)->nullable();
            $t->string('error_signature', 64)->nullable();
            $t->string('last_job_run_id', 128)->nullable();
            $t->json('candidate_evidence')->nullable();
            $t->date('manual_day')->nullable();
            $t->unsignedInteger('manual_attempts')->default(0);
            $t->index('next_due_at');
            $t->index('lease_until');
        });
        $this->create('monitoring_periods', function (Blueprint $t) {
            $this->owned($t);
            $t->unsignedBigInteger('generation');
            $t->dateTime('started_at', 6);
            $t->dateTime('ended_at', 6)->nullable();
            $t->index(['monitor_id', 'started_at']);
        });
        $this->create('monitoring_spans', function (Blueprint $t) {
            $this->owned($t);
            $t->unsignedBigInteger('generation');
            $t->string('availability', 16);
            $t->dateTime('started_at', 6);
            $t->dateTime('ended_at', 6);
            $t->index(['monitor_id', 'ended_at']);
        });
        $this->create('monitoring_rollups', function (Blueprint $t) {
            $this->owned($t);
            $t->string('grain', 8)->default('day');
            $t->string('bucket_key', 10);
            $t->unique(['monitor_id', 'grain', 'bucket_key'], 'monitoring_rollup_bucket');
            foreach (['expected_us', 'up_us', 'down_us', 'unknown_us', 'planned_us', 'sample_count', 'latency_sum'] as $k) {
                $t->unsignedBigInteger($k)->default(0);
            }
            $t->unsignedInteger('latency_min')->nullable();
            $t->unsignedInteger('latency_max')->nullable();
            $t->json('histogram')->nullable();
            $t->boolean('finalized')->default(false);
        });
        $this->create('monitoring_incidents', function (Blueprint $t) {
            $this->owned($t);
            $t->unsignedBigInteger('generation');
            foreach (['started_at', 'detected_at'] as $k) {
                $t->dateTime($k, 6);
            }
            foreach (['recovered_at', 'closed_at'] as $k) {
                $t->dateTime($k, 6)->nullable();
            } $t->string('close_reason', 32)->nullable();
            $t->string('cause', 32);
            $t->json('evidence');
            $t->index(['monitor_id', 'closed_at']);
        });
        $this->create('monitoring_diagnostics', function (Blueprint $t) {
            $this->owned($t);
            $t->string('kind', 16);
            $t->date('day');
            $t->dateTime('recorded_at', 6);
            $t->dateTime('expires_at', 6)->index();
            $t->json('evidence');
            $t->index(['monitor_id', 'day']);
        });
        $this->create('monitoring_events', function (Blueprint $t) {
            $this->owned($t);
            $t->unsignedBigInteger('event_sequence');
            $t->unique(['monitor_id', 'event_sequence']);
            $t->unsignedBigInteger('incident_id')->nullable();
            $t->string('type', 32);
            $t->dateTime('occurred_at', 6);
            $t->json('snapshot');
        });
        $this->create('monitoring_deliveries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('event_id')->constrained('monitoring_events')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unique(['event_id', 'user_id']);
            $t->string('status', 24)->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->string('claim_token', 64)->nullable();
            foreach (['lease_until', 'next_attempt_at', 'sent_at', 'terminal_at'] as $k) {
                $t->dateTime($k, 6)->nullable();
            } $t->index(['status', 'next_attempt_at']);
        });
        $this->create('monitoring_certificates', function (Blueprint $t) {
            $this->owned($t);
            $t->string('target_key', 64);
            $t->unique(['monitor_id', 'target_key']);
            $t->string('fingerprint', 64);
            $t->unsignedInteger('generation')->default(1);
            foreach (['not_before', 'not_after', 'verified_at'] as $k) {
                $t->dateTime($k, 6);
            } $t->boolean('warning_emitted')->default(false);
            $t->boolean('critical_emitted')->default(false);
            $t->string('last_error', 32)->nullable();
        });
        $this->create('monitoring_heartbeat_credentials', function (Blueprint $t) {
            $this->owned($t);
            $t->string('public_token_id', 32)->unique();
            $t->string('secret_hash', 64);
            $t->dateTime('created_at', 6);
            $t->dateTime('expires_at', 6)->nullable();
            $t->dateTime('revoked_at', 6)->nullable();
        });
        $this->create('monitoring_runs', function (Blueprint $t) {
            $t->id();
            $t->string('kind', 16)->default('checks');
            $t->string('status', 24)->default('running');
            $t->dateTime('started_at', 6)->index();
            $t->dateTime('finished_at', 6)->nullable();
            $t->unsignedInteger('checked')->default(0);
            $t->string('error', 255)->nullable();
        });
        foreach (['site' => 'sites', 'company' => 'companies'] as $kind => $parent) {
            $this->create('monitoring_'.$kind.'_preferences', function (Blueprint $t) use ($kind, $parent) {
                $t->foreignId($kind.'_id')->primary()->constrained($parent)->cascadeOnDelete();
                $t->string('display_timezone', 64);
            });
        }
    }
}
