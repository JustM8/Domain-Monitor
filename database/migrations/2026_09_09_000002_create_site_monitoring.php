<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_states', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('availability', 16)->default('unknown');
            $t->unsignedInteger('consecutive_failures')->default(0);
            $t->timestamp('last_checked_at')->nullable();
            $t->timestamp('next_check_at')->nullable()->index();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedInteger('response_ms')->nullable();
            $t->string('error')->nullable();
            $t->timestamps();
        });
        Schema::create('monitoring_checks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->string('availability', 16);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->unsignedInteger('response_ms')->nullable();
            $t->string('error')->nullable();
            $t->boolean('manual')->default(false);
            $t->timestamp('checked_at')->index();
            $t->index(['site_id', 'checked_at']);
        });
        Schema::create('monitoring_daily', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->date('day');
            $t->unsignedInteger('checks')->default(0);
            $t->unsignedInteger('successful')->default(0);
            $t->unsignedInteger('planned')->default(0);
            $t->unique(['site_id', 'day']);
        });
        Schema::create('monitoring_incidents', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->timestamp('opened_at');
            $t->timestamp('closed_at')->nullable();
            $t->string('close_reason')->nullable();
            $t->string('error')->nullable();
            $t->index(['site_id', 'closed_at']);
        });
        Schema::create('monitoring_runs', function (Blueprint $t) {
            $t->id();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->string('status', 16)->default('running');
            $t->unsignedInteger('checked')->default(0);
            $t->string('error')->nullable();
        });
        Schema::create('monitoring_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->json('recipient_ids')->nullable();
            $t->timestamps();
        });
        Schema::create('monitoring_notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('incident_id')->constrained('monitoring_incidents')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('event', 16);
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('next_attempt_at')->nullable()->index();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->timestamps();
            $t->unique(['incident_id', 'user_id', 'event'], 'monitoring_notification_event_unique');
        });
    }

    public function down(): void
    {
        foreach (['monitoring_notifications', 'monitoring_settings', 'monitoring_runs', 'monitoring_incidents', 'monitoring_daily', 'monitoring_checks', 'monitoring_states'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
