<?php

namespace Tests\Feature;

use App\Modules\TelegramSupport\Models\SupportClient;
use App\Modules\TelegramSupport\Models\SupportMessage;
use App\Modules\TelegramSupport\Models\SupportSession;
use App\Modules\TelegramSupport\Models\SupportTicket;
use App\Modules\TelegramSupport\Models\SupportTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class SupportTelegramAttributionTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    public function test_verified_telegram_group_reply_is_attributed_to_portal_user(): void
    {
        $manager = $this->portalUser('manager', 'active', [
            'full_name' => 'Karina Manager',
            'telegram_chat_id' => '7001',
            'telegram_username' => 'karina_portal',
            'telegram_verified_at' => now(),
        ]);
        $ticket = $this->supportTicket();

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 991]], 200)]);

        $this->postSupportWebhook($this->groupMessage(101, $ticket->session, [
            'id' => 7001,
            'username' => 'karina_tg',
            'first_name' => 'Karina',
            'last_name' => 'Support',
        ], 'Працюю над заявкою'))->assertOk();

        $message = SupportMessage::query()->where('direction', 'staff')->firstOrFail();
        $this->assertSame($manager->id, $message->sent_by_user_id);
        $this->assertSame('karina_tg', $message->telegram_username);
        $this->assertSame('7001', (string) data_get($message->payload, 'source_user_id'));
        $this->assertSame('sent', $message->delivery_status);
        $this->assertNotNull($ticket->fresh()->first_response_at);
    }

    public function test_unverified_telegram_group_reply_is_delivered_without_portal_user(): void
    {
        $ticket = $this->supportTicket();

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 992]], 200)]);

        $this->postSupportWebhook($this->groupMessage(102, $ticket->session, [
            'id' => 9001,
            'username' => 'unknown_staff',
        ], 'Відповідь без верифікації'))->assertOk();

        $message = SupportMessage::query()->where('direction', 'staff')->firstOrFail();
        $this->assertNull($message->sent_by_user_id);
        $this->assertSame('unknown_staff', $message->telegram_username);
        $this->assertSame('sent', $message->delivery_status);
    }

    public function test_telegram_close_command_records_verified_closer(): void
    {
        $manager = $this->portalUser('manager', 'active', [
            'telegram_chat_id' => '7002',
            'telegram_username' => 'closer',
            'telegram_verified_at' => now(),
        ]);
        $ticket = $this->supportTicket();

        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 993]], 200)]);

        $this->postSupportWebhook($this->groupMessage(103, $ticket->session, [
            'id' => 7002,
            'username' => 'closer',
        ], '/close'))->assertOk();

        $ticket->refresh();
        $this->assertSame(SupportTicket::STATUS_CLOSED, $ticket->status);
        $this->assertSame($manager->id, $ticket->closed_by_user_id);
    }

    public function test_actual_manager_analytics_uses_messages_not_topic_responsibles(): void
    {
        $artem = $this->portalUser('manager', 'active', ['full_name' => 'Artem']);
        $karina = $this->portalUser('manager', 'active', ['full_name' => 'Karina']);
        $ticket = $this->supportTicket();
        $ticket->session->topic->responsibleUsers()->sync([$artem->id, $karina->id]);

        SupportMessage::create([
            'support_ticket_id' => $ticket->id,
            'direction' => 'staff',
            'body' => 'Karina reply',
            'sent_by_user_id' => $karina->id,
            'delivery_status' => 'sent',
        ]);

        $response = $this->actingAs($this->portalUser('manager'))->get('/portal/support/analytics');

        $response->assertOk();
        $byManager = $response->viewData('byManager');
        $karinaRow = $byManager->firstWhere('name', 'Karina');
        $artemRow = $byManager->firstWhere('name', 'Artem');

        $this->assertNotNull($karinaRow);
        $this->assertNotNull($artemRow);
        $this->assertSame(1, $karinaRow['tickets']);
        $this->assertSame(1, $karinaRow['replies']);
        $this->assertSame(0, $artemRow['tickets']);
        $this->assertSame(0, $artemRow['replies']);
    }

    protected function postSupportWebhook(array $payload)
    {
        config(['telegram_support.webhook_secret' => 'support-secret']);

        return $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'support-secret')
            ->postJson('/api/telegram/support/webhook', $payload);
    }

    protected function groupMessage(int $updateId, SupportSession $session, array $from, string $text): array
    {
        return [
            'update_id' => $updateId,
            'message' => [
                'message_id' => $updateId + 1000,
                'message_thread_id' => $session->telegram_thread_id,
                'chat' => [
                    'id' => (int) $session->telegram_chat_id,
                    'type' => 'supergroup',
                ],
                'from' => $from + ['is_bot' => false],
                'text' => $text,
            ],
        ];
    }

    protected function supportTicket(): SupportTicket
    {
        $topic = SupportTopic::query()->where('code', 'consultation')->firstOrFail();
        $topic->forceFill([
            'telegram_chat_id' => '-100555',
            'is_active' => true,
        ])->save();

        $client = SupportClient::create([
            'telegram_user_id' => '5',
            'telegram_chat_id' => '5',
            'state' => 'ready',
        ]);

        $session = SupportSession::create([
            'support_client_id' => $client->id,
            'support_topic_id' => $topic->id,
            'number' => 'S-'.$client->id.'-'.SupportSession::query()->count(),
            'title' => 'Consultation',
            'status' => SupportSession::STATUS_OPEN,
            'telegram_chat_id' => '-100555',
            'telegram_thread_id' => 77 + SupportSession::query()->count(),
            'last_message_at' => now(),
        ]);

        return SupportTicket::create([
            'support_client_id' => $client->id,
            'support_session_id' => $session->id,
            'number' => 'T-'.$client->id.'-'.SupportTicket::query()->count(),
            'number_in_session' => 1,
            'subject' => 'Test',
            'type' => 'consultation',
            'status' => SupportTicket::STATUS_NEW,
            'description' => 'Test',
        ])->fresh(['session.topic']);
    }
}
