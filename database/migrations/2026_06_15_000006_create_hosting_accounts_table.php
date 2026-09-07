<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hosting_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hosting_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('login')->nullable();
            $table->text('password')->nullable();
            $table->string('ssh_host')->nullable();
            $table->unsignedInteger('ssh_port')->nullable();
            $table->string('ssh_login')->nullable();
            $table->text('ssh_password')->nullable();
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
        Schema::dropIfExists('hosting_accounts');
    }
};
