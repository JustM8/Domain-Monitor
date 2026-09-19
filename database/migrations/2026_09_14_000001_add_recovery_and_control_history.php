<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_webhook_receipts', function (Blueprint $t) {
            $t->string('status', 24)->default('pending')->index();
            $t->timestamp('started_at')->nullable();
            $t->longText('payload')->nullable();
        });
        Schema::create('site_control_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('site_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('version');
            $t->string('desired_state', 16);
            $t->string('status', 24)->default('pending');
            $t->text('message')->nullable();
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->index(['site_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_control_attempts');
        Schema::table('telegram_webhook_receipts', fn (Blueprint $t) => $t->dropIndex(['status']));
        Schema::table('telegram_webhook_receipts', fn (Blueprint $t) => $t->dropColumn(['status', 'started_at', 'payload']));
    }
};
