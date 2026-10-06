# Карта модулів

Актуалізовано за локальним кодом 2026-10-05 після Monitoring V2. Шляхи відносні до кореня проєкту; модулі у app/Modules.

| Модуль | Відповідальність | Основні точки входу всередині модуля |
| --- | --- | --- |
| Site | Картки, доступи, тип/середовище, ревізії, iframe, керування | Models/Site.php; Http/Controllers/SiteController.php; Services/SiteControlService.php, SiteSyncService.php, SiteMetadataService.php, SitePresentation.php |
| Monitoring | HTTP/TCP/Heartbeat, SSL lifecycle, UTC claims/state, compressed history, Kyiv rollups, immutable events/delivery, typed settings | Models/Monitor.php; Console/RunMonitoring.php, MonitoringMaintenance.php, VerifyMonitoring.php; Services/MonitorRunner.php, MonitorManager.php, MonitorHistory.php, MonitorRetention.php, MonitorEvents.php, MonitorHeartbeat.php, MonitorCertificates.php, MonitoringSettings.php, MonitoringTime.php, MonitoringInstaller.php, MonitoringAddressPolicy.php; Http/Controllers; routes/web.php, api.php |
| Shared | Права, активність користувача, вихідний HTTP, довідники, dashboard, кошик, аудит | Support/PortalAccess.php; Http/SafeHttp.php, OutboundAddressPolicy.php; Middleware; Models; Traits/HasPortalAudit.php |
| TelegramAccess | Підключення бота співробітником, одноразові посилання, видача доступів, транспорт повідомлень моніторингу | Services/AccessLinkService.php, AccessUpdateHandler.php, TelegramBotService.php; Console/TelegramSetWebhook.php |
| TelegramSupport | Клієнти, теми/сесії/тікети, повідомлення, аналітика, доставка | Models; Services/SupportUpdateHandler.php, SupportWebhookInbox.php, SupportReplyDelivery.php, TelegramSupportBotService.php; Http/Controllers |
| UserManagement | Користувачі, погодження, профіль | Http/Controllers/UserController.php, ProfileController.php; модель User лежить у app/Models |
| Ftp | FTP-акаунти й зв’язки із сайтами/компаніями | Models/FtpAccount.php; Http/Controllers/FtpAccountController.php |
| Hosting | Хостинги, акаунти й зв’язки із сайтами | Models/Hosting.php, HostingAccount.php; Http/Controllers |
| Audit | Перегляд журналу | Http/Controllers/ActivityLogController.php; модель ActivityLog у Shared |

## Інтерфейс і реєстрація

- routes/web.php, routes/api.php: підключення маршрутів модулів.
- app/Providers/AppServiceProvider.php: конфігурації; AuthServiceProvider.php: перевірки доступу.
- app/Console/Kernel.php: реєстрація команд; schedule() не запускає моніторинг.
- resources/views/layouts/portal.blade.php: каркас порталу.
- resources/views/portal/sites/add.blade.php: створення; show.blade.php: картка, редагування й керування; presentation-fields.blade.php: параметри показу.
- resources/views/portal/monitoring/index.blade.php: overview/list/create; show.blade.php: Monitor config, incidents, selected diagnostics, 7/30-day summaries; settings.blade.php: global typed settings; fields.blade.php: Monitor config; options.blade.php: primary Site inclusion/status.
- Інші шаблони: resources/views/portal/{support,users,profile,companies,statuses,ftp,hosting,hosting-accounts,activity,trash}.
- lang: переклади; частина текстів моніторингу зараз безпосередньо у PHP/Blade.

## Історична схема V1 (замінена V2)

Джерело: database/migrations/2026_09_09_000002_create_site_monitoring.php. Робота через Query Builder, без окремих Eloquent-моделей цих таблиць.

| Таблиця | Дані |
| --- | --- |
| monitoring_states | Один поточний стан на сайт, послідовні невдачі, минула/наступна перевірка |
| monitoring_checks | Результат, HTTP, тривалість, помилка, manual, checked_at |
| monitoring_daily | Денна кількість перевірок, успішних і планових |
| monitoring_incidents | Відкриття/закриття, причина й результат закриття |
| monitoring_runs | Прогони cron, стан, кількість сайтів, час, помилка |
| monitoring_settings | Глобальні recipient_ids у записі id=1 |
| monitoring_notifications | Подія/одержувач, спроби, повтор, відправлення/скасування |

Міграція 2026_09_14_000001_add_recovery_and_control_history.php додає журнал керування та відновлення обробки Telegram.

## Історичні доповнення V1, 19 вересня (замінені V2)

- Monitoring/Services/SiteProbe.php: HTTP/контент, помилки, транспортні метрики й сертифікат.
- Monitoring/Services/MonitoringOptions.php: валідація параметрів та відповідальних.
- Monitoring/Services/MonitoringHistory.php: активні періоди, злиття інтервалів, денні гістограми.
- Monitoring/Services/MonitoringReport.php: календарні межі, часова доступність/покриття, агрегування.
- Нові таблиці monitoring_periods, monitoring_spans, monitoring_metrics зберігають довгострокову історію; схема у міграції 2026_09_19_000001.
- Шаблони monitoring/options.blade.php і monitoring/report.blade.php спільні для форм/звітів; sites/operation-mode.blade.php — режим сайту.
- tests/Feature/MonitoringPatchTest.php: 18 нових сценаріїв.
- Поточна поведінка й список файлів — [MONITORING-PATCH.md](MONITORING-PATCH.md).

## Тести для відповідних змін

Це орієнтири для читання, а не твердження про повне покриття.

| Область | Файли |
| --- | --- |
| Моніторинг V2 | tests/Feature/MonitoringV2Test.php, MonitoringV2MigrationTest.php; tests/monitoring-v2-mysql.php; ancillary regression у PatchRecoveryTest.php |
| Керування | tests/Feature/ChildControlIntegrationTest.php, PatchRecoveryTest.php |
| Права | tests/Feature/PortalPermissionsTest.php, AccessAuthorizationTest.php |
| Вихідний HTTP | tests/Feature/OutboundSecurityTest.php |
| Access-бот | tests/Feature/TelegramAccessWebhookTest.php |
| Підтримка | tests/Feature/SupportDeliveryTest.php |
| Картки/доступи | tests/Feature/PortalDetailsTest.php, tests/Unit/SitePasswordTest.php |

Команда: php vendor/bin/phpunit. Налаштування безпечного тестового середовища — phpunit.xml і tests/TestCase.php.

## Документи

- [MONITORING-V2-IMPLEMENTATION.md](MONITORING-V2-IMPLEMENTATION.md): актуальна schema/services/storage/validation/exact files; legacy classes History/Report/SiteProbe/SiteMonitor/Notifications замінені.
- [MONITORING-V2-DEPLOY.md](MONITORING-V2-DEPLOY.md): актуальний cutover/runbook/runtime verification; єдина нова migration 2026_10_05_000001_install_monitoring_v2.php. Historical migrations збережені.

- [MONITORING-DEPLOY.md](MONITORING-DEPLOY.md): точний перелік для FTP, міграція, cron і команди.

- [PROJECT-CONTEXT.md](PROJECT-CONTEXT.md): призначення, поняття й потоки.
- [MONITORING-PLAN.md](MONITORING-PLAN.md): поточний стан і пропозиції.
- [DEPLOYMENT.md](DEPLOYMENT.md): розгортання, cron, боти, міграції.
- [CHILD-SITES.md](CHILD-SITES.md): дочірня інтеграція.
- [PATCH-VALIDATION.md](PATCH-VALIDATION.md): історичний звіт перевірки.
