# Monitoring V2 implementation

Дата: 2026-10-05. Локальний patch; production deployment і hosting cron не виконувалися.
Основний contract: [MONITORING-V2-ARCHITECTURE.md](MONITORING-V2-ARCHITECTURE.md). Попередні patch/audit документи описують V1.

## Реалізовано

Monitor-centric HTTP, TCP, completion-only Heartbeat; один Site → багато monitors. Primary HTTP має `slot=primary_http`, unique `(site_id,slot)`; додаткові slots NULL. Site checkbox — input/projection relation, не persisted config. Нові Sites включають primary за замовчуванням незалежно від Prod/Dev. Explicit URL не переписується після зміни Site URL; усі follow-Site HTTP отримують нове спостереження. Site pause/soft delete закриває період/інцидент без fake recovery; restore починає UNKNOWN. Remote-control write/protocol не змінені.

Typed singleton settings: interval 300s, timeout 6s, failure threshold 2, retry 60s, recovery threshold 1; SSL 14/3 days, renewal ON; validation і bounded retention. Nullable overrides; global interval change змінює лише inherited pull monitors, Heartbeat job interval/deadline незалежні. TCP ports 80/443 default; змінювати дозволені порти може тільки Admin. PM має monitoring.write для інших налаштувань; Developer — лише primary inclusion у дозволеній Site формі й manual diagnostic з чинним sites.read.

DB claim під коротким Monitor→State lock: token, dispatch sequence, lease, captured Monitor/settings revision. Probe поза transaction, fenced commit один раз. Lease 120s; late/consumed/config-changed results rejected. Earliest next_due_at ordering; deterministic initial phasing, planned-slot cadence, пропущені slots пропускаються. File lock команди лише додаткова оптимізація. MySQL 8/SKIP LOCKED не потрібні. Dispatcher послідовний і bounded; parallel processes безпечні через DB claims, але capacity actual hosting потребує canary.

Перший failed auto result → UNKNOWN/confirming_failure; другий → DOWN. Incident started_at = first failure, detected_at = confirmation; confirmed downtime не backdated. Recovery threshold підтримується. UP/DOWN/PLANNED pull observation діє максимум 2 effective intervals, далі UNKNOWN. UNKNOWN не включається в uptime denominator. PLANNED — лише read-only confirmed disabled Site із відповідною policy у follow-Site HTTP; Monitoring не надсилає control commands.

Manual probe не змінює automatic schedule/state/incident/event/rollup. Окремі counters забезпечують максимум 20 manual dispatches/Monitor/Kyiv day навіть коли діагностика не зберігається через hard cap.

## Target schema

| Table | Contract |
| --- | --- |
| monitoring_monitors | Site ownership, slot, type, config, nullable overrides, recipient policy, revision, activated_at |
| monitoring_states | Один row/Monitor; state/candidate counters, accounting cursor, due/deadline, claim/lease/sequence, active episode, manual quota |
| monitoring_periods | Expected active periods/generation |
| monitoring_spans | Compressed state durations/generation |
| monitoring_rollups | Kyiv day/month; duration microseconds, latency sums/min/max/histogram, finalized |
| monitoring_diagnostics | Selected bounded evidence, day, expiry |
| monitoring_incidents | started/detected/recovered/closed, reason, cause, bounded candidate/confirmation evidence |
| monitoring_events | Immutable event snapshot, sequence, occurred_at; nullable incident_id |
| monitoring_deliveries | Persistent recipient state, attempts, lease/token, next attempt, terminal timestamps |
| monitoring_default_recipients | Global recipients |
| monitoring_monitor_recipients | Explicit Monitor recipients |
| monitoring_settings | Typed singleton and checks_revision; narrow allowed-port array |
| monitoring_runs | Operational command summary |
| monitoring_certificates | Latest reliable configured-origin certificate, generation, expiry, verified/error, persistent alert markers |
| monitoring_heartbeat_credentials | Public id, SHA-256 secret hash, expiry/revocation/rotation |
| monitoring_site_preferences | Display timezone override |
| monitoring_company_preferences | Display timezone fallback sidecar; no Company business changes |
| monitoring_v2_install | Durable cutover stage/activation marker |
| monitoring_v2_manifest | Bounded per-Site initialization snapshot; emptied after successful cutover |

