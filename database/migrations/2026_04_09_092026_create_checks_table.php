<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();

            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();

            $table->timestamp('checked_at');

            $table->integer('status_code')->nullable(); // 200, 500 і т.д.
            $table->float('response_time')->nullable(); // сек

            $table->boolean('is_success')->default(false);
            $table->text('error')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
