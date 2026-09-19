<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_ftp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ftp_account_id')->constrained('ftp_accounts')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['site_id', 'ftp_account_id']);
        });

        Schema::table('ftp_accounts', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('site_id')->constrained('companies')->nullOnDelete();
            $table->boolean('requires_ip_access')->default(false)->after('path');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ftp_accounts MODIFY site_id BIGINT UNSIGNED NULL');
            Schema::table('ftp_accounts', function (Blueprint $table) {
                $table->dropForeign(['site_id']);
                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
            });
        }

        DB::table('ftp_accounts')
            ->select('id', 'site_id')
            ->whereNotNull('site_id')
            ->orderBy('id')
            ->get()
            ->each(function ($record) {
                DB::table('site_ftp_accounts')->insert([
                    'site_id' => $record->site_id,
                    'ftp_account_id' => $record->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('ftp_accounts', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['site_id']);
                $table->dropConstrainedForeignId('company_id');
            } else {
                $table->dropColumn('company_id');
            }
            $table->dropColumn('requires_ip_access');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE ftp_accounts MODIFY site_id BIGINT UNSIGNED NOT NULL');
            Schema::table('ftp_accounts', function (Blueprint $table) {
                $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            });
        }

        Schema::dropIfExists('site_ftp_accounts');
    }
};
