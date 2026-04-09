<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->timestamp('last_checked_at')->nullable()->after('check_interval');
        });
    }

    public function down()
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('last_checked_at');
        });
    }
};
