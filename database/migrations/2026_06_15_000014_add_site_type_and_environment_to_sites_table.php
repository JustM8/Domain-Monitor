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
            $table->enum('site_type', ['site', '3d', 'devbase'])->default('site')->after('url');
            $table->enum('environment', ['prod', 'dev'])->default('dev')->after('site_type');
        });

        DB::table('sites')->update([
            'site_type' => 'site',
        ]);

        DB::statement("UPDATE sites SET environment = CASE WHEN project_type = 'prod' THEN 'prod' ELSE 'dev' END");
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['site_type', 'environment']);
        });
    }
};
