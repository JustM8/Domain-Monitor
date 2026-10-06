# Пам’ятка для задач adm.chk

Актуалізовано 2026-10-05 після локального Monitoring V2 patch.

- Фактичний код: `D:\OSPanel6\home\adm.chk`; `C:\Users\Mate\Documents\ChatGPT\adm.ck` — лише папка задачі.
- Laravel 10 / PHP 8.2+, Blade/Bootstrap/Vite, модулі app/Modules. Почни з docs/PROJECT-CONTEXT.md і docs/MODULE-MAP.md, далі лише потрібна область.
- Monitoring V2 contract: docs/MONITORING-V2-ARCHITECTURE.md; implementation/exact files: docs/MONITORING-V2-IMPLEMENTATION.md; deployment: docs/MONITORING-V2-DEPLOY.md. V1 docs залишаються історичними; не починай повторний повний audit.
- Site — business registry; IDs/credentials/company/FTP/Hosting/control history не змінювати через Monitoring. site_type/environment/business status/remote_control_enabled/is_active/observed availability — різні поняття.
- Monitoring authoritative config — monitoring_monitors. Site monitoring_enabled — лише input/projection primary_http relation; дев'ять legacy Monitoring columns видаляються V2 cutover. Site може мати багато HTTP/Heartbeat/TCP monitors.
- New Sites primary HTTP default enabled для Prod/Dev; legacy intent збережено при cutover. Primary slot unique(site_id,slot), additional slot NULL. Existing intervals NULL→global 300s. Explicit URLs не слідують Site URL.
- Monitoring: DB-authoritative claim/revision/sequence/lease; probe поза transaction, fenced single commit. next_due_at UTC — scheduler authority; failure retry60s, phasing/catch-up skip. Manual діагностика не змінює automatic state/history/incidents/events.
- Failure default2: перша UNKNOWN/confirming, друга DOWN; incident start first failure, detected confirmation. UP/DOWN freshness2×interval, gaps UNKNOWN. PLANNED тільки read-only confirmed Site disabled із explicit follow-Site policy; remote commands Monitoring не надсилає.
- Storage: routine successes без raw rows; compressed spans, Kyiv day/month rollups і histogram. Uptime=UP/(UP+DOWN), coverage окремо. Diagnostics atomic caps32/day і448 live/Monitor; failed≤8/day, manual dispatch≤20/day. Quota не губить incidents/accounting.
- UTC DATETIME(6)/explicit MonitoringTime contract; canonical calendar Europe/Kyiv; Site→Company→Kyiv display-only timezone. Global app.timezone не змінювати. parse UTC→convert once; DST day не завжди86400s.
- SSL — verified configured-origin HTTP certificate projection/generation/14d/3d/renewal markers; renewal ON. Missing certinfo не стирає reliable data. Hosting verified PEM certinfo — runtime gate.
- Heartbeat HTTPS POST /api/monitoring/heartbeat із bearer header, random256bit secret shown once/hash at rest, rotation/revoke; job interval+grace незалежні від pull settings. Initial UNKNOWN; received server UTC freshness; latest job_run_id duplicate не продовжує deadline.
- TCP лише public targets/admin allowed ports/all A+AAAA checked/numeric pinned connect. HTTP SafeHttp/TLS/outbound policy збережені. Monitoring-only bounded DNS worker потребує proc_open і PHP CLI; MONITORING_PHP_CLI за потреби. Control/інший HTTP worker не використовують.
- monitoring:run зберігає external ancillary Support interrupted recovery і pending site_control_attempts cleanup. Не переносити їх бізнесову логіку до V2.
- monitoring:maintenance --deliver / --prune — окремі bounded budgets; monitoring:verify --save / --baseline — read-only business counts/hash/credential verification. Scheduler Laravel порожній; recommended cron у deployment doc. Development задача cron не вмикає.
- Access linking/auth/webhook, Support, Users, FTP/Hosting, Audit, Company і child-control protocol не рефакторити. Права лишаються Shared/Support/PortalAccess.php; PM monitoring.write, Developer Site primary integration без sites.control.
- Cutover migration 2026_10_05_000001_install_monitoring_v2.php має exact legacy table allowlist, durable manifest/stage і partial-DDL retry. Не видаляти markers, не робити wildcard DROP/migrate:fresh/mixed down/global seed/UserSeeder/key:generate. APP_KEY зберігати. V1 statistics не переноситься.
- Тести: `D:\OSPanel6\modules\PHP-8.3\php.exe -d sys_temp_dir=C:\Users\Mate\Documents\ChatGPT\adm.ck vendor/bin/phpunit`; SQLite :memory:, HTTP/Telegram/socket підмінені. Ізольований MySQL5.7.44 cutover/partialDDL/1000-success/concurrent claims перевірено; tests/monitoring-v2-mysql.php тільки localhost33927 isolated DB.
- Production DB/hosting/real Telegram/cron не змінені. Після суттєвих змін актуалізувати профільні docs; повний UX/Status Pages publication — наступна задача.

Локальний результат V2: 59 tests, 2710 assertions; isolated MySQL5.7.44 і concurrent claims PASS. Exact 55-file inventory у implementation doc.
