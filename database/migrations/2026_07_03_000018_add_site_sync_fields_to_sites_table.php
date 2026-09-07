<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('last_backup_at');
            $table->string('last_sync_status')->nullable()->after('last_synced_at');
            $table->text('last_sync_error')->nullable()->after('last_sync_status');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['last_synced_at', 'last_sync_status', 'last_sync_error']);
        });
    }
};
