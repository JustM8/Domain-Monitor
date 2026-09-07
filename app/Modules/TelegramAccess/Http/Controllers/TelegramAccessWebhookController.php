<?php

namespace App\Modules\TelegramAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Site\Models\Site;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TelegramAccessWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBotService $telegram)
    {
        try {
            $expectedSecret = (string) config('services.telegram.webhook_secret');
            if (filled($expectedSecret) && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $expectedSecret) {
                abort(403);
            }

            $payload = $request->all();
            $message = data_get($payload, 'message');
            $callback = data_get($payload, 'callback_query');

            Log::debug('telegram_access.webhook.received', [
                'update_id' => data_get($payload, 'update_id'),
                'has_message' => filled($message),
                'has_callback' => filled($callback),
                'message_type' => data_get($message, 'text') ? 'text' : (filled($callback) ? 'callback' : 'other'),
            ]);

            if ($callback) {
                Log::debug('telegram_access.webhook.callback_received', [
                    'callback_id' => data_get($callback, 'id'),
                    'from_id' => data_get($callback, 'from.id'),
                    'chat_id' => data_get($callback, 'message.chat.id'),
                    'data' => data_get($callback, 'data'),
                ]);

                $this->handleCallback($callback, $telegram);
                return response()->json(['ok' => true]);
            }

            if (! $message) {
                return response()->json(['ok' => true]);
            }

            $chatId = data_get($message, 'chat.id');
            $from = data_get($message, 'from', []);
            $telegramUserId = data_get($from, 'id');
            $text = trim((string) data_get($message, 'text', ''));
            $username = data_get($from, 'username');
            $firstName = data_get($from, 'first_name');
            [$command, $argument] = $this->parseCommand($text);

            if ($command === '' && $text !== '') {
                [$command, $argument] = $this->resolveTextAction($text);
            }

            if (! $chatId || ! $telegramUserId) {
                Log::warning('telegram_access.webhook.ignored_missing_identity', [
                    'update_id' => data_get($payload, 'update_id'),
                    'chat_id' => $chatId,
                    'telegram_user_id' => $telegramUserId,
                ]);

                return response()->json(['ok' => true]);
            }

            if ($command === 'start') {
                Log::debug('telegram_access.webhook.start_received', [
                    'chat_id' => (string) $chatId,
                    'telegram_user_id' => (string) $telegramUserId,
                    'username' => $username,
                    'has_argument' => $argument !== '',
                    'argument_prefix' => $argument !== '' ? mb_substr($argument, 0, 8) : null,
                ]);

                $this->handleStart($chatId, (string) $telegramUserId, $username, $firstName, $argument, $telegram, $payload);
                return response()->json(['ok' => true]);
            }

            $user = $this->userByChatId((string) $telegramUserId);
            if ($user && $command !== '') {
                Log::debug('telegram_access.webhook.command_received', [
                    'chat_id' => (string) $chatId,
                    'telegram_user_id' => (string) $telegramUserId,
                    'command' => $command,
                    'argument' => $argument !== '' ? mb_substr($argument, 0, 32) : null,
                    'linked' => $user?->telegramIsLinked(),
                ]);

                $handled = $this->handleCommandText($user, $command, $argument, $telegram, (string) $chatId, $payload);
                if ($handled) {
                    return response()->json(['ok' => true]);
                }
            }

            if ($user) {
                Log::debug('telegram_access.webhook.sending_status_card', [
                    'user_id' => $user->id,
                    'chat_id' => (string) $chatId,
                    'verified' => filled($user->telegram_verified_at),
                    'username' => $user->telegram_username,
                ]);

                $this->sendStatusCard($telegram, $user->fresh(), (string) $chatId);
            } else {
                Log::debug('telegram_access.webhook.sending_start_hint', [
                    'chat_id' => (string) $chatId,
                    'first_name' => $firstName,
                ]);

                $this->sendStartHint($telegram, (string) $chatId, $firstName ?: null);
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('telegram_access.webhook.failed', [
                'update_id' => data_get($request->all(), 'update_id'),
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    protected function handleStart(
        string|int $chatId,
        string $telegramUserId,
        ?string $username,
        ?string $firstName,
        string $argument,
        TelegramBotService $telegram,
        array $payload
    ): void {
        $user = null;

        if ($argument !== '') {
            $user = User::query()
                ->where('telegram_link_token', $argument)
                ->where(function ($query) {
                    $query->whereNull('telegram_link_expires_at')
                        ->orWhere('telegram_link_expires_at', '>=', now());
                })
                ->first();

            if ($user) {
                $update = [
                    'telegram_chat_id' => $telegramUserId,
                    'telegram_username' => $username,
                    'telegram_link_requested_at' => $user->telegram_link_requested_at ?? now(),
                ];

                if ($user->telegramIsApproved()) {
                    $update['telegram_verified_at'] = $user->telegram_verified_at ?? now();
                    $update['telegram_link_token'] = null;
                    $update['telegram_link_expires_at'] = null;
                }

                $user->forceFill($update)->save();

                Log::info('telegram_access.linked', [
                    'user_id' => $user->id,
                    'telegram_user_id' => $telegramUserId,
                    'verified' => filled($user->telegram_verified_at),
                ]);

                Log::debug('telegram_access.webhook.sending_status_card', [
                'user_id' => $user->id,
                'chat_id' => (string) $chatId,
                'verified' => filled($user->telegram_verified_at),
                'username' => $user->telegram_username,
            ]);

            $this->sendStatusCard($telegram, $user->fresh(), (string) $chatId);
                return;
            }

            Log::warning('telegram_access.token_not_found', [
                'telegram_user_id' => $telegramUserId,
                'chat_id' => (string) $chatId,
                'token_prefix' => mb_substr($argument, 0, 8),
                'first_name' => $firstName,
            ]);
        }

        $user = $this->userByChatId($telegramUserId);
        if ($user) {
            Log::debug('telegram_access.webhook.sending_status_card', [
                'user_id' => $user->id,
                'chat_id' => (string) $chatId,
                'verified' => filled($user->telegram_verified_at),
                'username' => $user->telegram_username,
            ]);

            $this->sendStatusCard($telegram, $user->fresh(), (string) $chatId);
            return;
        }

        $this->sendStartHint($telegram, (string) $chatId, $firstName ?: null);
    }

    protected function handleCallback(array $callback, TelegramBotService $telegram): void
    {
        $callbackId = (string) data_get($callback, 'id');
        $data = (string) data_get($callback, 'data', '');
        $message = data_get($callback, 'message', []);
        $chatId = (string) data_get($message, 'chat.id');
        $telegramUserId = (string) data_get(data_get($callback, 'from', []), 'id');
        $user = $this->userByChatId($telegramUserId);

        if ($data === 'ta:confirm') {
            if (! $user) {
                $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_need_start'));
                return;
            }

            if ($user->telegramIsLinked()) {
                $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_already_verified'));
                $this->sendStatusCard($telegram, $user->fresh(), $chatId);
                return;
            }

            $user->forceFill([
                'telegram_verified_at' => now(),
                'telegram_link_token' => null,
                'telegram_link_expires_at' => null,
                'telegram_username' => $user->telegram_username ?: data_get($callback, 'from.username'),
                'telegram_chat_id' => $user->telegram_chat_id ?: $chatId,
            ])->save();

            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_confirmed'));
            $this->sendStatusCard($telegram, $user->fresh(), $chatId);
            return;
        }

        if (! $user || ! $user->telegramIsLinked()) {
            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_confirm_first'));
            $this->sendStatusCard($telegram, $user ?: null, $chatId);
            return;
        }

        if ($data === 'ta:status' || $data === 'ta:home') {
            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_ready_short'));
            $this->sendStatusCard($telegram, $user->fresh(), $chatId);
            return;
        }

        if ($data === 'ta:sites') {
            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_sites_short'));
            $this->sendSitesList($telegram, $user->fresh(), $chatId);
            return;
        }

        if ($data === 'ta:hostings') {
            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_hosting_short'));
            $this->sendHostingsList($telegram, $user->fresh(), $chatId);
            return;
        }

        if ($data === 'ta:ftp') {
            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_ftp_short'));
            $this->sendFtpList($telegram, $user->fresh(), $chatId);
            return;
        }

        if (Str::startsWith($data, 'ta:site:')) {
            $siteId = (int) Str::afterLast($data, ':');
            $site = $this->resolveSiteById($siteId);

            if (! $site) {
                $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_site_not_found'));
                return;
            }

            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_site_short'));
            $this->sendSiteCard($telegram, $user->fresh(), $site, $chatId);
            return;
        }

        if (Str::startsWith($data, 'ta:hosting:')) {
            $hostingId = (int) Str::afterLast($data, ':');
            $hosting = $this->resolveHostingById($hostingId);

            if (! $hosting) {
                $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_hosting_not_found'));
                return;
            }

            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_hosting_short'));
            $this->sendHostingCard($telegram, $user->fresh(), $hosting, $chatId);
            return;
        }

        if (Str::startsWith($data, 'ta:ftpfile:')) {
            $ftpId = (int) Str::afterLast($data, ':');
            $ftp = $this->resolveFtpById($ftpId);

            if (! $ftp) {
                $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_ftp_not_found'));
                return;
            }

            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_file_sent'));
            $this->sendFtpFile($telegram, $ftp, $chatId);
            return;
        }

        if (Str::startsWith($data, 'ta:ftp:')) {
            $ftpId = (int) Str::afterLast($data, ':');
            $ftp = $this->resolveFtpById($ftpId);

            if (! $ftp) {
                $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_ftp_not_found'));
                return;
            }

            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_ftp_short'));
            $this->sendFtpCard($telegram, $user->fresh(), $ftp, $chatId);
            return;
        }

        $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_menu_hint'));
    }

    protected function handleCommandText(User $user, string $command, string $argument, TelegramBotService $telegram, string $chatId, array $payload): bool
    {
        if (! $user->telegramIsLinked()) {
            if (in_array($command, ['status', 'access', 'menu', 'help', 'portal'], true)) {
                $this->sendStatusCard($telegram, $user, $chatId);
                return true;
            }

            $telegram->sendMessage($chatId, __('portal.telegram_access_confirm_first'));
            $this->sendStatusCard($telegram, $user, $chatId);

            return true;
        }

        return match ($command) {
            'status', 'access', 'menu', 'help', 'portal' => tap(true, fn () => $this->sendStatusCard($telegram, $user, $chatId)),
            'sites' => tap(true, fn () => $this->sendSitesList($telegram, $user, $chatId)),
            'hostings', 'hosting' => tap(true, fn () => $this->sendHostingsList($telegram, $user, $chatId)),
            'ftp' => tap(true, fn () => $this->sendFtpList($telegram, $user, $chatId)),
            'site' => tap(true, fn () => $this->sendSiteByArgument($telegram, $user, $argument, $chatId)),
            'filezilla' => tap(true, fn () => $this->sendFtpFileByArgument($telegram, $user, $argument, $chatId)),
            default => false,
        };
    }

    protected function sendStartHint(TelegramBotService $telegram, string $chatId, ?string $firstName = null): void
    {
        $telegram->sendMessage($chatId, __('portal.telegram_start_hint_text', [
            'name' => $firstName ? e($firstName) : __('portal.telegram_user'),
        ]), [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        [
                            'text' => __('portal.open'),
                            'url' => rtrim((string) config('app.url'), '/') . '/portal/profile',
                        ],
                    ],
                ],
            ],
        ]);
    }

    protected function sendStatusCard(TelegramBotService $telegram, ?User $user, string $chatId): void
    {
        if (! $user) {
            $this->sendStartHint($telegram, $chatId);
            return;
        }

        $isLinked = $user->telegramIsLinked();
        $statusLabel = $isLinked
            ? __('portal.telegram_status_verified')
            : ($user->telegramIsPending() ? __('portal.telegram_status_pending') : __('portal.telegram_status_not_connected'));

        $lines = [
            '🔐 <b>' . e(__('portal.telegram_access_card_title')) . '</b>',
            '👤 <b>' . e(__('portal.telegram_access_user')) . ':</b> ' . e($user->displayName()),
            '📌 <b>' . e(__('portal.telegram_access_status')) . ':</b> ' . e($statusLabel),
            '💬 <b>' . e(__('portal.telegram_username')) . ':</b> ' . e($user->telegram_username ? '@' . ltrim($user->telegram_username, '@') : __('portal.empty')),
            '🕒 <b>' . e(__('portal.telegram_verified_at')) . ':</b> ' . e($user->telegram_verified_at?->format('d.m.Y H:i') ?? __('portal.empty')),
        ];

        if (! $isLinked) {
            $lines[] = '';
            $lines[] = '✅ <b>' . e(__('portal.telegram_access_confirm_first')) . '</b>';

            $telegram->sendMessage($chatId, implode("\n", $lines), [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [[
                            'text' => __('portal.telegram_confirm_self'),
                            'callback_data' => 'ta:confirm',
                        ]],
                        [[
                            'text' => __('portal.open'),
                            'url' => rtrim((string) config('app.url'), '/') . '/portal/profile',
                        ]],
                    ],
                ],
            ]);

            return;
        }

        $lines[] = '';
        $lines[] = '📋 <b>' . e(__('portal.telegram_access_menu_hint')) . '</b>';

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => $this->buildMainMenuKeyboard(),
        ]);
    }

    protected function sendSitesList(TelegramBotService $telegram, User $user, string $chatId): void
    {
        $sites = Site::query()
            ->with(['company', 'hostingAccounts.hosting', 'ftpAccounts'])
            ->orderBy('name')
            ->get();

        $keyboard = [];
        foreach ($sites as $site) {
            $keyboard[] = [[
                'text' => '🌐 #' . $site->id . ' ' . Str::limit($site->name, 28),
                'callback_data' => 'ta:site:' . $site->id,
            ]];
        }

        $keyboard[] = [[
            'text' => __('portal.telegram_back'),
            'callback_data' => 'ta:status',
        ]];

        $lines = [
            '🌐 <b>' . e(__('portal.telegram_access_sites_title')) . '</b>',
            '📌 ' . e(__('portal.telegram_access_list_hint')),
            '',
        ];

        if ($sites->isEmpty()) {
            $lines[] = e(__('portal.telegram_access_no_sites'));
        } else {
            foreach ($sites->take(10) as $site) {
                $lines[] = '• <b>' . e($site->name) . '</b> — ' . e($site->url);
                $lines[] = '  <i>' . e($site->company?->name ?: __('portal.empty')) . '</i>';
            }
        }

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendHostingsList(TelegramBotService $telegram, User $user, string $chatId): void
    {
        $hostings = Hosting::query()
            ->with(['accounts.sites'])
            ->orderBy('name')
            ->get();

        $keyboard = [];
        foreach ($hostings as $hosting) {
            $keyboard[] = [[
                'text' => '🏠 #' . $hosting->id . ' ' . Str::limit($hosting->name, 28),
                'callback_data' => 'ta:hosting:' . $hosting->id,
            ]];
        }

        $keyboard[] = [[
            'text' => __('portal.telegram_back'),
            'callback_data' => 'ta:status',
        ]];

        $lines = [
            '🏠 <b>' . e(__('portal.telegram_access_hostings_title')) . '</b>',
            '📌 ' . e(__('portal.telegram_access_list_hint')),
            '',
        ];

        if ($hostings->isEmpty()) {
            $lines[] = e(__('portal.empty'));
        } else {
            foreach ($hostings->take(10) as $hosting) {
                $siteCount = $hosting->accounts->flatMap(fn (HostingAccount $account) => $account->sites)->unique('id')->count();
                $lines[] = '• <b>' . e($hosting->name) . '</b> — ' . e($hosting->provider ?: __('portal.empty'));
                $lines[] = '  ' . e(__('portal.telegram_access_linked_counts', ['accounts' => $hosting->accounts->count(), 'sites' => $siteCount]));
            }
        }

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendFtpList(TelegramBotService $telegram, User $user, string $chatId): void
    {
        $ftpAccounts = FtpAccount::query()
            ->with(['company', 'site'])
            ->orderBy('host')
            ->get();

        $keyboard = [];
        foreach ($ftpAccounts as $ftp) {
            $keyboard[] = [[
                'text' => '📎 #' . $ftp->id . ' ' . Str::limit($ftp->host, 28),
                'callback_data' => 'ta:ftp:' . $ftp->id,
            ]];
        }

        $keyboard[] = [[
            'text' => __('portal.telegram_back'),
            'callback_data' => 'ta:status',
        ]];

        $lines = [
            '📎 <b>' . e(__('portal.telegram_access_ftp_title')) . '</b>',
            '📌 ' . e(__('portal.telegram_access_list_hint')),
            '',
        ];

        if ($ftpAccounts->isEmpty()) {
            $lines[] = e(__('portal.empty'));
        } else {
            foreach ($ftpAccounts->take(10) as $ftp) {
                $lines[] = '• <b>' . e($ftp->host . ':' . ($ftp->port ?: 21)) . '</b>';
                $lines[] = '  ' . e($ftp->login ?: __('portal.empty')) . ' · ' . e($ftp->path ?: __('portal.empty'));
            }
        }

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendSiteByArgument(TelegramBotService $telegram, User $user, string $argument, string $chatId): void
    {
        $site = $this->resolveSite($argument);

        if (! $site) {
            $telegram->sendMessage($chatId, __('portal.telegram_access_site_not_found'));
            return;
        }

        $this->sendSiteCard($telegram, $user, $site, $chatId);
    }

    protected function sendFtpFileByArgument(TelegramBotService $telegram, User $user, string $argument, string $chatId): void
    {
        $ftp = $this->resolveFtpAccount($argument);

        if (! $ftp) {
            $telegram->sendMessage($chatId, __('portal.telegram_access_ftp_not_found'));
            return;
        }

        $this->sendFtpFile($telegram, $ftp, $chatId);
    }

    protected function sendSiteCard(TelegramBotService $telegram, User $user, Site $site, string $chatId): void
    {
        $site->loadMissing(['company', 'hostingAccounts.hosting', 'ftpAccounts.company']);

        $hostingLines = $site->hostingAccounts->map(function (HostingAccount $account) {
            return '• ' . e($account->title ?: $account->hosting?->name ?: __('portal.empty')) . ' · ' . e($account->hosting?->provider ?: __('portal.empty'));
        })->all();

        $ftpLines = $site->ftpAccounts->map(function (FtpAccount $ftp) {
            return '• ' . e($ftp->host . ':' . ($ftp->port ?: 21)) . ' · ' . e($ftp->login ?: __('portal.empty'));
        })->all();

        $lines = [
            '🌐 <b>' . e($site->name) . '</b>',
            '🔗 ' . e($site->url),
            '🛠 ' . e(__('portal.admin_url')) . ': ' . e($site->admin_url ?: __('portal.empty')),
            '👤 ' . e(__('portal.admin_login')) . ': ' . e($site->admin_login ?: __('portal.empty')),
            '🔑 ' . e(__('portal.admin_password')) . ': ' . e($site->decryptedAdminPassword() ?: __('portal.empty')),
            '🏢 ' . e(__('portal.company')) . ': ' . e($site->company?->name ?: __('portal.empty')),
            '',
            '🏠 ' . e(__('portal.telegram_access_hostings_title')),
            $hostingLines ? implode("\n", $hostingLines) : e(__('portal.empty')),
            '',
            '📎 ' . e(__('portal.telegram_access_ftp_title')),
            $ftpLines ? implode("\n", $ftpLines) : e(__('portal.empty')),
        ];

        $keyboard = [
            [[
                'text' => __('portal.telegram_back'),
                'callback_data' => 'ta:sites',
            ]],
        ];

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendHostingCard(TelegramBotService $telegram, User $user, Hosting $hosting, string $chatId): void
    {
        $hosting->loadMissing(['accounts.company', 'accounts.sites']);
        $linkedSites = $hosting->accounts->flatMap(fn (HostingAccount $account) => $account->sites)->unique('id')->values();

        $accountLines = $hosting->accounts->map(function (HostingAccount $account) {
            return '• ' . e($account->title ?: __('portal.empty')) . ' · ' . e($account->login ?: __('portal.empty'));
        })->all();

        $lines = [
            '🏠 <b>' . e($hosting->name) . '</b>',
            '🏢 ' . e(__('portal.company')) . ': ' . e($hosting->accounts->first()?->company?->name ?: __('portal.empty')),
            '🌐 ' . e(__('portal.panel_url')) . ': ' . e($hosting->panel_url ?: __('portal.empty')),
            '📝 ' . e(__('portal.note')) . ': ' . e($hosting->note ?: __('portal.empty')),
            '',
            '🗂 ' . e(__('portal.telegram_access_hosting_accounts_title')),
            $accountLines ? implode("\n", $accountLines) : e(__('portal.empty')),
            '',
            '🌐 ' . e(__('portal.telegram_access_hosting_sites_title')),
            $linkedSites->isEmpty()
                ? e(__('portal.empty'))
                : implode("\n", $linkedSites->map(fn (Site $site) => '• ' . e($site->name) . ' — ' . e($site->url))->all()),
        ];

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => [[[
                'text' => __('portal.telegram_back'),
                'callback_data' => 'ta:hostings',
            ]]]],
        ]);
    }

    protected function sendFtpCard(TelegramBotService $telegram, User $user, FtpAccount $ftp, string $chatId): void
    {
        $ftp->loadMissing(['company', 'site', 'sites']);

        $lines = [
            '📎 <b>' . e($ftp->host . ':' . ($ftp->port ?: 21)) . '</b>',
            '👤 ' . e(__('portal.login_label')) . ': ' . e($ftp->login ?: __('portal.empty')),
            '🔑 ' . e(__('portal.password')) . ': ' . e($ftp->decryptedPassword() ?: __('portal.empty')),
            '📁 ' . e(__('portal.path')) . ': ' . e($ftp->path ?: __('portal.empty')),
            '🏢 ' . e(__('portal.company')) . ': ' . e($ftp->company?->name ?: __('portal.empty')),
            '',
            '🌐 ' . e(__('portal.telegram_access_ftp_sites_title')),
            $ftp->sites->isEmpty()
                ? e(__('portal.empty'))
                : implode("\n", $ftp->sites->map(fn (Site $site) => '• ' . e($site->name) . ' — ' . e($site->url))->all()),
        ];

        $keyboard = [
            [
                [
                    'text' => __('portal.telegram_filezilla'),
                    'callback_data' => 'ta:ftpfile:' . $ftp->id,
                ],
            ],
            [
                [
                    'text' => __('portal.telegram_back'),
                    'callback_data' => 'ta:ftp',
                ],
            ],
        ];

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendFtpFile(TelegramBotService $telegram, FtpAccount $ftp, string $chatId): void
    {
        $xml = $this->buildFileZillaXml($ftp);
        $site = $ftp->sites->first() ?? $ftp->site;
        $baseName = Str::slug($site?->name ?: $ftp->host) ?: 'ftp-account';
        $filename = $baseName . '-filezilla.xml';

        $telegram->sendDocument($chatId, $xml, $filename, [
            'caption' => __('portal.telegram_filezilla_caption', [
                'host' => e($ftp->host),
            ]),
        ]);
    }

    protected function buildFileZillaXml(FtpAccount $ftp): string
    {
        $ftp->loadMissing(['site', 'sites', 'company']);
        $site = $ftp->sites->first() ?? $ftp->site;
        $siteName = $site?->name ?: $ftp->host;
        $groupName = $ftp->company?->name ?: 'FTP';
        $password = $ftp->decryptedPassword() ?? '';
        $escape = static fn (mixed $value): string => htmlspecialchars(trim((string) $value), ENT_XML1 | ENT_COMPAT, 'UTF-8');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<FileZilla3>
  <Servers>
    <Server>
      <Host>{$escape($ftp->host)}</Host>
      <Port>{$escape($ftp->port ?: 21)}</Port>
      <Protocol>0</Protocol>
      <Type>0</Type>
      <User>{$escape($ftp->login)}</User>
      <Pass>{$escape($password)}</Pass>
      <Logontype>1</Logontype>
      <TimezoneOffset>0</TimezoneOffset>
      <PasvMode>MODE_DEFAULT</PasvMode>
      <MaximumMultipleConnections>0</MaximumMultipleConnections>
      <EncodingType>Auto</EncodingType>
      <BypassProxy>0</BypassProxy>
      <Name>{$escape($siteName)}</Name>
      <Comments>{$escape($ftp->path)}</Comments>
      <SyncBrowsing>0</SyncBrowsing>
      <Color>0</Color>
      <SortMode>0</SortMode>
      <Selected>0</Selected>
      <LocalDir></LocalDir>
      <RemoteDir>{$escape($ftp->path)}</RemoteDir>
      <ModifiedTime>0</ModifiedTime>
    </Server>
  </Servers>
  <Bookmarks>
    <Bookmark>
      <Name>{$escape($groupName)}</Name>
      <LocalDir></LocalDir>
      <RemoteDir>{$escape($ftp->path)}</RemoteDir>
    </Bookmark>
  </Bookmarks>
</FileZilla3>
XML;
    }

    protected function buildMainMenuKeyboard(): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => __('portal.telegram_status_button')],
                    ['text' => __('portal.telegram_sites_button')],
                ],
                [
                    ['text' => __('portal.telegram_hostings_button')],
                    ['text' => __('portal.telegram_ftp_button')],
                ],
            ],
            'resize_keyboard' => true,
            'is_persistent' => true,
        ];
    }

    protected function resolveTextAction(string $text): array
    {
        $normalized = mb_strtolower(trim($text));

        $actions = [
            mb_strtolower(__('portal.telegram_status_button')) => ['status', ''],
            mb_strtolower(__('portal.telegram_sites_button')) => ['sites', ''],
            mb_strtolower(__('portal.telegram_hostings_button')) => ['hostings', ''],
            mb_strtolower(__('portal.telegram_ftp_button')) => ['ftp', ''],
            mb_strtolower(__('portal.telegram_back')) => ['status', ''],
        ];

        return $actions[$normalized] ?? ['', ''];
    }

    protected function parseCommand(string $text): array
    {
        $text = trim($text);

        if ($text === '' || ! Str::startsWith($text, '/')) {
            return ['', ''];
        }

        [$rawCommand, $argument] = array_pad(preg_split('/\s+/', $text, 2), 2, '');
        $command = strtolower(ltrim((string) Str::before($rawCommand, '@'), '/'));

        return [$command, trim((string) $argument)];
    }

    protected function userByChatId(string $telegramUserId): ?User
    {
        return User::query()->where('telegram_chat_id', $telegramUserId)->first();
    }

    protected function resolveSiteById(int $siteId): ?Site
    {
        if ($siteId <= 0) {
            return null;
        }

        return Site::query()->with(['company', 'hostingAccounts.hosting', 'ftpAccounts.company', 'ftpAccounts.sites'])->find($siteId);
    }

    protected function resolveHostingById(int $hostingId): ?Hosting
    {
        if ($hostingId <= 0) {
            return null;
        }

        return Hosting::query()->with(['accounts.company', 'accounts.sites'])->find($hostingId);
    }

    protected function resolveFtpById(int $ftpId): ?FtpAccount
    {
        if ($ftpId <= 0) {
            return null;
        }

        return FtpAccount::query()->with(['company', 'site', 'sites'])->find($ftpId);
    }

    protected function resolveSite(string $argument): ?Site
    {
        $argument = trim($argument);

        if ($argument === '') {
            return null;
        }

        if (ctype_digit($argument)) {
            return $this->resolveSiteById((int) $argument);
        }

        return Site::query()
            ->with(['company', 'hostingAccounts.hosting', 'ftpAccounts.company', 'ftpAccounts.sites'])
            ->where(function ($query) use ($argument) {
                $query->where('name', 'like', '%' . $argument . '%')
                    ->orWhere('url', 'like', '%' . $argument . '%')
                    ->orWhere('admin_url', 'like', '%' . $argument . '%');
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN url = ? THEN 1 ELSE 2 END', [$argument, $argument])
            ->first();
    }

    protected function resolveFtpAccount(string $argument): ?FtpAccount
    {
        $argument = trim($argument);

        if ($argument === '') {
            return null;
        }

        if (ctype_digit($argument)) {
            return $this->resolveFtpById((int) $argument);
        }

        return FtpAccount::query()
            ->with(['company', 'site', 'sites'])
            ->where(function ($query) use ($argument) {
                $query->where('host', 'like', '%' . $argument . '%')
                    ->orWhere('login', 'like', '%' . $argument . '%')
                    ->orWhere('path', 'like', '%' . $argument . '%');
            })
            ->orderByRaw('CASE WHEN host = ? THEN 0 WHEN login = ? THEN 1 ELSE 2 END', [$argument, $argument])
            ->first();
    }

    protected function resolveHosting(string $argument): ?Hosting
    {
        $argument = trim($argument);

        if ($argument === '') {
            return null;
        }

        if (ctype_digit($argument)) {
            return $this->resolveHostingById((int) $argument);
        }

        return Hosting::query()
            ->with(['accounts.sites'])
            ->where(function ($query) use ($argument) {
                $query->where('name', 'like', '%' . $argument . '%')
                    ->orWhere('provider', 'like', '%' . $argument . '%')
                    ->orWhere('panel_url', 'like', '%' . $argument . '%');
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN provider = ? THEN 1 ELSE 2 END', [$argument, $argument])
            ->first();
    }
}

