# Monitoring Next Audit

Дата: 2026-10-05. Тип роботи: **READ-ONLY ARCHITECTURE / CODE AUDIT** локального поточного коду.

Позначення: **FACT** — підтверджено кодом, міграціями або зазначеною перевіркою; **INFERENCE** — висновок із фактів; **RECOMMENDATION** — пропозиція для окремої implementation-задачі. Runtime стан хостингу, дані робочої БД, справжня доставка Telegram і фактичний cron не перевірялися.

## 1. Executive Summary

**FACT.** Поточний Monitoring — один HTTP monitor на Site. Уже є per-site interval, timeout, failure threshold, expected statuses/content, check URL, незалежний monitoring_enabled, next_check_at, короткий retry, ручна діагностика, інциденти, persistent notification queue, time-based availability/coverage та довгострокова історія. Це перевірено в коді, а не лише в документації патча.

**FACT.** Company timezone, Site timezone override, динамічне успадкування global interval, SSL lifecycle alerts, Heartbeat, TCP і Status Pages відсутні у перевірених шляхах Monitoring. TLS expiry є persistent полем raw check, але немає незалежного certificate lifecycle state.

**FACT.** У Telegram formatter немає двох явних conversion UTC → Kyiv. Підтверджена інша помилка: cron UI інтерпретує UTC рядок як Kyiv. Семантичний UTC усіх історичних DB значень не доведений: частина writer використовує now(), MySQL session timezone не зафіксована конфігурацією. Причина заявленого Telegram +3h **Requires runtime verification**.

**INFERENCE.** Базу часової статистики слід зберегти. successful_checks / total_checks поверне стару некоректну для uptime семантику. Найбільша перешкода новим типам — повсюдна прив'язка state/history/incidents/notifications/reporting до site_id, а не відсутність агрегатів.

**RECOMMENDATION.** Обрати **Strategy B — Hybrid** із чітким шляхом подальшого зближення: сумісний Site HTTP adapter, окремі monitors для нових цілей, спільні правила часу/станів/history/events. Не створювати назавжди дві незалежні системи uptime. Спочатку перевірити UTC runtime і стабілізувати контракти, потім global interval та notification events, далі SSL/Heartbeat/TCP, після цього public projections. UX — окрема задача.

## 2. Audit Scope

**FACT. Перевірено:**

- Усі файли `app/Modules/Monitoring`: RunMonitoring; SiteMonitor; SiteProbe; MonitoringHistory; MonitoringReport; MonitoringNotifications; MonitoringOptions; CheckAlreadyRunning; MonitoringController; config; routes.
- Site: monitoring поля/casts/defaults/lifecycle hooks у Models/Site; monitoring виклики у SiteController і routes; reset/revision/target logic у SiteMetadataService. Remote control прочитаний лише в місцях визначення planned та захисту зміни цілі/від'єднання.
- Міграції `2026_09_09_000002_create_site_monitoring`, `2026_09_19_000001_extend_site_monitoring`; retirement старої схеми; пов'язані поля Site/environment/Company, recovery timestamps. Схему робочої БД не запитували.
- Monitoring Blade `index`, `show`, `options`, `report`; monitoring включення у Site add/show та operation-mode. Без аудиту layout/UX.
- SafeHttp, OutboundAddressPolicy, outbound/cache/app/database config; PortalAccess тільки для Monitoring; реєстрація конфігурації та порожній Laravel schedule.
- TelegramBotService тільки `sendMessage` та його HTTP transport/error result. Без linking, Access authorization, webhook чи видачі доступів.
- Company model/schema тільки relationship/timezone; прямого Site → SupportClient зв'язку в Site model немає. Інший Client module не досліджувався.
- Документи AGENTS, PROJECT-CONTEXT, MODULE-MAP, MONITORING-PATCH, MONITORING-PLAN, MONITORING-DEPLOY, DEPLOYMENT.
- MonitoringTest і MonitoringPatchTest; Monitoring-specific recovery test прочитаний; outbound address/redirect tests прочитані як evidence для security.

Свідомо не перевірялися Support tickets/analytics/attribution, FTP, Hosting, UserManagement, загальний Access/Support bot, remote-control subsystem поза вказаним перетином, production/staging та deployment.

**FACT. Валідація цього аудиту:** PHP 8.3.30; `vendor/bin/phpunit tests/Feature/MonitoringTest.php tests/Feature/MonitoringPatchTest.php` — **22 tests, 128 assertions, OK**, 36.060 s. phpunit.xml примусово задає SQLite `:memory:`, testing config-cache path, array cache/session, фіктивні bot credentials; Tests/TestCase забороняє stray HTTP, profile tests підміняють DNS/HTTP. Seeders не запускалися. Migration up/down у цих тестах стосується тільки тимчасової in-memory схеми. Це не перевірка MySQL, міжпроцесних locks або live Telegram.

Окрема read-only Carbon перевірка без bootstrap застосунку підтвердила приклади часу з §6. Application code/tests/старі документи не редагувалися. Git status недоступний через ownership/safe.directory; глобальну Git конфігурацію не змінювали. Для контролю незмінності використано SHA-256 fingerprint файлів app/config/routes/views/migrations/tests/старих docs.

## 3. Current Site ↔ Monitoring Architecture

**FACT.** `Site.php:44–61,78–98` та міграція 19 вересня задають:

| Поле | Фактична семантика |
| --- | --- |
| monitoring_enabled | Участь у cron; indexed boolean; незалежно від environment/control |
| monitoring_interval | Обов'язкове числове значення на Site; DB default 5; request 1–60 хв |
| monitoring_timeout | Request 2–10 с; DB/model default 6; на кожний HTTP hop |
| monitoring_failure_threshold | 1–5; default 2; поріг incident/alert, не поріг запису down history |
| monitoring_url | Nullable override; fallback до sites.url |
| monitoring_status_codes | Nullable JSON array; empty означає фінальний 2xx |
| monitoring_content | Nullable substring, до 255 символів; порожнє не перевіряється |
| monitoring_recipient_ids | Nullable array; порожнє успадковує global recipients |
| monitoring_revision | Версія параметрів для відкидання stale probe result |
| environment | prod/dev; default enabling у model/migration, не поточний критерій обходу |
| company_id | belongsTo Company; timezone не знайдено у model/schema |
| url | Адреса реєстру; зміна зачіпає HTTP history/control safeguards навіть за custom check URL |
| is_active / confirmed_state / control versions | Визначення planned за підтвердженим disabled; не measured availability |

