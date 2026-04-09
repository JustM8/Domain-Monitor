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
        Schema::create('domains', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('domain'); // example.com
            $table->boolean('is_active')->default(true);

            // налаштування перевірок (мінімум)
            $table->integer('check_interval')->default(60); // сек
            $table->integer('timeout')->default(5); // сек
            $table->enum('method', ['GET', 'HEAD'])->default('GET');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
