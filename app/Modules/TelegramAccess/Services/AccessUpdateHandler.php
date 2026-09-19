<?php

namespace App\Modules\TelegramAccess\Services;

use App\Models\User;
use App\Modules\Ftp\Models\FtpAccount;
use App\Modules\Hosting\Models\Hosting;
use App\Modules\Hosting\Models\HostingAccount;
use App\Modules\Site\Models\Site;
use Illuminate\Support\Str;

final class AccessUpdateHandler
{
    public function handle(array $payload, TelegramBotService $telegram): void
    {
        $callback = data_get($payload, 'callback_query');
        $message = $callback ? data_get($callback, 'message', []) : data_get($payload, 'message', []);
        $from = $callback ? data_get($callback, 'from', []) : data_get($message, 'from', []);
        $chatId = (string) data_get($message, 'chat.id', '');
        $telegramId = (string) data_get($from, 'id', '');
        if ($chatId === '' || $telegramId === '') {
            return;
        }
        if (data_get($message, 'chat.type') !== 'private' || $chatId !== $telegramId) {
            if ($callback) {
                $telegram->answerCallbackQuery((string) $callback['id'], 'Доступ можливий лише в особистому чаті.', true);
            } else {
                $telegram->sendMessage($chatId, 'Доступ можливий лише в особистому чаті.');
            }

            return;
        }
        [$command, $argument] = $this->parseCommand(trim((string) data_get($message, 'text', '')));
        $user = $this->userByChatId($telegramId);
        if (! $callback && $command === 'start' && $argument !== '') {
            $user = app(AccessLinkService::class)->consume($argument, $telegramId, data_get($from, 'username'));
        }
        if (! $user) {
            $denied = 'Ви не маєте доступу. Увійдіть у підтверджений обліковий запис порталу й підключіть Telegram у своєму кабінеті.';
            if ($callback) {
                $telegram->answerCallbackQuery((string) $callback['id'], $denied, true);
            } else {
                $telegram->sendMessage($chatId, $denied);
            }

            return;
        }
        if ($callback) {
            $this->handleCallback($callback, $telegram);

            return;
        }
        if ($command === '') {
            [$command, $argument] = $this->resolveTextAction(trim((string) data_get($message, 'text', '')));
        }
        if (! $this->handleCommandText($user, $command, $argument, $telegram, $chatId, $payload)) {
            $this->sendStatusCard($telegram, $user, $chatId);
        }
    }

