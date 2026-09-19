<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_webhook_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('bot', 32);
            $t->unsignedBigInteger('update_id');
            $t->timestamp('processed_at')->nullable();
            $t->unique(['bot', 'update_id']);
        });
        // Deliberate removal of the retired list. Back up the database before deployment.
        Schema::dropIfExists('checks');
        Schema::dropIfExists('domains');
        Schema::dropIfExists('settings');
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_webhook_receipts');
        foreach (['2026_04_09_091943_create_domains_table.php', '2026_04_09_092026_create_checks_table.php', '2026_04_09_104640_add_last_checked_at_to_domains_table.php', '2026_04_09_110334_create_settings_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        // Schema can be restored; deleted legacy rows require the pre-deployment backup.
    }
};
