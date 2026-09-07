<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_topics', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('assigned_role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_client_id')->constrained('support_clients')->cascadeOnDelete();
            $table->foreignId('support_topic_id')->constrained('support_topics')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('number')->unique();
            $table->string('title')->nullable();
            $table->string('status')->default('open')->index();
            $table->string('assigned_role')->nullable()->index();
            $table->unsignedBigInteger('telegram_thread_id')->nullable()->unique();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable()->index();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('closed_note')->nullable();
            $table->timestamps();

            $table->unique(['support_client_id', 'support_topic_id']);
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('support_session_id')
                ->nullable()
                ->after('support_client_id')
                ->constrained('support_sessions')
                ->cascadeOnDelete();

            $table->unsignedInteger('number_in_session')
                ->nullable()
                ->after('number')
                ->index();
        });

        Schema::table('support_clients', function (Blueprint $table) {
            $table->foreignId('current_support_session_id')
                ->nullable()
                ->after('state')
                ->constrained('support_sessions')
                ->nullOnDelete();

            $table->foreignId('current_support_ticket_id')
                ->nullable()
                ->after('current_support_session_id')
                ->constrained('support_tickets')
                ->nullOnDelete();
        });

        DB::table('support_topics')->insert(array_map(function (array $row) {
            $now = now();

            return $row + [
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, \App\Modules\TelegramSupport\Models\SupportTopic::seedCatalog()));
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_session_id');
            $table->dropColumn('number_in_session');
        });

        Schema::table('support_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_support_ticket_id');
            $table->dropConstrainedForeignId('current_support_session_id');
        });

        Schema::dropIfExists('support_sessions');
        Schema::dropIfExists('support_topics');
    }
};
