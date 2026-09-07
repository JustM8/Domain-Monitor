<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->boolean('remote_control_enabled')->default(false)->after('last_sync_error');
        });

        DB::table('sites')->update([
            'remote_control_enabled' => false,
        ]);
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn('remote_control_enabled');
        });
    }
};
