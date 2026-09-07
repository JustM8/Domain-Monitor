<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'first_response_at')) {
                $table->timestamp('first_response_at')->nullable()->after('description')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'last_client_message_at')) {
                $table->timestamp('last_client_message_at')->nullable()->after('first_response_at')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'last_staff_message_at')) {
                $table->timestamp('last_staff_message_at')->nullable()->after('last_client_message_at')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'customer_rating')) {
                $table->unsignedTinyInteger('customer_rating')->nullable()->after('closed_note')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'customer_rating_comment')) {
                $table->text('customer_rating_comment')->nullable()->after('customer_rating');
            }

            if (! Schema::hasColumn('support_tickets', 'customer_rated_at')) {
                $table->timestamp('customer_rated_at')->nullable()->after('customer_rating_comment');
            }

            if (! Schema::hasColumn('support_tickets', 'sent_to_pm')) {
                $table->boolean('sent_to_pm')->default(false)->after('customer_rated_at')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'sent_to_pm_at')) {
                $table->timestamp('sent_to_pm_at')->nullable()->after('sent_to_pm')->index();
            }

            if (! Schema::hasColumn('support_tickets', 'sent_to_pm_by_user_id')) {
                $table->foreignId('sent_to_pm_by_user_id')->nullable()->after('sent_to_pm_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('support_tickets', 'stale_notified_at')) {
                $table->timestamp('stale_notified_at')->nullable()->after('sent_to_pm_by_user_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (Schema::hasColumn('support_tickets', 'stale_notified_at')) {
                $table->dropColumn('stale_notified_at');
            }

            if (Schema::hasColumn('support_tickets', 'sent_to_pm_by_user_id')) {
                $table->dropConstrainedForeignId('sent_to_pm_by_user_id');
            }

            if (Schema::hasColumn('support_tickets', 'sent_to_pm_at')) {
                $table->dropColumn('sent_to_pm_at');
            }

            if (Schema::hasColumn('support_tickets', 'sent_to_pm')) {
                $table->dropColumn('sent_to_pm');
            }

            if (Schema::hasColumn('support_tickets', 'customer_rated_at')) {
                $table->dropColumn('customer_rated_at');
            }

            if (Schema::hasColumn('support_tickets', 'customer_rating_comment')) {
                $table->dropColumn('customer_rating_comment');
            }

            if (Schema::hasColumn('support_tickets', 'customer_rating')) {
                $table->dropColumn('customer_rating');
            }

            if (Schema::hasColumn('support_tickets', 'last_staff_message_at')) {
                $table->dropColumn('last_staff_message_at');
            }

            if (Schema::hasColumn('support_tickets', 'last_client_message_at')) {
                $table->dropColumn('last_client_message_at');
            }

            if (Schema::hasColumn('support_tickets', 'first_response_at')) {
                $table->dropColumn('first_response_at');
            }
        });
    }
};
