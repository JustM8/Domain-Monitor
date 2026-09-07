<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->string('admin_url')->nullable();
            $table->string('admin_login')->nullable();
            $table->text('admin_password')->nullable();
            $table->foreignId('status_id')->nullable()->constrained('statuses')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->enum('project_type', ['prod', 'dev', 'stage'])->default('prod');
            $table->string('repo_url')->nullable();
            $table->string('branch')->nullable();
            $table->string('cms')->nullable();
            $table->string('version')->nullable();
            $table->boolean('ssl')->default(false);
            $table->timestamp('last_backup_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('disabled_reason')->nullable();
            $table->foreignId('disabled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('disabled_at')->nullable();
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
        Schema::dropIfExists('sites');
    }
};
