<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_hosting', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hosting_account_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_main')->default(false);
            $table->timestamps();
            $table->unique(['site_id', 'hosting_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_hosting');
    }
};
