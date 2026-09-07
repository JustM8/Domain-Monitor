<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');

        if ($adminRoleId) {
            DB::table('users')
                ->where('email', 'admin@admin.com')
                ->update([
                    'role_id' => $adminRoleId,
                    'is_active' => true,
                ]);
        }
    }

    public function down(): void
    {
        // no-op
    }
};
