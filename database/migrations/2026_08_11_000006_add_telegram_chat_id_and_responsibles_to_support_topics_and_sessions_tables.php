<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_topics', function (Blueprint $table) {
            if (! Schema::hasColumn('support_topics', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->unique()->after('assigned_role');
            }
        });

        Schema::table('support_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('support_sessions', 'telegram_chat_id')) {
                $table->string('telegram_chat_id')->nullable()->after('assigned_role')->index();
            }
        });

        if (! Schema::hasTable('support_topic_user')) {
            Schema::create('support_topic_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('support_topic_id')->constrained('support_topics')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['support_topic_id', 'user_id']);
            });
        }

        $consultationChatId = (string) config('telegram_support.support_chat_id');
        if (filled($consultationChatId)) {
            DB::table('support_topics')
                ->where('code', 'consultation')
                ->update(['telegram_chat_id' => $consultationChatId]);
        }

        DB::table('support_sessions')
            ->select(['id', 'support_topic_id'])
            ->orderBy('id')
            ->chunkById(200, function ($sessions) {
                foreach ($sessions as $session) {
                    $chatId = DB::table('support_topics')
                        ->where('id', $session->support_topic_id)
                        ->value('telegram_chat_id');

                    if (filled($chatId)) {
                        DB::table('support_sessions')
                            ->where('id', $session->id)
                            ->update(['telegram_chat_id' => $chatId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('support_topic_user')) {
            Schema::dropIfExists('support_topic_user');
        }

        Schema::table('support_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('support_sessions', 'telegram_chat_id')) {
                $table->dropIndex(['telegram_chat_id']);
                $table->dropColumn('telegram_chat_id');
            }
        });

        Schema::table('support_topics', function (Blueprint $table) {
            if (Schema::hasColumn('support_topics', 'telegram_chat_id')) {
                $table->dropUnique(['telegram_chat_id']);
                $table->dropColumn('telegram_chat_id');
            }
        });
    }
};