Усі V2 instant columns — DATETIME(6), explicit UTC strings. Monitoring MySQL session UTC. Application timezone не змінено. Durations — integer microseconds; UI converts to seconds. Calendar keys/boundaries Europe/Kyiv, DST-aware; display resolver Site→Company→Kyiv. Other display zones не створюють інші денні статистики. Formatters parse UTC→convert once; CLI/web використовують той самий contract.

## Storage і retention

Routine success: update State, extend compressed span, increment day latency histogram; diagnostics=0, events=0, incidents=0. 1000 successes — один State, кілька calendar rows і максимум initial UNKNOWN + UP spans (окрема generation у MySQL exercise), без raw success stream.

Failed diagnostics: first/confirmation/error signature change або sparse sample раз на 3h; максимум 8/day. Shared ceiling 32/day і 448 live rows/Monitor під row lock, навіть якщо pruning зупинився. Quota не блокує transitions/accounting/incident evidence; suppressed counter без extra spam events. Manual ≤20/day. Не зберігаються response bodies, cert chains, query tokens або credentials.

Spans зливаються лише за однакового state/generation і суміжних меж. Accounting cursor запобігає повторному нарахуванню. Fresh tail у report обчислюється read-only. Daily aggregates = duration, uptime UP/(UP+DOWN), coverage окремо, successful HTTP response/TCP connect latency; Heartbeat без fake latency. p95 — upper histogram bucket approximation, overflow явно окремий.

Maintenance матеріалізує unknown gaps, finalizes closed Kyiv days, перевіряє duration equality перед detail pruning. Default detail 90d; target 10,000 retained spans викликає earlier pruning лише завершених Kyiv days. Flapping усередині незавершеного дня може тимчасово перевищити цей target: факти не merge в fake averages. Detail watermark показаний у UI. Open period/span prefix обрізається, решта зберігається. Daily 3y; старі цілі місяці compaction transaction→monthly→delete daily, idempotent; monthly long-term. Retention service обробляє bounded fair batches за найстарішим cursor.

## SSL / Heartbeat / TCP / Telegram

SSL збирає verified PEM metadata з HTTP transfer stats тільки для configured HTTPS origin. Redirected чужий hostname не підміняє target. Fingerprint + later expiry → renewal; first ≤3 days → лише critical. Markers у projection переживають pruning events/deliveries. Відсутні certinfo/TLS errors не стирають reliable projection і не є renewal. Threshold alerts оцінюються на reliable observations; age/error показані у detail. Якщо hosting cURL не дає verified PEM certinfo, lifecycle буде unavailable — це обов'язковий runtime gate, а не fabricated SSL state.

Heartbeat endpoint: `POST /api/monitoring/heartbeat` тільки HTTPS, `Authorization: Bearer PUBLIC_ID.SECRET`; optional `job_run_id`. Secret random 256-bit, shown once, hash at rest. Rotation overlap 0–3600s; максимум current+previous key; revoke. Initial UNKNOWN/deadline activation+interval+grace; ping UP/deadline received_at+interval+grace. Duplicate latest job_run_id не продовжує deadline. Evaluator locks same state, створює один missed episode; наступний valid ping recovery. Missed DOWN expires after 120s без evaluator. Latest-run idempotency не є необмеженим replay journal; signed/nonces/start-fail workflows поза V1.

TCP public-only, admin allowed port, всі A/AAAA перевіряються existing public policy, mixed/private/metadata/mapped ranges blocked, numeric pinned IP connect. HTTP зберігає SafeHttp redirects/TLS/address/body-size policy. Monitoring-only DNS worker (`dns-resolve.php`, proc_open array argv без shell) дозволяє завершити blocked DNS за спільним deadline; transfer/connect отримує remaining budget. PHP CLI/proc_open і TLS certinfo треба перевірити на hosting. Інші outbound/control consumers не використовують цей worker; старий SafeHttp contract зберігається через optional totalBudget parameter.

