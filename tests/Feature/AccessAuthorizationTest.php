<?php

namespace Tests\Feature;

use App\Modules\TelegramAccess\Services\AccessLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPortalRecords;
use Tests\TestCase;

class AccessAuthorizationTest extends TestCase
{
    use CreatesPortalRecords, RefreshDatabase;

    private function command(string $text, string $chatType = 'private'): array
    {
        return ['message' => ['chat' => ['id' => 1001, 'type' => $chatType], 'from' => ['id' => 1001, 'username' => 'tester'], 'text' => $text]];
    }

    public function test_webhook_rejects_missing_wrong_and_unconfigured_secrets(): void
    {
        Http::fake();
        $this->postJson('/api/telegram/webhook', $this->command('/start'))->assertForbidden();
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', 'wrong')->postJson('/api/telegram/webhook', $this->command('/start'))->assertForbidden();
        config(['telegram_access.webhook_secret' => '']);
        $this->postJson('/api/telegram/webhook', $this->command('/start'))->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_one_time_link_and_each_command_recheck_active_account(): void
    {
        $user = $this->portalUser('developer');
        $link = app(AccessLinkService::class)->issue($user);
        $this->assertSame(hash('sha256', $link), $user->fresh()->telegram_link_token);
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram_access.webhook_secret'))->postJson('/api/telegram/webhook', $this->command('/start '.$link))->assertOk();
        $this->assertNull($user->fresh()->telegram_link_token);
        $this->assertTrue($user->fresh()->telegramIsLinked());
        $this->assertNull(app(AccessLinkService::class)->consume($link, '1001', 'tester'));
        $user->update(['is_active' => false, 'approval_status' => 'blocked']);
        $this->postJson('/api/telegram/webhook', $this->command('/sites'))->assertOk();
        Http::assertSent(fn ($r) => str_contains($r['text'] ?? '', 'Ви не маєте доступу'));
    }

    public function test_expired_link_group_chat_and_self_confirmation_cannot_grant_access(): void
    {
        $user = $this->portalUser('pm');
        $links = app(AccessLinkService::class);
        $link = $links->issue($user);
        $this->travel(16)->minutes();
        $this->assertNull($links->consume($link, '1001', 'tester'));
        $this->travelBack();
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram_access.webhook_secret'))->postJson('/api/telegram/webhook', $this->command('/start '.$link, 'group'))->assertOk();
        $this->assertFalse($user->fresh()->telegramIsLinked());
        $this->postJson('/api/telegram/webhook', ['callback_query' => ['id' => 'cb', 'data' => 'ta:confirm', 'from' => ['id' => 1001], 'message' => ['chat' => ['id' => 1001, 'type' => 'private']]]])->assertOk();
        $this->assertFalse($user->fresh()->telegramIsLinked());
        $this->actingAs($this->portalUser('manager'))->post('/portal/profile/telegram/request')->assertForbidden();
    }

    public function test_site_list_is_paginated_and_revoked_callback_does_not_expose_credentials(): void
    {
        $user = $this->portalUser('developer', 'active', ['telegram_chat_id' => '1001', 'telegram_verified_at' => now()]);
        for ($i = 1; $i <= 25; $i++) {
            $this->site(['name' => sprintf('Site %02d', $i)]);
        }
        Http::fake(['*' => Http::response(['ok' => true])]);
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram_access.webhook_secret'))->postJson('/api/telegram/webhook', $this->command('/sites'))->assertOk();
        Http::assertSent(function ($request) {
            $keyboard = $request['reply_markup']['inline_keyboard'] ?? [];

            return count($keyboard) === 22 && ($keyboard[20][0]['callback_data'] ?? '') === 'ta:page:sites:2';
        });
        $callback = ['callback_query' => ['id' => 'page', 'data' => 'ta:page:sites:2', 'from' => ['id' => 1001], 'message' => ['chat' => ['id' => 1001, 'type' => 'private']]]];
        $this->postJson('/api/telegram/webhook', $callback)->assertOk();
        $user->forceFill(['telegram_access_revoked_at' => now()])->save();
        $callback['callback_query']['data'] = 'ta:site:1';
        $this->postJson('/api/telegram/webhook', $callback)->assertOk();
        $last = Http::recorded()->last()[0];
        $this->assertStringContainsString('Ви не маєте доступу', $last['text']);
    }
}