    protected function handleCallback(array $callback, TelegramBotService $telegram): void
    {
        $callbackId = (string) data_get($callback, 'id');
        $data = (string) data_get($callback, 'data', '');
        $message = data_get($callback, 'message', []);
        $chatId = (string) data_get($message, 'chat.id');
        $telegramUserId = (string) data_get(data_get($callback, 'from', []), 'id');
        $user = $this->userByChatId($telegramUserId);

        if (! $user || ! $user->canUseAccessBot() || ! $user->telegramIsLinked()) {
            $telegram->answerCallbackQuery($callbackId, __('portal.telegram_access_confirm_first'));
            $this->sendStatusCard($telegram, $user ?: null, $chatId);

            return;
        }

        if (preg_match('/^ta:page:(sites|hostings|ftp):(\d+)$/', $data, $page)) {
            $telegram->answerCallbackQuery($callbackId);
            $method = ['sites' => 'sendSitesList', 'hostings' => 'sendHostingsList', 'ftp' => 'sendFtpList'][$page[1]];
            $this->$method($telegram, $user, $chatId, min(10000, (int) $page[2]));

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
                            'url' => rtrim((string) config('app.url'), '/').'/portal/profile',
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
            '🔐 <b>'.e(__('portal.telegram_access_card_title')).'</b>',
            '👤 <b>'.e(__('portal.telegram_access_user')).':</b> '.e($user->displayName()),
            '📌 <b>'.e(__('portal.telegram_access_status')).':</b> '.e($statusLabel),
            '💬 <b>'.e(__('portal.telegram_username')).':</b> '.e($user->telegram_username ? '@'.ltrim($user->telegram_username, '@') : __('portal.empty')),
            '🕒 <b>'.e(__('portal.telegram_verified_at')).':</b> '.e($user->telegram_verified_at?->format('d.m.Y H:i') ?? __('portal.empty')),
        ];

        if (! $isLinked) {
            $lines[] = '';
            $lines[] = '✅ <b>'.e(__('portal.telegram_access_confirm_first')).'</b>';

            $telegram->sendMessage($chatId, implode("\n", $lines), [
                'reply_markup' => [
                    'inline_keyboard' => [
                        [[
                            'text' => __('portal.telegram_confirm_self'),
                            'callback_data' => 'ta:confirm',
                        ]],
                        [[
                            'text' => __('portal.open'),
                            'url' => rtrim((string) config('app.url'), '/').'/portal/profile',
                        ]],
                    ],
                ],
            ]);

            return;
        }

        $lines[] = '';
        $lines[] = '📋 <b>'.e(__('portal.telegram_access_menu_hint')).'</b>';

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => $this->buildMainMenuKeyboard(),
        ]);
    }

    protected function sendSitesList(TelegramBotService $telegram, User $user, string $chatId, int $page = 1): void
    {
        $sites = Site::query()
            ->with(['company', 'hostingAccounts.hosting', 'ftpAccounts'])
            ->orderBy('name')
            ->paginate(20, ['*'], 'page', max(1, $page));

        $keyboard = [];
        foreach ($sites as $site) {
            $keyboard[] = [[
                'text' => '🌐 #'.$site->id.' '.Str::limit($site->name, 28),
                'callback_data' => 'ta:site:'.$site->id,
            ]];
        }

        $pages = [];
        if ($sites->currentPage() > 1) {
            $pages[] = ['text' => '←', 'callback_data' => 'ta:page:sites:'.($sites->currentPage() - 1)];
        }
        if ($sites->hasMorePages()) {
            $pages[] = ['text' => '→', 'callback_data' => 'ta:page:sites:'.($sites->currentPage() + 1)];
        }
        if ($pages) {
            $keyboard[] = $pages;
        }
        $keyboard[] = [[
            'text' => __('portal.telegram_back'),
            'callback_data' => 'ta:status',
        ]];

        $lines = [
            '🌐 <b>'.e(__('portal.telegram_access_sites_title')).'</b>',
            '📌 '.e(__('portal.telegram_access_list_hint')),
            '',
        ];

        if ($sites->isEmpty()) {
            $lines[] = e(__('portal.telegram_access_no_sites'));
        } else {
            foreach ($sites->take(10) as $site) {
                $lines[] = '• <b>'.e($site->name).'</b> — '.e($site->url);
                $lines[] = '  <i>'.e($site->company?->name ?: __('portal.empty')).'</i>';
            }
        }

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendHostingsList(TelegramBotService $telegram, User $user, string $chatId, int $page = 1): void
    {
        $hostings = Hosting::query()
            ->with(['accounts.sites'])
            ->orderBy('name')
            ->paginate(20, ['*'], 'page', max(1, $page));

        $keyboard = [];
        foreach ($hostings as $hosting) {
            $keyboard[] = [[
                'text' => '🏠 #'.$hosting->id.' '.Str::limit($hosting->name, 28),
                'callback_data' => 'ta:hosting:'.$hosting->id,
            ]];
        }

        $pages = [];
        if ($hostings->currentPage() > 1) {
            $pages[] = ['text' => '←', 'callback_data' => 'ta:page:hostings:'.($hostings->currentPage() - 1)];
        }
        if ($hostings->hasMorePages()) {
            $pages[] = ['text' => '→', 'callback_data' => 'ta:page:hostings:'.($hostings->currentPage() + 1)];
        }
        if ($pages) {
            $keyboard[] = $pages;
        }
        $keyboard[] = [[
            'text' => __('portal.telegram_back'),
            'callback_data' => 'ta:status',
        ]];

        $lines = [
            '🏠 <b>'.e(__('portal.telegram_access_hostings_title')).'</b>',
            '📌 '.e(__('portal.telegram_access_list_hint')),
            '',
        ];

        if ($hostings->isEmpty()) {
            $lines[] = e(__('portal.empty'));
        } else {
            foreach ($hostings->take(10) as $hosting) {
                $siteCount = $hosting->accounts->flatMap(fn (HostingAccount $account) => $account->sites)->unique('id')->count();
                $lines[] = '• <b>'.e($hosting->name).'</b> — '.e($hosting->provider ?: __('portal.empty'));
                $lines[] = '  '.e(__('portal.telegram_access_linked_counts', ['accounts' => $hosting->accounts->count(), 'sites' => $siteCount]));
            }
        }

        $telegram->sendMessage($chatId, implode("\n", $lines), [
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ]);
    }

    protected function sendFtpList(TelegramBotService $telegram, User $user, string $chatId, int $page = 1): void
    {
        $ftpAccounts = FtpAccount::query()
            ->with(['company', 'site'])
            ->orderBy('host')
            ->paginate(20, ['*'], 'page', max(1, $page));

        $keyboard = [];
        foreach ($ftpAccounts as $ftp) {
            $keyboard[] = [[
                'text' => '📎 #'.$ftp->id.' '.Str::limit($ftp->host, 28),
                'callback_data' => 'ta:ftp:'.$ftp->id,
            ]];
        }

        $pages = [];
        if ($ftpAccounts->currentPage() > 1) {
            $pages[] = ['text' => '←', 'callback_data' => 'ta:page:ftp:'.($ftpAccounts->currentPage() - 1)];
        }
        if ($ftpAccounts->hasMorePages()) {
            $pages[] = ['text' => '→', 'callback_data' => 'ta:page:ftp:'.($ftpAccounts->currentPage() + 1)];
        }
        if ($pages) {
            $keyboard[] = $pages;
        }
        $keyboard[] = [[
            'text' => __('portal.telegram_back'),
            'callback_data' => 'ta:status',
        ]];

        $lines = [
            '📎 <b>'.e(__('portal.telegram_access_ftp_title')).'</b>',
            '📌 '.e(__('portal.telegram_access_list_hint')),
            '',
        ];

        if ($ftpAccounts->isEmpty()) {
            $lines[] = e(__('portal.empty'));
        } else {
            foreach ($ftpAccounts->take(10) as $ftp) {
                $lines[] = '• <b>'.e($ftp->host.':'.($ftp->port ?: 21)).'</b>';
                $lines[] = '  '.e($ftp->login ?: __('portal.empty')).' · '.e($ftp->path ?: __('portal.empty'));
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
            return '• '.e($account->title ?: $account->hosting?->name ?: __('portal.empty')).' · '.e($account->login).' / '.e($account->decryptedPassword())."\nSSH: ".e($account->ssh_host).' · '.e($account->ssh_login).' / '.e($account->decryptedSshPassword());
        })->all();

        $ftpLines = $site->ftpAccounts->map(function (FtpAccount $ftp) {
            return '• '.e($ftp->host.':'.($ftp->port ?: 21)).' · '.e($ftp->login ?: __('portal.empty')).' / '.e($ftp->decryptedPassword());
        })->all();

        $lines = [
            '🌐 <b>'.e($site->name).'</b>',
            '🔗 '.e($site->url),
            '🛠 '.e(__('portal.admin_url')).': '.e($site->admin_url ?: __('portal.empty')),
            '👤 '.e(__('portal.admin_login')).': '.e($site->admin_login ?: __('portal.empty')),
            '🔑 '.e(__('portal.admin_password')).': '.e($site->decryptedAdminPassword() ?: __('portal.empty')),
            '🏢 '.e(__('portal.company')).': '.e($site->company?->name ?: __('portal.empty')),
            '',
            '🏠 '.e(__('portal.telegram_access_hostings_title')),
            $hostingLines ? implode("\n", $hostingLines) : e(__('portal.empty')),
            '',
            '📎 '.e(__('portal.telegram_access_ftp_title')),
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
            return '• '.e($account->title ?: __('portal.empty')).' · '.e($account->login ?: __('portal.empty')).' / '.e($account->decryptedPassword())."\nSSH: ".e($account->ssh_host).' · '.e($account->ssh_login).' / '.e($account->decryptedSshPassword());
        })->all();

        $lines = [
            '🏠 <b>'.e($hosting->name).'</b>',
            '🏢 '.e(__('portal.company')).': '.e($hosting->accounts->first()?->company?->name ?: __('portal.empty')),
            '🌐 '.e(__('portal.panel_url')).': '.e($hosting->panel_url ?: __('portal.empty')),
            '📝 '.e(__('portal.note')).': '.e($hosting->note ?: __('portal.empty')),
            '',
            '🗂 '.e(__('portal.telegram_access_hosting_accounts_title')),
            $accountLines ? implode("\n", $accountLines) : e(__('portal.empty')),
            '',
            '🌐 '.e(__('portal.telegram_access_hosting_sites_title')),
            $linkedSites->isEmpty()
                ? e(__('portal.empty'))
                : implode("\n", $linkedSites->map(fn (Site $site) => '• '.e($site->name).' — '.e($site->url))->all()),
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
            '📎 <b>'.e($ftp->host.':'.($ftp->port ?: 21)).'</b>',
            '👤 '.e(__('portal.login_label')).': '.e($ftp->login ?: __('portal.empty')),
            '🔑 '.e(__('portal.password')).': '.e($ftp->decryptedPassword() ?: __('portal.empty')),
            '📁 '.e(__('portal.path')).': '.e($ftp->path ?: __('portal.empty')),
            '🏢 '.e(__('portal.company')).': '.e($ftp->company?->name ?: __('portal.empty')),
            '',
            '🌐 '.e(__('portal.telegram_access_ftp_sites_title')),
            $ftp->sites->isEmpty()
                ? e(__('portal.empty'))
                : implode("\n", $ftp->sites->map(fn (Site $site) => '• '.e($site->name).' — '.e($site->url))->all()),
        ];

        $keyboard = [
            [
                [
                    'text' => __('portal.telegram_filezilla'),
                    'callback_data' => 'ta:ftpfile:'.$ftp->id,
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
        $filename = $baseName.'-filezilla.xml';

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
        $user = User::query()->with('role')->where('telegram_chat_id', $telegramUserId)->first();

        return $user && $user->canUseAccessBot() && $user->telegramIsLinked() ? $user : null;
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
                $query->where('name', 'like', '%'.$argument.'%')
                    ->orWhere('url', 'like', '%'.$argument.'%')
                    ->orWhere('admin_url', 'like', '%'.$argument.'%');
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
                $query->where('host', 'like', '%'.$argument.'%')
                    ->orWhere('login', 'like', '%'.$argument.'%')
                    ->orWhere('path', 'like', '%'.$argument.'%');
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
                $query->where('name', 'like', '%'.$argument.'%')
                    ->orWhere('provider', 'like', '%'.$argument.'%')
                    ->orWhere('panel_url', 'like', '%'.$argument.'%');
            })
            ->orderByRaw('CASE WHEN name = ? THEN 0 WHEN provider = ? THEN 1 ELSE 2 END', [$argument, $argument])
            ->first();
    }
}