Scheduling timestamps розміщені в monitoring_states, не в sites. Site має casts параметрів, але Query Builder monitoring rows не мають Eloquent datetime casts: timestamp приходить як рядок і парситься явно.

**FACT.** Потік: direct cron → RunMonitoring → due Sites → SiteMonitor → SiteProbe → SafeHttp → transaction з Site row lock → raw/state/daily/history/metrics/incident/event. HTTP виконується до DB transaction; enqueue всередині неї, Telegram delivery поза нею. Ручна check повертається після raw insert і не змінює автоматичні структури.

Site створення/restore відкриває monitoring period; pause/soft delete закриває period, reset state і відкриті incidents без recovery. Зміна цілі/status/content або interval/threshold reset history/state; timeout змінює revision, але не робить reset. URL/control зміни захищені підтвердженням active, щоб не залишити дитячий сайт вимкненим. Hard delete каскадно видаляє monitoring history.

**INFERENCE.** Сервіси типізовані Site; state має unique site_id; всі агрегати й incident subjects — Site. Другий HTTP `/health`, TCP чи backup heartbeat не можна додати до current state того самого Site без змішування результатів.

**RECOMMENDATION.** Site лишити business registry, Company — власник/контекст, Monitor — конкретний сигнал спостереження. Дозвіл керування не переносити в probe permissions. Planned для backup/TCP не успадковувати автоматично від is_active HTTP сайту.

## 4. Current Monitoring Data Model

**FACT.** Таблиці використовують Query Builder. FK на Site мають cascade delete; notification FK на incident/user також cascade. Нижче — actual write/read paths, не припущення за назвами.

| Table | Actual role | Write path | Read path | Long-term / raw | Recommendation |
| --- | --- | --- | --- | --- | --- |
| monitoring_states | Unique site current observation, failures, first_failed, scheduling, coverage cursor/observed interval | SiteMonitor::check; History::flush/reset | Runner due query; Controller; Report virtual tail | Mutable projection | Зберегти projection; майбутній subject monitor; freshness обчислювати |
| monitoring_checks | HTTP/technical/planned result, ms/error/url, manual, timings, cert expiry | SiteMonitor::check | Controller::show/latestCheck, diagnostic Blade | Raw; default 30 days | Selective retention; не джерело uptime |
| monitoring_daily | Counts checks/successful/planned, automatic only, Kyiv day | SiteMonitor::check | Поточні MonitoringReport/Controller/Blade не читають; Runner prune | Legacy/count aggregate; 365 days | Припиняти лише після перевірки consumers; не підміняти часовий звіт |
| monitoring_incidents | Confirmed outage, first failure opened_at, detected_at, closure/reason/error | SiteMonitor; History::reset | Controller; Report count; Notifications | Long-term, no cron prune | Subject abstraction; preserve IDs/close reasons |
| monitoring_runs | Cron operational progress, interrupted/completed/failed | RunMonitoring | Controller index, cron Blade | Operational; hardcoded 30 days | Коротко зберігати, додати діагностику backlog згодом |
| monitoring_settings | Singleton id=1, global recipient_ids | Controller::settings | Notifications::recipientIds | Configuration | Новий global interval має визначений contract; зараз тут його немає |
| monitoring_notifications | Incident/user/event outbox, attempts, next attempt, sent/cancelled | Notifications::enqueue/deliver; History::reset cancellation | Delivery; Controller pending count | Durable delivery records, no prune | Узагальнити event identity, додати lifecycle/retention |
| monitoring_periods | Активні UTC boundaries; denominator expected monitoring time | Migration; History::start/stop; Site lifecycle | Report range/build | Canonical expected-time history | Зберігати; invariant один відкритий period на subject |
| monitoring_spans | Стислі bounded up/down/planned observations, checked_url; unknown gaps implicit | History::flush | Report::build | Canonical persisted observed-time history | Зберігати, разом із periods/current tail; це не самодостатній ledger |
| monitoring_metrics | Kyiv-day successful auto latency samples/sum/max/histogram | History::sample | Report::build | Long-term latency aggregate | Зберегти, явно version/calendar; TCP latency окремо |

**INFERENCE.** Canonical availability — **periods + spans + bounded live state tail**. Окремо spans не відновлюють expected time/unknown. monitoring_metrics уже вирішує long-term latency без мільйонів raw successes. monitoring_daily лишилося сумісним записом кількості перевірок, не статистикою тривалості.

## 5. Requirement Overlap Matrix

**FACT / INFERENCE.** Позначка «Conflict» означає конфлікт запропонованого спрощення з current semantics, а не обов'язково bug.

| Requirement | Exists | Partial | Missing | Conflict | Recommendation |
| --- | --- | --- | --- | --- | --- |
| UTC/timezone | Explicit UTC check/state writes, Kyiv display/report | now() writers, unpinned MySQL session; cron parser bug | Runtime proof | Змішування naive local/UTC | UTC contract + runtime verification |
| Company timezone | — | Company relation | Field/resolver | Daily metrics мають один Kyiv calendar | Спочатку display timezone |
| Site override | — | Site context | Field/resolver | Та сама calendar проблема | Nullable override, тільки за потреби |
| Global interval | Config default 5 | Model/migration copy, UI literal 5 | Live inheritance | Existing values вже materialized | Explicit inherit/custom + effective interval |
| Per-site interval | 1–60 хв | — | — | Не треба дублювати поле | Зберегти під час переходу |
| next_check_at | Indexed due time | Queue/budget затримки | SLA guarantee | last_checked subtraction губить retry | Лишити canonical scheduling |
| Raw retention | 30 days | One policy for all results | Selective/incident retention | Early skip insert втратить TLS/diagnostics | Persist projections before prune |
| Daily | Auto count aggregate | Legacy compatibility | — | Counts замість duration — regression | Deprecation після consumer inventory |
| Spans | Bounded/compressed states | Потребують periods/tail | — | Не видаляти для «економії» | Reuse semantics |
| Metrics | Long-term histogram | Kyiv day only | Arbitrary timezone/intraday exactness | Re-bucketing після raw prune неможливий | Fixed calendar/versioned hourly future data |
| SSL alerts | Expiry collection + UI ≤14d | Raw persistence | Lifecycle state/14d/3d/renewal events | Current queue requires incident | Separate certificate state, generic events |
| Heartbeat | — | Reusable state/history concepts | Entity/token/ping/deadline | Site field не підтримує кілька jobs | Monitor entity + bounded events |
| TCP | — | Public IP classifier | Probe/type policy | HTTP origin/ports не підходять | Separate network target validation |
| ICMP | — | — | Probe | Hosting permissions/portability | Відкласти |
| Status Pages | Report/history data | Internal report service | Entity/access/projection | Public reuse Blade leaks internal data | Explicit opt-in read-only projection |
| Monitor abstraction | — | Probe/History/Report separation | monitor subject ownership | Unique Site state | Strategy B with migration seam |

