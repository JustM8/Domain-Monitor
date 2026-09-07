<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('telegram_user_id')->unique();
            $table->string('telegram_chat_id')->unique();
            $table->string('telegram_username')->nullable()->index();
            $table->string('telegram_first_name')->nullable();
            $table->string('telegram_last_name')->nullable();
            $table->string('full_name')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('phone')->nullable()->index();
            $table->string('position')->nullable();
            $table->string('state')->default('await_company')->index();
            $table->json('draft_data')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_clients');
    }
};
