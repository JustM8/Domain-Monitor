<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_clients', function (Blueprint $table) {
            if (! Schema::hasColumn('support_clients', 'company_name')) {
                $table->string('company_name')->nullable()->after('company_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_clients', function (Blueprint $table) {
            if (Schema::hasColumn('support_clients', 'company_name')) {
                $table->dropColumn('company_name');
            }
        });
    }
};