Events immutable: names, sanitized target, cause/evidence, transition time/timezone, episode start/detection. Delivery rechecks current eligibility/policy/enabled Site, pending→claimed→sent/retry_wait/cancelled/failed_terminal. Retry 5→10→20→30min, max event age 24h, max 12 attempts, 90s lease, fair due ordering. Exactly-once Telegram не гарантується: crash після external success до ack може дублювати повідомлення. Telegram Access transport/linking і Support transport/business logic не змінені.

## Cutover і збереження даних

Єдина нова міграція: `database/migrations/2026_10_05_000001_install_monitoring_v2.php`.
Перед DROP persisted manifest переносить тільки non-deleted Site enabled intent, explicit URL/status/content/recipients. Timeout/threshold/interval/history/timestamps/revision старого Site не переносить; existing primary interval=NULL, state UNKNOWN, periods від V2 activated_at. Global explicit V1 recipient IDs також переносяться в default recipient relation, щоб inherit Sites зберегли відповідальних; перевірте eligibility у V2 Settings.

Exact legacy table allowlist: monitoring_notifications, monitoring_checks, monitoring_daily, monitoring_metrics, monitoring_periods, monitoring_spans, monitoring_states, monitoring_incidents, monitoring_runs, monitoring_settings. Чотири raw/legacy tables checks/daily/metrics/notifications прибрано; решта recreate з новим Monitor contract. Wildcard DROP, mixed migration rollback, migrate:fresh, seeds/key reset відсутні. Historical migrations збережені для fresh install.

Після initialization verification прибираються тільки дев'ять Site columns: monitoring_enabled, monitoring_interval, monitoring_timeout, monitoring_failure_threshold, monitoring_status_codes, monitoring_content, monitoring_url, monitoring_recipient_ids, monitoring_revision. sites не recreate/truncate. IDs, URL, credentials, company/FTP/Hosting relations, users, Support, control fields/history/revisions зберігаються. Втрата V1 Monitoring history — свідомий contract. Повтор після MySQL DDL відновлює stage, не вдруге resets V2. down() відмовляє: rollback потребує coordinated code+DB restore.

Ancillary Support interrupted processing і pending site_control_attempts cleanup залишені в RunMonitoring wrapper із попередніми service methods/semantics.

## Validation

Повний PHPUnit на PHP 8.3/SQLite; результати фінального запуску нижче. HTTP/Telegram/socket probes підмінені; live зовнішніх повідомлень не надсилали. Покриті 1000 successes, sustained failure, stale claims/revisions/consumption, candidate/recovery, manual isolation/quota, phasing/intervals, UTC/Kyiv/New York/DST23/25/PHP non-UTC/CLI-web, credentials/heartbeat rotation/replay, TCP public/private/mixed/metadata/IPv6, recipient policies/retry, immutable events, SSL severity/renewal/error/target/dedup після pruning, retention/monthly/idempotency, UI/permissions, business preservation/partial migration retry.

Ізольований MySQL 5.7.44 strict mode: fresh historical path→V2 cutover, симульоване missing CREATE/partial Site DROP stage, retry, business fields/credential preservation, UTC session/DATETIME(6), 1000 successful commits = 1000 samples, 3 compressed spans, 0 diagnostics; PASS. Два PHP процеси concurrent claim = один claimed, один busy; PASS. Ізольований server зупинено після перевірок. `tests/monitoring-v2-mysql.php` жорстко вказує лише local 127.0.0.1:33927 / isolated DB і відмовляє на nonempty fresh test database.

## Наступний UX patch

Глобальний layout і Support/FTP/Hosting/Users UI не перероблені. Пізніше: styling/dashboard graphs, richer filters, maintenance windows, Status Pages publication/access policy, standalone ownership, detailed intraday UX/replay workflows. Базовий V2 overview/list/config/settings/diagnostics/incidents/7–30-day calendar summaries уже usable.

Deployment/runbook: [MONITORING-V2-DEPLOY.md](MONITORING-V2-DEPLOY.md).

## Exact files changed

