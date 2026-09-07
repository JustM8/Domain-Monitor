<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_clients', function (Blueprint $table) {
            if (! Schema::hasColumn('support_clients', 'pending_support_ticket_id')) {
                $table->foreignId('pending_support_ticket_id')
                    ->nullable()
                    ->after('current_support_ticket_id')
                    ->constrained('support_tickets')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_clients', function (Blueprint $table) {
            if (Schema::hasColumn('support_clients', 'pending_support_ticket_id')) {
                $table->dropConstrainedForeignId('pending_support_ticket_id');
            }
        });
    }
};
