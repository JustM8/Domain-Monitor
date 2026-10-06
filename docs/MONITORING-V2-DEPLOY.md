# Monitoring V2 deployment

Локальний patch від 2026-10-05. Це інструкція для оператора; production deployment/cron не виконані. Міграція свідомо видаляє V1 Monitoring history, але не бізнес-дані. Не запускайте старий код із V2 schema.

## Before

1. Повний consistent DB backup і code backup, перевірений restore на копії. Приватно зберегти `.env`, незмінний `APP_KEY`, current deployment revision. Не друкувати keys/passwords у logs.
2. `php artisan migrate:status`: усі попередні міграції, включно з 2026_09_19_000001, мають бути applied. V2 path не замінює відсутні prerequisites.
3. Hosting PHP≥8.2, MySQL≥5.7 (локально перевірено 5.7.44 strict). curl, openssl, PDO MySQL, stream_socket_client, proc_open і доступний PHP CLI для DNS worker. У `.env` встановити `MONITORING_PHP_CLI=/absolute/path/to/php`, якщо web PHP_BINARY/PHP_BINDIR не вказує на робочий CLI. Перевірити CLI/web runtime окремо.
4. Egress public HTTP(S), DNS, configured TCP ports, Telegram API; valid CA/TLS verification. cURL handler stats повинні містити verified PEM `certinfo[0].Cert` для SSL lifecycle. Не вимикати TLS verify.

## Stop / coordinated upload

Зупинити тільки Monitoring cron і Monitoring maintenance workers, дочекатися завершення in-flight V1/V2 процесів. Не змінювати Telegram webhook/Support cron. Коротко заборонити Site create/edit/delete/restore і Monitoring settings writes під час extraction/cutover; Support може працювати. Не запускати old і new writers разом.

Завантажити всі runtime файли з exact inventory нижче, видалити тільки перелічені obsolete файли. Зберегти .env/APP_KEY. Якщо autoloader authoritative: `composer dump-autoload --optimize` зі встановленими залежностями; dependencies не змінювались. Clear лише відповідні Laravel caches:

```sh
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan monitoring:verify --save=/private/outside-webroot/monitoring-v2-before.json
```

Нова verify команда працює до cutover у --save mode. Snapshot містить counts/hashes business rows, credentials тільки всередині hashes. Перевіряйте actual counts/IDs/relations і приватний backup; поточний PHP-код не забезпечує DB backup.

## Migration

```sh
php artisan migrate --path=database/migrations/2026_10_05_000001_install_monitoring_v2.php --force
php artisan monitoring:verify --baseline=/private/outside-webroot/monitoring-v2-before.json
php artisan migrate:status
```

При частковому MySQL DDL повторити ТУ САМУ migrate command після виправлення причини. Не видаляти monitoring_v2_install/manifest markers. States `extracted/resetting/schema/initialized/complete` дозволяють forward retry. Після complete повтор нічого не resets. Missing prerequisites/corrupt manifest — stop і forward repair/backup restore, не global reset.

Business verify порівнює counts+hashes sites (без видалених Monitoring columns), users, companies, FTP/Hosting/accounts/relations, revisions, control attempts, audit і Support tables/receipts. Перевіряє decryptability та primary/state counts. Якщо живі Support/webhooks легітимно змінювали свої rows між baseline і verify, команда може повідомити відмінність: дослідити конкретні записи/время, не ігнорувати business differences автоматично. Site IDs/company relations/credentials/is_active/control versions/confirmed state/history повинні збігтися з baseline. Не запускати db:seed/UserSeeder/key:generate/migrate:fresh/старі mixed down().

Після verification повернути Site writes. У Monitoring → Settings перевірити перенесені global default responsible users, explicit recipients та inherit/explicit-empty/mute. Existing primary interval NULL і UNKNOWN, old disabled intent лишається disabled. Soft-deleted Sites не ініціалізуються до restore.

## Canary — до scheduled cron

Використати окремий контрольований Site й власного linked Admin/PM recipient. Не перемикати remote control для симуляції outage.

- HTTP expected 200: запустити `php artisan monitoring:run` вручну після phased due; UP і правильний next due. Manual check — лише diagnostic, не incident.
- Змінити test endpoint на 503. Перший auto probe UNKNOWN/confirming, retry 60s, без down Telegram; наступний due probe DOWN/один incident/одна down event. `php artisan monitoring:maintenance --deliver` → власний Telegram down.
- Повернути 200, наступний auto due → UP/closed recovered/одна recovery event. Звірити actual UTC DB event time → Kyiv UI/Telegram один conversion; New York display не змінює calendar.
- Healthy routine successes не збільшують monitoring_diagnostics/check rows. monitoring_checks не існує; State один, spans compressed, rollup samples ростуть. Перевірити UNKNOWN після зупинки runner довше 2 intervals.
- HTTPS: fingerprint/not_before/not_after/verified_at з configured target; certinfo unavailable/error показаний, без invented renewal. Перевірити SSL 14/3/renewal на контрольованому short-lived certificate; не вимикати TLS verification для цього.
- Heartbeat: створити Backup monitor, issue secret один раз, HTTPS POST з bearer у header і optional JSON job_run_id. Не вставляти secret у URL/logs. Повтор того самого latest job_run_id не продовжує deadline; missed → down; valid ping → recovery. Rotation/revoke і pause eligibility.
- TCP: дозволений public hostname+port на контрольованому target. Перевірити successful connect latency й failure; private/loopback/mixed/metadata блокуються. Не сканувати internal DB/Redis.
- Перевірити actual web/CLI DNS worker launch/timeout/termination, stream_socket_client egress, CA/certinfo і Telegram. Перевірити due lag/lease recovery, batches і тривалість на hosting capacity; локальний тест не доводить hosting throughput.