## 6. Timezone Audit

### Поточний time flow

**FACT.** `config/app.php:73` має **UTC**, без env expression для timezone. `LoadConfiguration.php:47` встановлює PHP default timezone з app config. Read-only запуск локального PHP без Laravel показав `Etc/GMT-2`; **це не timezone Laravel після bootstrap** і не evidence про хостинг.

| Stage | Current behavior | UTC confidence |
| --- | --- | --- |
| Probe/cert | microtime для latency; expiry strtotime → epoch → Carbon UTC string | Explicit UTC, якщо expiry string правильно розпізнаний |
| checked_at / last_checked_at / first_failed_at / detected_at | CarbonImmutable::now('UTC'); first failure reused as UTC row string | Explicit app-side UTC |
| next_check_at | UTC probe start rounded to minute + delay | Explicit UTC |
| opened_at | first_failed_at при incident confirmation | Current path UTC; legacy records не доведені |
| incident closed_at on probe | Explicit UTC $at | Explicit UTC |
| History period start/end, reset closure | now() | Залежить від effective app timezone |
| Spans / coverage cursor | parse persisted state UTC; flush UTC until; DATETIME boundaries | UTC за умови правильних inputs |
| Runs started/finished; notification created/updated/sent/cancelled/retry | now(), now()->addMinutes | Залежить від app config; немає спеціальних casts |
| Runner due/prune queries | now() без UTC argument | Залежить від app config і DB session |
| Daily/metrics day | UTC $at або now() → monitoring.timezone → date | Kyiv calendar, timezone не записана в рядку |
| Report ranges | Local calendar → UTC query; DB strings parse UTC → local distribute | Correct для погодженого UTC і fixed Kyiv |
| Telegram | opened_at parse UTC → timezone(Kyiv) один раз; duration за epoch | Одне explicit display conversion |
| UI checks/incidents | parse UTC → Kyiv | Так само |
| UI last cron run | parse(started_at, Kyiv), без conversion | **Підтверджений defect** для UTC stored value |

**FACT.** Laravel DB `Connection::prepareBindings:741–750` лише форматує DateTime у database format, не переводить його в UTC. У config/database MySQL немає `timezone`; MySqlConnector задає session time_zone лише коли ключ присутній. Частина schema — TIMESTAMP, periods/spans — DATETIME. MySQL конвертує TIMESTAMP через session timezone; DATETIME календарні значення так не конвертує ([MySQL time zone documentation](https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html)). Фізичне UTC storage TIMESTAMP не доводить правильного app-side instant.

**FACT. Локальна read-only демонстрація:**

- Правильний formatter Telegram: stored `2026-10-05 12:00:00` UTC → `15:00:00 +03:00` Kyiv.
- Поточний cron UI parser: той самий рядок → `12:00:00 +03:00`; помилка на 3 години назад і хибне «cron давно не запускався».
- Якщо в DB лежить local wall time `15:00:00`, але reader вважає його UTC → `18:00:00 +03:00`. Це відтворює **механізм** +3h, але не доводить походження робочого запису.

**INFERENCE.** Підозра про подвійний UTC → Kyiv conversion у current MonitoringNotifications **не підтверджена**. Ймовірні класи причин: legacy local timestamps; інша effective cached app.timezone під час writer; різні MySQL session timezones між writer/reader; старий deployed code. Стабільна однакова non-UTC DB session іноді приховує помилку TIMESTAMP round-trip, але залишає іншу семантику DATETIME. Жоден із runtime сценаріїв не оголошується встановленою причиною.

**RECOMMENDATION.** Окремий fix має охопити cron parser, явний UTC timestamp contract усіх Monitoring writers/queries та перевірку UTC DB session. Перед будь-якою data migration звірити один відомий event з незалежним UTC часом і raw timestamps; не робити blanket «мінус 3h». Перевірити effective app timezone/config cache у CLI і web, PHP після bootstrap, MySQL `@@session.time_zone`, `@@global.time_zone`, `@@system_time_zone`, фактичні column types та наявність ON UPDATE. Тут ці production команди не виконувалися.

### Company/Site timezone та calendar

**FACT.** Site належить Company; поля timezone немає в обох перевірених моделях/міграціях. Прямий Site → Client relation **Not confirmed from current code**. Для цього audit достатній Company як business owner; не треба залучати SupportClient.

**RECOMMENDATION.** Resolver для display: Site override → Company timezone → Europe/Kyiv; nullable, IANA identifiers, validation списком timezone IDs. Company default достатньо для компанії з одним часовим контекстом; Site override корисний для географічно різних сайтів, але його потребу треба погодити. Для standalone monitor потрібен явний owner/display fallback, не виклик nullable Site без перевірки.

Timezone не змінює interval scheduling: 5 minutes — elapsed duration UTC. У першій фазі timezone впливає **тільки на display**. Calendar reports лишаються явно Kyiv. Якщо потрібні локальні календарні звіти компаній, timezone стає явним параметром report, а не глобально mutable config.

**INFERENCE.** UTC periods/spans можна розбити за новими local day boundaries. Existing monitoring_daily/metrics містять тільки Kyiv date: точне перерозкладання latency в іншу timezone після видалення raw checks неможливе. Змішаний портальний звіт потребує однієї явно обраної reporting timezone. Для нової системи варіанти: canonical UTC hourly latency buckets з обрізанням/обмеженням точності; timezone-versioned daily summaries; залишити fixed Kyiv календар. Hourly buckets самі по собі не дають точного arbitrary-minute clipping.

**FACT.** Є test 23-hour Kyiv DST day та histogram merge. Осінній 25-hour день, repeated hour, зміна timezone, MySQL session round-trip цими тестами не доведені.

**RECOMMENDATION.** Не використовувати fixed +2/+3 або 86400 як тривалість локального дня. Date boundaries будувати в IANA timezone, elapsed time рахувати epoch/UTC. Calendar cron expressions для Heartbeat потребують окремої DST політики; interval-only V1 простіший.

## 7. Scheduler Audit

**FACT.** RunMonitoring (`:20–61`) бере global cache lease `max_seconds + 180`, bounds budget 30–900 s, вибирає non-soft-deleted enabled Sites з null або due next_check_at. Null due йдуть першими, далі найстаріша due дата/site id. Batch bounded 1–500 (default 100); виконання **послідовне**, default pause 250 ms. Laravel schedule порожній. Direct щохвилинний cron — актуальна рекомендація MONITORING-DEPLOY, а його реальний стан **Requires runtime verification**.

