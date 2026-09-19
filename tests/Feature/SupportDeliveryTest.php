<?php

namespace Tests\Feature;

use App\Modules\TelegramSupport\Models\SupportClient;
use App\Modules\TelegramSupport\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class SupportDeliveryTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    private function ticket(): SupportTicket
    {
        $client = SupportClient::create(['telegram_user_id' => '5', 'telegram_chat_id' => '5', 'state' => 'ready']);

        return SupportTicket::create(['support_client_id' => $client->id, 'number' => 'T-001', 'subject' => 'Test', 'type' => 'error', 'status' => 'new', 'description' => 'Test']);
    }

    public function test_manager_reply_failure_is_saved_and_retry_sets_first_response_only_on_success(): void
    {
        $ticket = $this->ticket();
        Http::fake(['*' => Http::sequence()->push(['ok' => false], 500)->push(['ok' => true, 'result' => ['message_id' => 55]])]);
        $this->actingAs($this->portalUser('manager'))->post('/portal/support/tickets/'.$ticket->id.'/reply', ['body' => '<b>Plain text</b>'])->assertRedirect();
        $this->assertNull($ticket->fresh()->first_response_at);
        $this->assertSame('new', $ticket->fresh()->status);
        $message = $ticket->messages()->first();
        $this->assertSame('failed', $message->delivery_status);
        $this->post('/portal/support/tickets/'.$ticket->id.'/messages/'.$message->id.'/retry')->assertRedirect();
        $this->assertSame('sent', $message->fresh()->delivery_status);
        $this->assertNotNull($ticket->fresh()->first_response_at);
        $this->post('/portal/support/tickets/'.$ticket->id.'/messages/'.$message->id.'/retry')->assertRedirect();
        Http::assertSentCount(2);
        $this->withoutExceptionHandling()->get('/portal/support/tickets/'.$ticket->id)->assertOk()->assertSee('Доставлено');
        $this->withExceptionHandling();
        $this->post('/portal/support/tickets/'.$ticket->id.'/status', ['status' => 'closed'])->assertForbidden();
        $this->actingAs($this->portalUser('pm'))->post('/portal/support/tickets/'.$ticket->id.'/reply', ['body' => 'No'])->assertForbidden();
    }

    public function test_support_webhook_requires_secret_and_deduplicates_updates(): void
    {
        $payload = ['update_id' => 123];
        $this->postJson('/api/telegram/support/webhook', $payload)->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram_support.webhook_secret'))->postJson('/api/telegram/support/webhook', $payload)->assertOk();
        $this->postJson('/api/telegram/support/webhook', $payload)->assertOk();
        $this->assertDatabaseCount('telegram_webhook_receipts', 1);
    }
}
