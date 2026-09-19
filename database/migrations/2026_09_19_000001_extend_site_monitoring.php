<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $t) {
            $t->boolean('monitoring_enabled')->default(false)->index();
            $t->unsignedBigInteger('monitoring_revision')->default(0);
            $t->unsignedSmallInteger('monitoring_interval')->default(5);
            $t->unsignedSmallInteger('monitoring_timeout')->default(6);
            $t->unsignedSmallInteger('monitoring_failure_threshold')->default(2);
            $t->json('monitoring_status_codes')->nullable();
            $t->string('monitoring_url')->nullable();
            $t->string('monitoring_content', 255)->nullable();
            $t->json('monitoring_recipient_ids')->nullable();
        });
        DB::table('sites')->update([
            'monitoring_interval' => max(1, min(60, (int) config('monitoring.interval_minutes', 5))),
        ]);
        DB::table('sites')->where('environment', 'prod')->update(['monitoring_enabled' => true]);
        Schema::table('monitoring_states', function (Blueprint $t) {
            $t->timestamp('first_failed_at')->nullable();
            $t->timestamp('coverage_cursor')->nullable();
            $t->unsignedSmallInteger('observed_interval')->nullable();
            $t->string('checked_url')->nullable();
            $t->string('technical_availability', 16)->nullable();
        });
        Schema::table('monitoring_checks', function (Blueprint $t) {
            $t->string('checked_url')->nullable();
            $t->string('technical_availability', 16)->nullable();
            $t->string('error_kind', 24)->nullable();
            $t->json('timings')->nullable();
            $t->timestamp('certificate_expires_at')->nullable();
        });
        Schema::table('monitoring_incidents', function (Blueprint $t) {
            $t->timestamp('detected_at')->nullable();
        });
        Schema::create('monitoring_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('ended_at')->nullable();
            $t->index(['site_id', 'started_at']);
        });
        Schema::create('monitoring_spans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->timestamp('started_at');
            $t->timestamp('ended_at');
            $t->string('availability', 16);
            $t->string('checked_url')->nullable();
            $t->index(['site_id', 'ended_at']);
        });
        Schema::create('monitoring_metrics', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->unsignedInteger('samples')->default(0);
            $t->unsignedBigInteger('response_sum')->default(0);
            $t->unsignedInteger('response_max')->default(0);
            $t->json('histogram')->nullable();
            $t->unique(['site_id', 'day']);
        });
        // Start a new, explicitly dated time series. Old counters cannot recover durations.
        DB::table('sites')->where('monitoring_enabled', true)->whereNull('deleted_at')
            ->orderBy('id')->chunkById(200, function ($sites) {
                foreach ($sites as $site) {
                    DB::table('monitoring_periods')->insert(['site_id' => $site->id, 'started_at' => now()]);
                }
            });
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