SiteMonitor має site cache lease 120 s, fresh Site до HTTP, row lock та fingerprint/revision check після HTTP. `next_check_at = UTC probe_start.startOfMinute() + delay`: delay 1 хв для down нижче threshold, інакше per-site interval. Послідовність failures чинна лише в межах двох observed intervals, інакше first failure/лічильник починаються заново. Incident підтверджується на threshold, opened_at — перша невдача. Один up закриває; planned закриває з окремою причиною. Raw down і time-based down з'являються до confirmation — alert threshold не прибирає цей час зі статистики.

**INFERENCE.** Запропоноване `last_checked_at <= now()->subMinutes($interval)` **гірше** current architecture, а не еквівалентне: воно губить 1-minute confirmation retry при interval=5, null/new target semantics та явний scheduled due; completion-time anchor додає drift. За interval-only healthy regime схожість приблизна, не причина заміни. Current minute rounding може дати трохи менший elapsed interval і не є phase-preserving scheduler; великий backlog теж змінює phase. При довгому probe retry due вже може минути до завершення.

**FACT.** Global `monitoring.interval_minutes` default 5 копіюється model creating hook і міграцією в конкретне поле Site. Немає null/use-global mode. Blade нового Site підставляє **literal 5**, тож змінений config default не обов'язково впливає на створення через повну форму. monitoring_settings зараз лише recipients. Manual check не змінює автоматичний schedule, failures, periods, metrics, daily чи incidents; з auto вони взаємно блокуються site lock.

**RECOMMENDATION.** Один canonical `effectiveInterval(subject)` з explicit inherited/custom mode та global persisted setting або погодженим config source. Поточні значення Site зберігати custom під час compatibility migration; не вважати всі існуючі «5» автоматично inherited. При global change визначити reschedule/revision/reset policy для inherited monitors, зберегти старий observed_interval для вже виміряного tail. Спільне джерело використовувати для due calculation, freshness, retry policy та UI. next_check_at лишити; Heartbeat має next_expected_at/deadline semantics, а не синтетичний last HTTP check.

## 8. Storage / Retention Audit

**FACT.** Runner безумовно зберігає кожний auto/manual raw result, потім очищає checks старші detail_days (default 30), daily старші daily_days (365), runs старші 30 днів. Incidents/notifications/periods/spans/metrics не prune. Spans зливають суміжний однаковий availability та checked_url; unknown не зберігається рядком, це expected time без bounded observation. Tail максимум 2 observed intervals.

`availability = up/(up+down)`; `coverage = (up+down)/(expected-planned)`; unknown = expected−up−down−planned; нульовий denominator → null. Paused/deleted intervals не expected. Latency — лише up auto samples, sum/count average, max, merged histogram p95 upper bound. Count ratio — regression, особливо при retry і cron gaps. Incident elapsed duration може містити unknown й не дорівнює observed downtime.

**INFERENCE.** Prune raw/details не видаляє current time/latency aggregates: є тест цього invariant. Але safety лише відносно **поточних** reports: зникають cert evidence, timings та детальні failed/manual results; немає check→incident FK чи окремої linked retention. Daily prune порівнює Kyiv day із UTC date cutoff — на межі доби retention може відрізнятися на день. Large deletes і run pruning поза deadline checks можуть подовжити cron/lock occupancy. Кількість реально накопичених rows **Not confirmed from current code**.

### Recommended retention matrix

**RECOMMENDATION.** Нижче кандидати, а не затверджені SLA; всі operational строки підлягають погодженню. Prune не має видаляти pending delivery/open incident чи єдину lifecycle projection.

| Data | Purpose | Recommended retention |
| --- | --- | --- |
| Successful automatic raw checks | Recent диагностика/timings | 7–14 днів; якщо потрібна повна діагностика — 30; далі sampling лише після projection |
| Failed raw checks | Причина outage/false-positive investigation | 60–90 днів |
| Incident-bound evidence | Перша/confirmation/recovery та значущі failed samples | 180–365 днів або строк incident audit; explicit linking/snapshot |
| Manual checks | Діагностика без статистичного впливу | 14–30 днів |
| monitoring_daily | Legacy check counts | Existing 365 до deprecation; потім не підтримувати нові writes без consumer |
| periods/spans | Expected/observed time, unknown, maintenance | Довгостроково; не prune до узгодженого archive/rollup |
| metrics | Long-term latency | Довгостроково, з aggregation calendar/version |
| incidents | Lifecycle/audit/public incident projection | Довгостроково; archive за погодженою політикою |
| states / settings | Current projection/config | До видалення subject; не age prune |
| notifications pending/retrying | Durable delivery | До terminal state, expiry/escalation policy; не silently age-delete |
| notifications sent/cancelled | Delivery audit/dedup evidence | 90–180 днів, event dedup identity зберігати незалежно від delivery row |
| runs | Operational cron diagnostics | 30 днів; 90 для failed/interrupted за потреби |
| Certificate lifecycle state | Latest reliable cert/threshold state | Постійно для активної цілі; history transitions 1 рік+ |
| Heartbeat raw pings | Diagnose jobs, last payload metadata | 7–14 днів bounded payload; lifecycle events 90–180 днів |

**FACT.** Уже є indexes: states unique(site_id), next_check_at; checks checked_at та (site_id,checked_at); daily/metrics unique(site_id,day); incidents(site_id,closed_at); notifications next_attempt_at та unique(incident_id,user_id,event); periods(site_id,started_at); spans(site_id,ended_at). Runs.started_at не indexed; daily.day не окремо indexed для global prune. Unique incident-open або period-open invariant у DB немає.

**RECOMMENDATION.** Перевірити EXPLAIN на репрезентативній копії перед вибором indexes: runs(started_at) для prune; daily(day) для global prune, якщо лишається; notifications terminal/due composite або projection для pending queue; incidents(site_id,opened_at) для calendar overlap; last-span lookup (site_id,id); periods site/open access. Range overlaps не вирішуються механічним додаванням одного index. Для monitor migration keys/indexes мають включати monitor subject. Chunked prune із бюджетом, однаковий UTC contract cutoff, durable projections до skip/prune raw. Числових оцінок DB bytes без actual row sizes немає.

## 9. SSL Alert Readiness