| Operation | Path |
| --- | --- |
| UPDATE | `AGENTS.md` |
| UPDATE | `app/Modules/Monitoring/Console/RunMonitoring.php` |
| UPDATE | `app/Modules/Monitoring/Http/Controllers/MonitoringController.php` |
| DELETE | `app/Modules/Monitoring/Services/CheckAlreadyRunning.php` |
| DELETE | `app/Modules/Monitoring/Services/MonitoringHistory.php` |
| DELETE | `app/Modules/Monitoring/Services/MonitoringNotifications.php` |
| UPDATE | `app/Modules/Monitoring/Services/MonitoringOptions.php` |
| DELETE | `app/Modules/Monitoring/Services/MonitoringReport.php` |
| DELETE | `app/Modules/Monitoring/Services/SiteMonitor.php` |
| DELETE | `app/Modules/Monitoring/Services/SiteProbe.php` |
| UPDATE | `app/Modules/Monitoring/config.php` |
| UPDATE | `app/Modules/Monitoring/routes/web.php` |
| UPDATE | `app/Modules/Shared/Http/SafeHttp.php` |
| UPDATE | `app/Modules/Site/Http/Controllers/SiteController.php` |
| UPDATE | `app/Modules/Site/Models/Site.php` |
| UPDATE | `app/Modules/Site/Services/SiteMetadataService.php` |
| UPDATE | `docs/MODULE-MAP.md` |
| UPDATE | `docs/PROJECT-CONTEXT.md` |
| UPDATE | `resources/views/portal/monitoring/index.blade.php` |
| UPDATE | `resources/views/portal/monitoring/options.blade.php` |
| DELETE | `resources/views/portal/monitoring/report.blade.php` |
| UPDATE | `resources/views/portal/monitoring/show.blade.php` |
| UPDATE | `routes/api.php` |
| DELETE | `tests/Feature/MonitoringPatchTest.php` |
| DELETE | `tests/Feature/MonitoringTest.php` |
| UPDATE | `tests/Feature/PatchRecoveryTest.php` |
| UPDATE | `tests/Feature/PortalDetailsTest.php` |
| ADD | `app/Modules/Monitoring/Console/MonitoringMaintenance.php` |
| ADD | `app/Modules/Monitoring/Console/VerifyMonitoring.php` |
| ADD | `app/Modules/Monitoring/Http/Controllers/HeartbeatController.php` |
| ADD | `app/Modules/Monitoring/Http/Middleware/MonitoringReady.php` |
| ADD | `app/Modules/Monitoring/Models/Monitor.php` |
| ADD | `app/Modules/Monitoring/Services/HttpProbe.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorCertificates.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorEvents.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorHeartbeat.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorHistory.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorManager.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorRetention.php` |
| ADD | `app/Modules/Monitoring/Services/MonitorRunner.php` |
| ADD | `app/Modules/Monitoring/Services/MonitoringAddressPolicy.php` |
| ADD | `app/Modules/Monitoring/Services/MonitoringInstaller.php` |
| ADD | `app/Modules/Monitoring/Services/MonitoringSettings.php` |
| ADD | `app/Modules/Monitoring/Services/MonitoringTime.php` |
| ADD | `app/Modules/Monitoring/Services/TcpProbe.php` |
| ADD | `app/Modules/Monitoring/dns-resolve.php` |
| ADD | `app/Modules/Monitoring/routes/api.php` |
| ADD | `database/migrations/2026_10_05_000001_install_monitoring_v2.php` |
| ADD | `docs/MONITORING-V2-DEPLOY.md` |
| ADD | `docs/MONITORING-V2-IMPLEMENTATION.md` |
| ADD | `resources/views/portal/monitoring/fields.blade.php` |
| ADD | `resources/views/portal/monitoring/settings.blade.php` |
| ADD | `tests/Feature/MonitoringV2MigrationTest.php` |
| ADD | `tests/Feature/MonitoringV2Test.php` |
| ADD | `tests/monitoring-v2-mysql.php` |

## Фінальний результат перевірок

- Full PHPUnit: **59 tests, 2710 assertions — PASS** (PHP 8.3.30, SQLite :memory:).
- Isolated MySQL 5.7.44 strict: cutover/mapping/partial-DDL/credential preservation, 1000 successes without diagnostics і two-process exclusive claims — PASS.
- Laravel Pint для змінених PHP файлів та git diff --check — PASS.
- Production/hosting/real Telegram/cron не виконувалися. Runtime canary gates — у deployment doc.
