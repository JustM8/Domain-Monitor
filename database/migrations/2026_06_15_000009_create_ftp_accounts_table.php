<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ftp_accounts', function (Blueprint $table) {
            $table->id();
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            } else {
                $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            }
            $table->string('host');
            $table->unsignedInteger('port')->default(21);
            $table->string('login')->nullable();
            $table->text('password')->nullable();
            $table->string('path')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ftp_accounts');
    }
};