**FACT.** SafeHttp додає CURLOPT_CERTINFO; SiteProbe on_stats бере `certinfo[0]['Expire date']`, перетворює в UTC. Callback на кожний hop reset expiry: залишається остання HTTPS response. Поле certificate_expires_at зберігається **тільки у monitoring_checks**; state/metrics не мають його. Blade бере latest check (у тому числі manual), показує ≤14d. Після latest null/error warning зникає, попереднє reliable expiry не вибирається. Renew fingerprint/issuer/serial не зберігаються. `checked_url` у result — configured initial URL, не фінальний redirect URL.

**INFERENCE.** Current cert data частково готові, current notifications **не готові** до SSL lifecycle: обов'язковий incident_id, Site lookup, event mapping тільки down/recovered/planned. Без зміни Monitoring event contract вставка ssl_warning з fake incident створить неправильні outage reports або formatter failure. Telegram transport можна зберегти без Access bot refactor.

**RECOMMENDATION.** Separate certificate projection: verified_at, last reliable not_after, target/final TLS host, fingerprint за можливості, probe error/freshness; notification state warning14/critical3/renewed для конкретної certificate generation + recipient. Durable unique event key має пережити notification-row prune. При first observation всередині 3 днів — один critical, не одночасний warning+critical. Missing expiry/TLS error не стирає останній cert і не означає renewal; окремий freshness/HTTP TLS failure signal. Renewal визначати новим fingerprint і валідним expiry, або monotonic later expiry як слабше evidence; target change не renewal. Не надсилати alert за кожний cron; optional renewal — opt-in. Cert probe schedule можна відокремити від HTTP interval; explicit TLS host entity потрібна при redirects/кількох endpoints. Чи SSL буде standalone type — open decision, не додається мовчки до HTTP/TCP/Heartbeat V1.

## 10. Heartbeat Readiness

**FACT.** Push endpoint/token/last ping/deadline/heartbeat event entity відсутні у Monitoring. Existing classes приймають Site і HTTP результат. Один Site field не представляє декілька backup/import jobs.

**RECOMMENDATION.** Heartbeat — окремий Monitor type, nullable site link тільки після визначення standalone owner/authorization. Config: expected elapsed interval, grace, enabled, name; projection: last accepted ping, next_expected_at, deadline, state, revision. Deadline evaluator відкриває missed incident атомарно один раз; ping closes/recovery через той самий transition/history/outbox contract. Визначити initial unknown до першого ping або deadline від активації, pause/resume, late/duplicate ping і grace semantics. До deadline — fresh/healthy за правилами; після missed deadline — down, а не HTTP-style unknown через 2 intervals. Зупинка evaluator потребує окремого coverage/freshness правила, бо відсутність пінгу й відсутність спостерігача — різні факти.

Monitoring incidents/spans/notifications **концептуально reusable**, але current schema/method signatures не reusable без subject adaptation. Heartbeat не повинен створювати HTTP monitoring_checks або response_ms=job duration. Optional event table — для bounded metadata/dedup/job completion; всі пінги не потрібні довгостроково. У V1 ping = completion success; start/failure payload додавати лише з погодженою job state machine.

**RECOMMENDATION. Security contract:** cryptographic random secret (наприклад 32 random bytes), віддати plaintext один раз; зберігати hash і token identifier, constant-time verification; credential rotation/revocation, коротке overlap вікно лише за explicit policy. Hash-at-rest не запобігає replay викраденого bearer. HTTPS POST, Authorization/header secret; GET із secret у URL не рекомендується через access logs/history/referrers. Body size cap, schema whitelist, без довільних secret-rich payloads, credentials/body redaction. Rate limits per monitor + trusted source IP, не лише global; не губити легітимні одночасні jobs. Atomic ping/deadline updates під monitor row lock, server receipt time UTC як default truth. Optional idempotency job_run_id; старий повтор не подовжує deadline без правил. Для строгого replay resistance потрібні timestamp/nonce/HMAC і clock-skew window, bounded nonce store; це окремий tradeoff, не обіцянка звичайного bearer endpoint. Не створювати логування секретного ping URL або raw tokens.

## 11. TCP / Network Probe Readiness

**FACT.** SafeHttp/OutboundAddressPolicy дозволяють лише HTTP(S), порти 80/443, DNS A/AAAA validation, public-address policy, IP pinning; exceptions — точний HTTP origin→RFC1918/ULA IP, GET-only. Це не готова TCP target API. `isPublic()` може стати спільним IP classifier, але перенос HTTP exception на arbitrary port змінить security scope.

**RECOMMENDATION.** TCP Monitor: validated host/port, bounded connect timeout/interval, connect success/error/latency, independent incident/recovery. `stream_socket_client('tcp://<validated-pinned-IP>:port')` із numeric IPv4 або bracketed IPv6 і fclose; альтернативно fsockopen; async/select тільки з явним global concurrency/deadline limit. DNS resolve поза unbounded hidden socket hostname lookup, перевірити всі A/AAAA, connect саме до перевіреного IP. DNS latency окремо від connect_ms або явно описати включення. Connect timeout стосується встановлення з'єднання; read/write потребують іншого timeout ([PHP manual](https://www.php.net/manual/en/function.stream-socket-client.php)). Plain TCP 443 success не доводить HTTPS/TLS/content health, TCP DB не доводить успішний query.

Security: loopback, RFC1918, link-local, IPv6 ULA/local/mapped/transition, metadata/reserved адреси блокувати; fail closed на mixed public/private DNS. Pinning закриває resolve→connect rebinding window; не передавати original hostname для повторного implicit DNS. Port 1–65535 validation недостатній: дозволений набір портів, quota на monitors і можливість створення targets за правами, щоб портал не став scanner. Internal exceptions — окремий admin-managed exact host/port/IP allowlist, без широких CIDR, user-controlled ranges або доступу до metadata. Це target design; Shared security код не змінено. Вихідний firewall/hosting network restrictions **Requires runtime verification**.

**INFERENCE / RECOMMENDATION. ICMP.** На shared hosting raw socket або executable ping може бути недоступний за permissions/disabled functions/OS restrictions; успішний ICMP не доводить application readiness, блокування ICMP не означає down HTTP. Практичну підтримку тут не перевіряли. Не включати зараз; пізніше окремий controlled agent або protocol adapter, без shell interpolation user host.

