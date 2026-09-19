<?php

namespace App\Modules\TelegramSupport\Console;

use App\Modules\TelegramSupport\Services\SupportWebhookInbox;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ReviewWebhook extends Command
{
    protected $signature = 'telegram-support:review {--show= : Update ID to inspect privately} {--resolve= : Update ID already reconciled manually}';

    protected $description = 'Inspect interrupted support events without resending them';

    public function handle(SupportWebhookInbox $inbox): int
    {
        $inbox->recoverInterrupted();
        $q = DB::table('telegram_webhook_receipts')->where('bot', 'support')->where('status', 'needs_review');
        if ($id = $this->option('resolve')) {
            $changed = $q->where('update_id', $id)->update(['status' => 'resolved', 'processed_at' => now(), 'payload' => null]);
            $this->info($changed ? 'Позначено звіреним. Повторного надсилання немає.' : 'Подію не знайдено.');

            return $changed ? self::SUCCESS : self::FAILURE;
        }
        if ($id = $this->option('show')) {
            $row = $q->where('update_id', $id)->first();
            if (! $row || ! $row->payload) {
                return self::FAILURE;
            }
            $this->line(Crypt::decryptString($row->payload));

            return self::SUCCESS;
        }
        $this->table(['Update ID', 'Початок'], $q->orderBy('id')->get(['update_id', 'started_at'])->map(fn ($r) => (array) $r)->all());

        return self::SUCCESS;
    }
}
