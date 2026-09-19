<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(['name' => 'manager'], ['label' => 'Менеджер', 'sort_order' => 4, 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_status', 16)->default('active')->index();
            $table->timestamp('telegram_access_revoked_at')->nullable();
        });
        // Existing inactive accounts cannot be reliably distinguished from blocked accounts.
        DB::table('users')->where('is_active', false)->update(['approval_status' => 'blocked']);
        DB::table('users')->update(['telegram_link_token' => null, 'telegram_link_expires_at' => null]);
        // Preserve the existing bootstrap administrator without changing its password.
        $adminRole = DB::table('roles')->where('name', 'admin')->value('id');
        if ($adminRole) {
            DB::table('users')->where('email', 'admin@admin.com')->whereNull('role_id')->update(['role_id' => $adminRole]);
        }
        Schema::table('sites', function (Blueprint $table) {
            $table->string('display_mode', 16)->default('standalone')->index();
            $table->json('embed_origins')->nullable();
            $table->unsignedBigInteger('control_version')->default(0);
            $table->unsignedBigInteger('confirmed_control_version')->nullable();
            $table->string('confirmed_state', 16)->nullable();
        });
        Schema::table('support_messages', function (Blueprint $table) {
            $table->string('delivery_status', 16)->default('sent')->index();
            $table->text('delivery_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', fn (Blueprint $table) => $table->dropIndex(['delivery_status']));
        Schema::table('sites', fn (Blueprint $table) => $table->dropIndex(['display_mode']));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['approval_status']));
        Schema::table('support_messages', fn (Blueprint $table) => $table->dropColumn(['delivery_status', 'delivery_error', 'delivered_at']));
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn(['display_mode', 'embed_origins', 'control_version', 'confirmed_control_version', 'confirmed_state']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['approval_status', 'telegram_access_revoked_at']));
    }
};
