<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->unique()->after('last_login_at');
            $table->string('telegram_username')->nullable()->after('telegram_chat_id');
            $table->string('telegram_link_token', 80)->nullable()->unique()->after('telegram_username');
            $table->timestamp('telegram_link_requested_at')->nullable()->after('telegram_link_token');
            $table->timestamp('telegram_link_expires_at')->nullable()->after('telegram_link_requested_at');
            $table->timestamp('telegram_verified_at')->nullable()->after('telegram_link_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id']);
            $table->dropUnique(['telegram_link_token']);
            $table->dropColumn([
                'telegram_chat_id',
                'telegram_username',
                'telegram_link_token',
                'telegram_link_requested_at',
                'telegram_link_expires_at',
                'telegram_verified_at',
            ]);
        });
    }
};
