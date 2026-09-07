<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            if (! Schema::hasColumn('statuses', 'code')) {
                $table->string('code', 64)->nullable()->unique()->after('id');
            }
        });

        $defaults = [
            1 => 'can_delete',
            2 => 'prod',
            3 => 'in_progress',
            4 => 'blocked',
            5 => 'backup',
            6 => 'partner',
        ];

        \App\Modules\Shared\Models\Status::query()
            ->whereNull('code')
            ->get()
            ->each(function (\App\Modules\Shared\Models\Status $status) use ($defaults) {
                $code = $defaults[(int) $status->sort_order] ?? null;

                if (filled($code)) {
                    $status->forceFill(['code' => $code])->save();
                }
            });
    }

    public function down(): void
    {
        Schema::table('statuses', function (Blueprint $table) {
            if (Schema::hasColumn('statuses', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            }
        });
    }
};
