<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL commits DDL immediately. Resume after a failed CREATE without
        // re-adding columns or overwriting settings already migrated/edited.
        $added = $this->addMissing('sites', [
            'monitoring_enabled' => fn (Blueprint $t) => $t->boolean('monitoring_enabled')->default(false)->index(),
            'monitoring_revision' => fn (Blueprint $t) => $t->unsignedBigInteger('monitoring_revision')->default(0),
            'monitoring_interval' => fn (Blueprint $t) => $t->unsignedSmallInteger('monitoring_interval')->default(5),
            'monitoring_timeout' => fn (Blueprint $t) => $t->unsignedSmallInteger('monitoring_timeout')->default(6),
            'monitoring_failure_threshold' => fn (Blueprint $t) => $t->unsignedSmallInteger('monitoring_failure_threshold')->default(2),
            'monitoring_status_codes' => fn (Blueprint $t) => $t->json('monitoring_status_codes')->nullable(),
            'monitoring_url' => fn (Blueprint $t) => $t->string('monitoring_url')->nullable(),
            'monitoring_content' => fn (Blueprint $t) => $t->string('monitoring_content', 255)->nullable(),
            'monitoring_recipient_ids' => fn (Blueprint $t) => $t->json('monitoring_recipient_ids')->nullable(),
        ]);
        if (in_array('monitoring_interval', $added, true)) {
            DB::table('sites')->update([
                'monitoring_interval' => max(1, min(60, (int) config('monitoring.interval_minutes', 5))),
            ]);
        }
        if (in_array('monitoring_enabled', $added, true)) {
            DB::table('sites')->where('environment', 'prod')->update(['monitoring_enabled' => true]);
        }
        $this->addMissing('monitoring_states', [
            'first_failed_at' => fn (Blueprint $t) => $t->timestamp('first_failed_at')->nullable(),
            'coverage_cursor' => fn (Blueprint $t) => $t->timestamp('coverage_cursor')->nullable(),
            'observed_interval' => fn (Blueprint $t) => $t->unsignedSmallInteger('observed_interval')->nullable(),
            'checked_url' => fn (Blueprint $t) => $t->string('checked_url')->nullable(),
            'technical_availability' => fn (Blueprint $t) => $t->string('technical_availability', 16)->nullable(),
        ]);
        $this->addMissing('monitoring_checks', [
            'checked_url' => fn (Blueprint $t) => $t->string('checked_url')->nullable(),
            'technical_availability' => fn (Blueprint $t) => $t->string('technical_availability', 16)->nullable(),
            'error_kind' => fn (Blueprint $t) => $t->string('error_kind', 24)->nullable(),
            'timings' => fn (Blueprint $t) => $t->json('timings')->nullable(),
            'certificate_expires_at' => fn (Blueprint $t) => $t->timestamp('certificate_expires_at')->nullable(),
        ]);
        $this->addMissing('monitoring_incidents', [
            'detected_at' => fn (Blueprint $t) => $t->timestamp('detected_at')->nullable(),
        ]);
        $this->createMissing('monitoring_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->dateTime('started_at');
            $t->dateTime('ended_at')->nullable();
            $t->index(['site_id', 'started_at']);
        });
        $this->createMissing('monitoring_spans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            // DATETIME avoids legacy implicit TIMESTAMP defaults / zero dates
            // and must never acquire ON UPDATE CURRENT_TIMESTAMP.
            $t->dateTime('started_at');
            $t->dateTime('ended_at');
            $t->string('availability', 16);
            $t->string('checked_url')->nullable();
            $t->index(['site_id', 'ended_at']);
        });
        $this->normalizeDates('monitoring_periods', true);
        $this->normalizeDates('monitoring_spans', false);
        $this->createMissing('monitoring_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->unsignedInteger('samples')->default(0);
            $t->unsignedBigInteger('response_sum')->default(0);
            $t->unsignedInteger('response_max')->default(0);
            $t->json('histogram')->nullable();
            $t->unique(['site_id', 'day']);
        });
        DB::table('sites')->where('monitoring_enabled', true)->whereNull('deleted_at')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('monitoring_periods')->whereColumn('monitoring_periods.site_id', 'sites.id'))
            ->orderBy('id')->chunkById(200, function ($sites) {
                foreach ($sites as $site) {
                    DB::table('monitoring_periods')->insert(['site_id' => $site->id, 'started_at' => now()]);
                }
            });
    }

    private function addMissing(string $table, array $definitions): array
    {
        $existing = Schema::getColumnListing($table);
        $missing = array_diff_key($definitions, array_flip($existing));
        if ($missing) {
            Schema::table($table, function (Blueprint $t) use ($missing) {
                foreach ($missing as $define) {
                    $define($t);
                }
            });
        }

        return array_keys($missing);
    }

    private function createMissing(string $table, Closure $definition): void
    {
        if (! Schema::hasTable($table)) {
            Schema::create($table, $definition);
        }
    }

    private function normalizeDates(string $table, bool $nullableEnd): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }
        $quoted = DB::connection()->getQueryGrammar()->wrapTable($table);
        $columns = DB::select('SHOW COLUMNS FROM '.$quoted);
        $hasTimestamp = collect($columns)->contains(fn ($column) => in_array($column->Field, ['started_at', 'ended_at'], true)
            && str_starts_with(strtolower($column->Type), 'timestamp'));
        if ($hasTimestamp) {
            // Repair a periods table left by the old, partially applied migration.
            // MODIFY also removes implicit ON UPDATE from the first TIMESTAMP.
            DB::statement('ALTER TABLE '.$quoted.' MODIFY started_at DATETIME NOT NULL, MODIFY ended_at DATETIME '.($nullableEnd ? 'NULL DEFAULT NULL' : 'NOT NULL'));
        }
    }

    public function down(): void
    {
        foreach (['monitoring_metrics', 'monitoring_spans', 'monitoring_periods'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('monitoring_incidents', fn (Blueprint $t) => $t->dropColumn('detected_at'));
        Schema::table('monitoring_checks', fn (Blueprint $t) => $t->dropColumn([
            'checked_url', 'technical_availability', 'error_kind', 'timings', 'certificate_expires_at',
        ]));
        Schema::table('monitoring_states', fn (Blueprint $t) => $t->dropColumn([
            'first_failed_at', 'coverage_cursor', 'observed_interval', 'checked_url', 'technical_availability',
        ]));
        Schema::table('sites', fn (Blueprint $t) => $t->dropIndex(['monitoring_enabled']));
        Schema::table('sites', fn (Blueprint $t) => $t->dropColumn([
            'monitoring_enabled', 'monitoring_revision', 'monitoring_interval', 'monitoring_timeout',
            'monitoring_failure_threshold', 'monitoring_status_codes', 'monitoring_url',
            'monitoring_content', 'monitoring_recipient_ids',
        ]));
    }
};