Після canary відновити тільки Monitoring scheduled jobs. При runtime failure лишити cron stopped і forward repair; code-only rollback після reset не працює.

## Recommended cron

Замість placeholders використати перевірені absolute CLI PHP і portal paths. Operator створює ці cron entries; patch їх автоматично не вмикає.

```cron
* * * * * cd /absolute/portal && /absolute/php artisan monitoring:run >> /private/logs/monitoring-run.log 2>&1
* * * * * cd /absolute/portal && /absolute/php artisan monitoring:maintenance --deliver >> /private/logs/monitoring-delivery.log 2>&1
*/5 * * * * cd /absolute/portal && /absolute/php artisan monitoring:maintenance --prune >> /private/logs/monitoring-prune.log 2>&1
```

Dispatcher default 45s budget/100 candidates, with heartbeat evaluator and preserved ancillary Support/control cleanup. Delivery окремий 45s/20-candidate budget, 90s leases; prune 100 monitors/pass. Large fleet/outage може потребувати capacity tuning MONITORING_BATCH_SIZE/MONITORING_MAX_SECONDS і частоти prune; cadence не catch-up stream. Стежити за oldest due/queue age, failed runs, diagnostics_suppressed, detail watermark. Telegram duplicates після crash possible; exactly-once не обіцяється.

## Rollback

Зупинити Monitoring jobs. Restore coordinated code+consistent DB backup, original .env/APP_KEY. Врахувати легітимні Support/business writes після backup — не відновлювати live DB поверх них без окремого reconciliation plan. V2 down() deliberate refusal; forward repair рекомендований після успішного cutover. Backup rollback не виконано цією development задачею.

## Exact upload / delete inventory

Перелік додається нижче з фактичного git diff. Tests/docs можна завантажити як review artifacts; для runtime потрібні app/routes/migration/views. Historical migrations не видаляти. `docs/MONITORING-V2-ARCHITECTURE.md` уже існував як наданий contract, збережений.

### Upload runtime files

- `app/Modules/Monitoring/Console/RunMonitoring.php`
- `app/Modules/Monitoring/Http/Controllers/MonitoringController.php`
- `app/Modules/Monitoring/Services/MonitoringOptions.php`
- `app/Modules/Monitoring/config.php`
- `app/Modules/Monitoring/routes/web.php`
- `app/Modules/Shared/Http/SafeHttp.php`
- `app/Modules/Site/Http/Controllers/SiteController.php`
- `app/Modules/Site/Models/Site.php`
- `app/Modules/Site/Services/SiteMetadataService.php`
- `resources/views/portal/monitoring/index.blade.php`
- `resources/views/portal/monitoring/options.blade.php`
- `resources/views/portal/monitoring/show.blade.php`
- `routes/api.php`
- `app/Modules/Monitoring/Console/MonitoringMaintenance.php`
- `app/Modules/Monitoring/Console/VerifyMonitoring.php`
- `app/Modules/Monitoring/Http/Controllers/HeartbeatController.php`
- `app/Modules/Monitoring/Http/Middleware/MonitoringReady.php`
- `app/Modules/Monitoring/Models/Monitor.php`
- `app/Modules/Monitoring/Services/HttpProbe.php`
- `app/Modules/Monitoring/Services/MonitorCertificates.php`
- `app/Modules/Monitoring/Services/MonitorEvents.php`
- `app/Modules/Monitoring/Services/MonitorHeartbeat.php`
- `app/Modules/Monitoring/Services/MonitorHistory.php`
- `app/Modules/Monitoring/Services/MonitorManager.php`
- `app/Modules/Monitoring/Services/MonitorRetention.php`
- `app/Modules/Monitoring/Services/MonitorRunner.php`
- `app/Modules/Monitoring/Services/MonitoringAddressPolicy.php`
- `app/Modules/Monitoring/Services/MonitoringInstaller.php`
- `app/Modules/Monitoring/Services/MonitoringSettings.php`
- `app/Modules/Monitoring/Services/MonitoringTime.php`
- `app/Modules/Monitoring/Services/TcpProbe.php`
- `app/Modules/Monitoring/dns-resolve.php`
- `app/Modules/Monitoring/routes/api.php`
- `database/migrations/2026_10_05_000001_install_monitoring_v2.php`
- `resources/views/portal/monitoring/fields.blade.php`
- `resources/views/portal/monitoring/settings.blade.php`

### Delete obsolete runtime files

- `app/Modules/Monitoring/Services/CheckAlreadyRunning.php`
- `app/Modules/Monitoring/Services/MonitoringHistory.php`
- `app/Modules/Monitoring/Services/MonitoringNotifications.php`
- `app/Modules/Monitoring/Services/MonitoringReport.php`
- `app/Modules/Monitoring/Services/SiteMonitor.php`
- `app/Modules/Monitoring/Services/SiteProbe.php`
- `resources/views/portal/monitoring/report.blade.php`

Review-only files/tests/docs — повний перелік у MONITORING-V2-IMPLEMENTATION.md. Existing architecture contract не входить до цього diff.