**INFERENCE / RECOMMENDATION. UDP.** Generic UDP «connect» не підтверджує remote service: протокол connectionless, відсутність відповіді неоднозначна. Потрібен protocol-specific request/response із validation/deadline; UDP не requirement V1. Це відповідає semantics sockets у [PHP manual](https://www.php.net/manual/en/function.stream-socket-client.php).

## 12. Status Page Backend Readiness

**FACT.** Report уже дає calendar buckets, duration/uptime/coverage/unknown/planned, latency mean/max/p95, incident count та longest observed down. Incident list і current state читає Controller окремо. Public entity/route/access/cache policy, component mapping і operational/degraded/down resolver відсутні. Current Report hardcodes Kyiv та Site ids; public DTO не існує.

**INFERENCE.** 30-day read-only projection можна побудувати з існуючих periods/spans/metrics/incidents, не створюючи другу uptime БД. Але mixed monitors і degraded не мають готової semantics: HTTP failure threshold, stale observation, missed heartbeat, maintenance та latency thresholds — різні сигнали. Current report count не замінює curated public incident content.

**RECOMMENDATION.** status_pages entity: slug, owner, enabled/publish opt-in, visibility, reporting timezone, component→monitor mapping, sanitized public title/incident content. Public/private/token/password modes обрати явно; private використовує portal ACL, token — hashed revocable high-entropy credential без витоку через referrer/logging, password — password hash/rate-limit. Нічого не публікувати автоматично від Site URL або company_id. Projection не розкриває monitoring_url query credentials, technical errors/internal hosts, recipient/user data або management controls. Для unavailable/stale data публічно зберігати unknown, а не operational. Кешувати sanitized DTO на короткий TTL (наприклад 30–60 s), invalidation на transition/publish settings; public caching не переносити на protected pages без коректного cache key/access boundary. Status reads не запускають probes і не змінюють history.

## 13. Site vs Monitor Architecture Options

**FACT.** Current uniqueness/state/history/notification lookup і signatures enforce `1 Site = 1 HTTP monitor`. Новий пакет включає кілька незалежних signals на один Site; решту оцінено як design options, не реалізовану schema.

| Criterion | A — Minimal Site extension | B — Hybrid | C — Full Monitor abstraction |
| --- | --- | --- | --- |
| Advantages | Мінімум змін current HTTP; швидкий SSL addon | HTTP compatibility; нові types/multi-target без big-bang; reuse contracts | Єдиний subject/query/UX, кілька HTTP endpoints природні |
| Disadvantages | Heartbeat/TCP side tables множать lifecycle/reporting; Site перевантажений | Тимчасовий bridge, складні lookup/invariants; ризик двох engine | Одночасно великий перехід state/history/routes/reporting/lifecycle |
| Migration complexity | Low для SSL; medium/high для кількох types | Medium, поетапно; чіткий ownership mapping | High; backfill всіх subjects/FK/indexes і compatibility |
| Regression risk | Low HTTP спочатку; high semantics divergence з часом | Low/medium за adapter/contracts і shadow validation | High через широку зміну стабільного HTTP backend |
| Compatibility | Current routes/fields без змін | Current Site routes/IDs читають default HTTP adapter | Треба забезпечити Site APIs/routes compatibility facade |
| Scalability | Targets виконують кілька runner, duplication | Unified capacity planning, backend може зближуватися | Найпростіше розширювати після завершення переходу |
| Current reporting | Старий HTTP report, side reports для нових signals | HTTP report незмінний спочатку; subject report adapter | Перенесення всіх Site id queries на monitor subject |
| Future UX | Side panels/fields погано представляють monitor list | Нормальна monitor list; legacy default HTTP сумісний | Чистий monitor-centric UX |
| Existing Site IDs/history | Зберігаються; нові signals окремо | Зберігаються; mapping не переписує historical IDs | Site IDs лишаються; history прив'язка backfill до default monitor |

**RECOMMENDATION. Обрати B.** Для поточного пріоритету «мінімальний ризик + scalability» A недостатня для кількох Heartbeats/TCP/HTTP, C має непропорційний immediate migration ризик. B має закінчений compatibility contract: кожний existing Site отримує однозначне default HTTP monitor mapping; тільки один authoritative writer current HTTP state; нові types відразу monitor-owned.

B не означає помістити нові states в monitoring_states із тим самим site_id. Можливий staged subject key extension current tables або окремі monitor-owned tables зі спільними history algorithms/read adapter. **Вибір schema bridge ще відкритий**, не обидва одночасно. Preserve old site_id, row IDs, incident identity та Site routes; додавати mapping/backfill без rewrite timestamp meaning. Якщо nullable site_id потрібний standalone targets, owner/permissions/deletion/report timezone мають існувати незалежно від Site.

**INFERENCE.** Згодом B може перейти до C коли вигода unified HTTP monitor reporting виправдає migration і перевірена parity. Це evolution path, не автоматичний наступний refactor чи затверджена вимога.

## 14. Security Review

**FACT.** Monitoring доступ через auth/active.user/monitoring.read; settings додатково monitoring.write. PM може редагувати Monitoring, Developer — monitoring параметри через sites.write; manual path також із sites.read, throttle 10/min та site lock. HTTP URL форма синтаксично валідована; остаточний security check у SafeHttp. TLS verify=true, proxy вимкнений, DNS всі addresses перевірені, selected IP pinned; redirects revalidated ≤3, download limit 2 MiB. Винятки internal GET не поширюються на redirect іншого origin чи control requests. Notification user eligibility/recipient membership перевіряється повторно перед transport; HTML fields escaped/truncated. Raw exception text не зберігається для HTTP (класифіковані помилки), run зберігає class.

**INFERENCE.** monitoring_url може містити sensitive query string: current checked_url/raw history/UI містять адресу, Telegram показує Site URL. Form дозволяє URL до security probe, але secret-in-query redaction окремо не реалізована. Fingerprint захищає налаштування, а не бізнес-команду під час probe; planned оцінюється з актуальної row після HTTP. Цю current compatibility semantics не можна мовчки переносити на всі monitor types.

**RECOMMENDATION.** Preserve security policy/IP pinning; окремі permissions/quotas для TCP target створення й Heartbeat credential management; redacted target labels у public projections/logs; encrypted sensitive probe config лише якщо буде така requirement. Public status cache і push endpoint мають власні ACL/rate limits. При плануванні internal TCP не reuse HTTP exception без explicit narrow policy.

## 15. Scalability Review

**FACT.** Послідовний runner, global lease, batch100/default240s/pause0.25s; перед probe reserve `timeout*4+5` (типово29s). Notifications delivery на початку, після кожної check і в кінці, до 3 events у повідомленні, на кожний deliver читається максимум100 pending rows. Transport timeout15s, DNS/DB/prune не гарантуються загальним budget. Process limits реального hosting невідомі.

**INFERENCE.** Для 5-min interval arrival rate N/300 targets/s. При успішній check середній serial cost c = probe + DB + 0.25s + delivery amortization. Потрібно N*c ≤300s, але batch/cron cadence/deadline overhead/locks додають обмеження. Це optimistic algebra, не benchmark/SLA.

| Targets | Checks за хв при 5min | Raw/day та 30d без retry/manual | Algorithm readiness |
| --- | --- | --- | --- |
| 100 | 20 | 28,800 / 864,000 | Розумний кандидат для current runner за швидких probes; ideal average cost ≤3s, фактичний запас менший |
| 500 | 100 | 144,000 / 4,320,000 | Default batch100 щохвилини дає рівно nominal demand без запасу; c має бути ≤0.6s; повільні targets/retries легко створюють backlog |
| 1000+ | 200+ | 288,000+ / 8,640,000+ | Default batch100 обмежує capacity навіть за fast checks; c≤0.3s optimistic, pause0.25s лишає надто мало часу; потрібна інша execution capacity |

Без flaky transitions spans стискаються; за frequent up/down/target changes можуть рости майже з checks. Metrics — приблизно1 row/site/day (365k rows/year для1000 за daily samples); periods ростуть за enable/disable cycles. Incidents/notifications не prune, fan-out множить event volume. Target intervals1min збільшують nominal raw/traffic у5 разів. Incident confirmation retries додають burst.

Report::build завантажує overlapping periods/spans/metrics усіх filtered Site IDs в memory; index list pagination30 **не** обмежує dataset загального report. Кожний long span/period distribution обходить календарні дні навіть при monthly group; all/custom великі ranges можуть бути дорогими. StaleIds Controller читає всі states; delivery робить кілька lookup per row (N+1). Це backend ризики, не пропозиція UI redesign.

**RECOMMENDATION.** 100: виміряти effective cadence, pending queue і timeout ratio. 500: jitter/phasing, оптимізувати queries/prune, separate delivery budget, capacity metrics; після вимірювань bounded concurrency. 1000+: shared locks/claiming, bounded workers/queue або окремий runner deployment, не просто підняти max_seconds. Зберегти address validation, subject revision, row locking та outbox. Long-term reports — cached projections/rollups зі збереженням coverage semantics. Реальна readiness shared hosting **Requires runtime verification**.

## 16. Risks / Hidden Coupling

| Finding | FACT / evidence | INFERENCE / impact | RECOMMENDATION |
| --- | --- | --- | --- |
| UTC ambiguity | now() + UTC writers; TIMESTAMP/DATETIME; no MySQL timezone config | Mixed historical instants можливі; root cause +3h не доведена | Runtime clock/config/session verification до data correction |
| Cron display bug | index.blade.php:9 parse UTC string as Kyiv | Хибний last run time/stale warning | Scoped parser fix у наступній задачі |
| Concurrent checks | Global/site locks; Site/state row lock; no DB unique open incident/period | Normal paths serialized; array/nonshared cache або expired lease ламає міжпроцесну гарантію | Shared lease store, bounded tasks, DB invariants/claiming |
| In-flight target changes | fingerprint + revision, Site lock після HTTP | Stale params результат відкидається; прямі DB writers поза metadata service можуть обходити invariant | Один writer contract і regression cases |
| Transaction boundaries | HTTP до transaction; raw/state/history/incident/enqueue всередині | Добра атомарність; Telegram не можна rollback | Зберегти outbox boundary |
| Queue reset/delivery race | reset cancels unsent rows; deliver читає/фільтрує перед external send | Pause/target change після filter не може відкликати вже початий Telegram send | Event generation/revision check перед send; описати at-least-once limits |
| Duplicate notifications | unique incident/user/event і delivery lock | DB dedup є; Telegram success до DB update/crash може повторитися | Durable generic event ID; transport exactly-once не обіцяти |
| Mutable notification context | formatter читає Site/incident під час delivery, не snapshot | Old down може прийти після recovery з новим URL/closed duration; ordered delivery не event-time snapshot | Event occurred_at/subject snapshot, expiry/coalescing policy |
| Retry storm/backlog | 1min unconfirmed retry; delivery retries5…30min без terminal cap; limit100 first rows | Portal network outage fan-out, permanent failures/blocking old users можуть затримати інших | Observer health signal, fairness, expiry/permanent failure handling |
| False alerts / masking | Один observer; one-up recovery; confirmed planned overrides technical result | Network outage як multi-target down; false200 без content; planned може приховати unrelated failure | Preserve technical signal, configurable debounce later, scoped maintenance |
| Scheduling budget | Sequential, lease not renewed; prune/recovery hooks поза budget | Deadline не hard kill; stalled DNS/process limit можуть втратити lease/каденс | Bound operations, backlog/lease observability |
| No self-monitoring external signal | UI lastRun only, one portal observer | При зупинці portal ніхто не відправить missed alert | Окремий зовнішній observer/heartbeat, не цього ж stopped process |
| History deletion coupling | Site hard delete каскадно прибирає history | Public/SLA archive губиться після registry deletion | Owner/deletion policy перед monitor/status pages |
| Runs external recovery hooks | RunMonitoring calls SupportWebhookInbox::recoverInterrupted і site_control_attempts cleanup до deadline | Monitoring failure можливий через direct dependency | Зафіксувати contract; External dependency / outside current scope; інші модулі не досліджено |
| Daily calendar coupling | metrics keyed Site/day, no timezone/version | Company calendar change не reversible після raw prune | Display-only timezone first; explicit aggregation redesign later |

**FACT.** Current tests охоплюють single-process locks/revision, schedule/manual separation, state transitions, pruning independence та DST23h. Multi-process MySQL races, crash між Telegram send/ack, wall-clock jumps, 25h day, real TLS certinfo і production network limits **Requires runtime verification** або окремих targeted tests. Clock NTP/config correction не виконувалася.

## 17. Recommended Target Architecture

**RECOMMENDATION.** Strategy B з такими contracts:

1. **Site registry / owner context:** Site IDs/business status/control незалежні від Monitoring signals. Company display defaults з nullable Site override. Standalone owner визначити до nullable site_id implementation.
2. **Monitor definition:** name/type/enabled/revision, target config, inherited/custom interval, timeout; optional site link, default HTTP compatibility mapping. Type config validated adapter, не довільна JSON команда.
3. **Subject projection:** current observed state/technical signal, last observed UTC, next due або heartbeat deadline. Current state може бути окремою таблицею; не дублювати authoritative state в definition і Site.
4. **Adapters:** legacy Site HTTP, нові HTTP endpoints за потреби, Heartbeat deadline/ping, TCP connect. Об'єднуються transition engine/history rules/outbox, а не pretend HTTP latency для всіх types.
5. **History:** expected periods + bounded observations/gaps + type-specific metric histograms; stable subject mapping. Для Heartbeat freshness/expectedness визначається окремим policy.
6. **Incidents/events:** durable subject/generation/event key + occurred_at та cause; notification deliveries per recipient. SSL lifecycle event не fake outage incident. Current access transport лишається transport.
7. **Reports/projections:** compatibility Site report, subject report, sanitized status projection. Явний aggregation calendar/version; жодного average-of-percentages або average-of-p95.

Monitor schema концептуально може мати config JSON, але frequently queried scheduling/type/enabled/revision мають бути typed/indexed. Tokens/secrets не plaintext config. У цьому аудиті schema не затверджена й не реалізована.

## 18. Recommended Implementation Phases

**RECOMMENDATION.** Це послідовність для майбутнього плану після перегляду аудиту, не початок implementation.

| Phase | Scope | Acceptance gate |
| --- | --- | --- |
| 0 | Runtime UTC/deployment/cron evidence; погодити owner, calendar, Strategy B bridge | Один traceable event UTC→DB→Telegram; historical correction тільки за evidence |
| 1 | Scoped time correctness fix + effective interval inheritance | Cron parser; UTC writer/session contract; retry/manual/freshness parity, DST23/25h; existing custom intervals preserved |
| 2 | Monitor subject/legacy HTTP adapter і generic event outbox contract | Site routes/IDs/history parity; one authoritative writer; stale result/concurrency migration checks на копії MySQL |
| 3 | Selective retention/index/prune budget, certificate lifecycle + SSL alerts | Uptime/coverage/latency unchanged після raw prune; 14/3/renewal dedup, null cert/error freshness |
| 4 | Heartbeat Monitor/ping/deadline evaluator | Token rotation/rate limits/replay contract; concurrent ping/deadline, missed/recovered/pause semantics |
| 5 | TCP adapter і bounded execution capacity | DNS pinning/private IPv6/metadata/port policies; connect latency semantics; backlog/worker limits |
| 6 | Read-only Status Page backend projection | Explicit publication/access/privacy, cached sanitized data, stale/unknown/degraded policy |
| Later | Monitor-centric UX; optional full HTTP migration, ICMP/protocol-specific UDP | Окремі погоджені задачі після backend parity; не включати автоматично |

Якщо пріоритет спочатку SSL, мінімальний certificate state можна підключити до legacy HTTP, але event identity має відповідати phase2 contract і не залежати від fake incident. Зміни global timezone/interval не повинні приховано переписувати історію.

## 19. Open Decisions

**RECOMMENDATION. Погодити до деталізації implementation:**

- Display-only Company timezone чи також локальні calendar reports? Nullable Site override потрібен одразу? Що є owner standalone monitor?
- Default interval persistent setting чи config? Який explicit inheritance marker і що робити з already-due/observed tail при global change?
- Hybrid schema bridge: staged monitor_id current tables чи new subject tables + compatibility reader? Backfill/default HTTP mapping, deletion/archive policy.
- Чи підтримує V1 кілька HTTP monitors і standalone targets, або тільки Heartbeat/TCP на Site? SSL lifecycle addon чи explicit certificate monitor?
- Heartbeat: activation без першого ping, interval vs calendar schedule, grace, failure/start payload, replay/idempotency contract.
- SSL: thresholds14/3, warning suppression при immediate critical, renewal opt-in, exact TLS host за redirect, freshness/error policy.
- Retention точні строки/cost budget; incident evidence linkage; delivery expiry/coalescing/permanent errors; зберігання dedup identity після prune.
- Public status component policy: unknown/planned/degraded/incident confirmation, sanitized incident publishing, public/private/token/password modes.
- Target count/interval mix/expected network latency, deployment process limits, alert latency SLA; чи потрібен external observer.

Production DB contents/config cache/cron/Telegram queue/certificate behavior **Not confirmed from current code**. Висновки не припускають, що локальний патч уже встановлений на хостингу.

## 20. Documentation Discrepancies

| Document / claim | Current code evidence | Classification / action |
| --- | --- | --- |
| MONITORING-PLAN sections2–3: only Prod, manual впливає на schedule/counts, count uptime, немає monitoring_enabled | Current Runner/SiteMonitor/Report та patch tests показують іншу поведінку | **Historical, explicitly marked pre-patch** на початку; не current fact, не переписувати історію |
| DEPLOYMENT body: cron5min, Prod-only, fixed2 failures/6s, count percentage | Current patch implements enabled Dev/Prod, per-site values, time-based reports | Header прямо supersedes старий текст; використовувати MONITORING-DEPLOY/PATCH |
| MODULE-MAP «show: денні лічильники» | show/report читають periods/spans/metrics, не monitoring_daily | Factual stale description; зафіксовано тут, старий файл не редагувався |
| MODULE-MAP schema source лише migration09-09 у базовій секції | Extension09-19 додає поля і3 таблиці; доповнення документа це називає | Reading hazard; потрібні обидві міграції, не лише початкова таблиця |
| MONITORING-PATCH «timestamps UTC» | UTC explicit для check, now() залежить від app; MySQL session unspecified; cron parser local | Intended storage contract, **не proof усіх runtime/historical instants** |
| PATCH/DEPLOY: interval config типове значення model creation | Model так працює; options Blade submitted new form default literal5 | Документ правильно обмежує statement моделлю; UI default має окремий source inconsistency |
| TLS expiry в UI | Поле persistent лише raw checks; latest null/manual може приховати reliable cert | Statement true, але lifecycle/freshness не реалізовані; не трактувати як готові SSL alerts |
| Старий plan пропонує «кінцева адреса» | SiteProbe.checked_url зберігає configured initial target, не redirect destination | Proposed goal, не current implemented fact; важливо для certificate host attribution |
| 54 tests/389 assertions та local MySQL recovery validation у patch/deploy | Історичні звіти; поточний scoped run22/128 SQLite | Не видавати historical result за повторно виконаний current full-suite/MySQL audit |
| «Хостинг не оновлювався» у local docs | Repository не доводить поточний remote deployment state | Last documented state, actual2026-10-05 deployment **Requires runtime verification** |

**FACT.** Новим документом є лише цей audit. Application code was not modified. Persistent database schema was not modified. Other portal modules were not refactored. No deployment/runtime configuration was changed. Реалізація наступного етапу не починалася.
