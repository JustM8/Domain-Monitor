<?php

namespace Database\Seeders;

use App\Modules\TelegramSupport\Models\SupportTopic;
use Illuminate\Database\Seeder;

class TelegramSupportTopicSeeder extends Seeder
{
    public function run(): void
    {
        $chatIds = [
            'consultation' => '-1004415912097',
            'settings' => '-1004436047824',
            'error' => '-1004433968849',
            'feature' => '-1003889396585',
        ];

        foreach (SupportTopic::seedCatalog() as $row) {
            $chatId = trim((string) ($chatIds[$row['code']] ?? ''));

            SupportTopic::updateOrCreate(
                ['code' => $row['code']],
                [
                    'label' => $row['label'],
                    'sort_order' => $row['sort_order'],
                    'assigned_role' => $row['assigned_role'],
                    'telegram_chat_id' => $chatId !== '' ? $chatId : null,
                    'is_active' => $row['is_active'],
                ]
            );
        }
    }
}
