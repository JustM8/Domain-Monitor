<?php

namespace Tests\Feature;

use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Shared\Models\Company;
use App\Modules\Site\Models\Site;
use App\Modules\TelegramAccess\Services\TelegramBotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Tests\TestCase;

class TelegramAccessWebhookTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\CreatesPortalRecords;

    public function test_verified_user_can_open_access_menu(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram_access.webhook_secret'));

        $user = $this->portalUser('developer', 'active', [
            'telegram_chat_id' => '1001',
            'telegram_username' => 'tester',
            'telegram_verified_at' => now(),
        ]);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text, $options) use ($user) {
                return (string) $chatId === '1001'
                    && str_contains((string) $text, __('portal.telegram_access_card_title'))
                    && str_contains((string) $text, $user->displayName())
                    && isset($options['reply_markup']['keyboard']);
            })
            ->andReturn(['ok' => true]);

        $this->app->instance(TelegramBotService::class, $bot);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 1001, 'type' => 'private'],
                'from' => [
                    'id' => 1001,
                    'username' => 'tester',
                    'first_name' => 'Test',
                ],
                'text' => '/access',
            ],
        ]);

        $response->assertOk();
    }

    public function test_site_command_returns_access_bundle_from_real_tables(): void
    {
        $this->withHeader('X-Telegram-Bot-Api-Secret-Token', config('telegram_access.webhook_secret'));

        $user = $this->portalUser('developer', 'active', [
            'telegram_chat_id' => '2002',
            'telegram_username' => 'verified_user',
            'telegram_verified_at' => now(),
        ]);

        $company = Company::create([
            'name' => 'Acme Agency',
        ]);

        $site = Site::create([
            'name' => 'Example Site',
            'url' => 'https://example.com',
            'site_type' => 'site',
            'environment' => 'prod',
            'admin_url' => 'https://example.com/admin',
            'admin_login' => 'admin',
            'admin_password' => Crypt::encryptString('admin-pass'),
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $hosting = Hosting::create([
            'name' => 'Main Hosting',
            'provider' => 'Provider One',
            'panel_url' => 'https://panel.example.com',
            'note' => 'Primary environment',
        ]);

        $hostingAccount = HostingAccount::create([
            'hosting_id' => $hosting->id,
            'company_id' => $company->id,
            'title' => 'Main account',
            'login' => 'host-login',
            'password' => Crypt::encryptString('host-pass'),
            'ssh_host' => 'ssh.example.com',
            'ssh_port' => 22,
            'ssh_login' => 'ssh-user',
            'ssh_password' => Crypt::encryptString('ssh-pass'),
            'note' => 'SSH access',
        ]);

        $ftpAccount = FtpAccount::create([
            'site_id' => $site->id,
            'company_id' => $company->id,
            'host' => 'ftp.example.com',
            'port' => 21,
            'login' => 'ftp-user',
            'password' => Crypt::encryptString('ftp-pass'),
            'path' => '/public_html',
            'requires_ip_access' => false,
            'note' => 'Main FTP',
        ]);

        $site->hostingAccounts()->attach($hostingAccount->id, ['is_main' => true]);
        $site->ftpAccounts()->attach($ftpAccount->id);

        $bot = Mockery::mock(TelegramBotService::class);
        $bot->shouldReceive('sendMessage')
            ->once()
            ->withArgs(function ($chatId, $text, $options) {
                $body = (string) $text;

                return (string) $chatId === '2002'
                    && str_contains($body, 'Example Site')
                    && str_contains($body, 'admin-pass')
                    && str_contains($body, 'host-pass')
                    && str_contains($body, 'ssh-pass')
                    && str_contains($body, 'ftp-pass')
                    && isset($options['reply_markup']);
            })
            ->andReturn(['ok' => true]);

        $this->app->instance(TelegramBotService::class, $bot);

        $response = $this->postJson('/api/telegram/webhook', [
            'message' => [
                'chat' => ['id' => 2002, 'type' => 'private'],
                'from' => [
                    'id' => 2002,
                    'username' => 'verified_user',
                    'first_name' => 'Verified',
                ],
                'text' => '/site Example Site',
            ],
        ]);

        $response->assertOk();
    }
}
