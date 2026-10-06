# Monitoring V2 Architecture

Дата: 2026-10-05. База локального коду: `d793da4`. **Architecture design only — implementation не починалася.**

Цей документ замінює рекомендацію Strategy B у [MONITORING-NEXT-AUDIT.md](MONITORING-NEXT-AUDIT.md) для наступного патча, бо нові рамки дозволяють відкинути legacy Monitoring history та implementation compatibility. Попередній аудит залишається історичним описом current state. Усе нижче з позначкою **DESIGN** є запропонованим V2 contract, а не вже реалізованою функцією. **FACT** позначає перевірений поточний код або надані користувачем умови; **OPEN** — рішення, яке ще потребує погодження.

Перевірено повторно тільки Monitoring/Site integration, network helpers, current time parser, notification transport boundary та перелік прямих Monitoring references. Інші бізнес-модулі не аудіювалися. Production DB/config/cron/Telegram state не перевірені. Єдина зміна цієї задачі — створення цього документа.

## 1. Final constraints

**FACT — погоджені рамки.** Зберегти `sites`, усі Site IDs/основні дані/URL/company relations/credentials, немоніторингові revisions, remote-control secrets/versions/confirmed state/history/child integration, users/companies/FTP/Hosting/Audit/Access/Support/UserManagement. Monitoring не змінює бажаний стан сайту і не відправляє remote commands.

Legacy `monitoring_*` history/statistics/incidents/deliveries/settings/runs та monitoring-specific Site columns/pages дозволено замінити. Нова статистика починається з V2 activation. Не потрібні compatibility reader, історичний backfill або міграція сумнівних timestamps.

Поведінку включення Site в monitoring, регулярні probes, підтверджені incidents, Telegram down/recovery та різних відповідальних зберегти. HTTP/HTTPS, SSL14d/3d/renewal, кілька Heartbeat jobs, TCP — target scope; ICMP optional later, generic UDP поза V1; Status Page UX пізніше.

**DESIGN — зміна рекомендації.** Обрати **Clean Monitoring V2 / Monitor-centric architecture**. Hybrid більше не дає головної вигоди збереження history, зате додає два subjects, adapter routing і тимчасові FK/queries. Clean V2 має один writer/subject/calendar/event model. Ризик переходу залишається на межі Site lifecycle/control/permissions та selective cleanup; зниження migration risk не означає дозволу чіпати бізнес-дані.

| Критерій за нових умов | Hybrid | Clean V2 |
| --- | --- | --- |
| Legacy history | Вартість bridge без обов'язкової вигоди | Не переноситься |
| Кілька HTTP/Heartbeat/TCP на Site | Потрібен default HTTP adapter + new subjects | Одразу звичайні Monitor entities |
| Scheduler/storage/alerts | Compatibility branches у critical paths | Один contract, type policies явно відділені |
| Міграція | Довший період змішаного коду/schema | Один cutover із backup і новим стартом stats |
| Основний ризик | Hidden dual ownership, відкладений другий refactor | Безпека reset/cutover та Site integration |
| Висновок | Не рекомендовано за поточних рамок | Рекомендовано |

## 2. Proposed Monitoring V2 architecture

**DESIGN.** Monitoring — окремий subsystem у чинному Laravel portal, не новий registry сайтів.

- **MonitorDefinition / type config:** що перевіряти, effective settings, enabled intent, revision; `monitoring_monitors`.
- **MonitorState / claim:** mutable current projection, confirmation counters, freshness, schedule/lease/sequence; один рядок на Monitor.
- **Probe adapters:** HTTP(S) через SafeHttp; TCP через narrow target policy; Heartbeat через authenticated ping + deadline evaluator. Єдиний нормалізований result envelope, але різні freshness/metric rules.
- **Transition service:** під Monitor row lock визначає observation/service state, incident lifecycle, history і domain event. Не робить external HTTP/Telegram у transaction.
- **History accumulator:** expected periods, compressed spans, incremental daily/monthly rollups; explicit unknown/planned; monotonic accounting cursor.
- **Certificate lifecycle:** незалежна last reliable certificate projection та threshold events, без fake availability incident.
- **Event outbox / delivery service:** durable immutable event + recipient-specific attempts; існуючий Access `TelegramBotService::sendMessage` як transport.
- **Read projections:** Monitor list, Site monitoring summary, incidents/statistics/settings; згодом sanitized Status Page DTO. Жодні reads не запускають probe або history mutation.

V1 всі monitors належать існуючому Site (`site_id NOT NULL`), бо standalone targets не погоджені. Одна company relation береться із Site; не дублювати `company_id` у кожному Monitor. Nullable standalone ownership — окреме розширення, не прихована вимога.

Result envelope: monitor_id, revision/config snapshot, dispatch sequence, started/completed UTC, technical result/error class, permitted metric, target identity. Після commit consumed claim не може повторно додати sample/event. Manual results позначені diagnostic-only й не проходять automatic transition/accounting pipeline.

## 3. Site integration

**DESIGN.** Site майже не перебудовується. Немає другої authoritative copy monitoring_enabled/interval/revision на Site. «Включити Site в Monitoring» — команда створити/увімкнути його primary HTTP Monitor; checkbox у майбутній Site form читає/пише цей Monitor через integration service. Відключити всі monitors Site — інша explicit операція, не неоднозначний primary checkbox.

| Current Site field / behavior | Classification | V2 action |
| --- | --- | --- |
| id, name, url, company_id, environment, site_type, основні дані/credentials | KEEP in Site | Немає business migration/recreation sites |
| remote_control_enabled, is_active, api_token, control_version, confirmed_state/confirmed_control_version, control data/history | KEEP in Site | Monitoring тільки читає relevant maintenance context |
| monitoring_enabled | MOVE to Monitor; TRANSITION ONLY source | enabled primary HTTP на cutover; потім drop old column |
| monitoring_interval | MOVE to Monitor; REMOVE materialized default | `custom_interval_seconds = NULL` означає global; старе значення не constraint |
| monitoring_timeout | MOVE to Monitor | nullable override; default із Monitoring Settings |
| monitoring_failure_threshold | MOVE to Monitor | nullable override; global confirmation default |
| monitoring_status_codes, monitoring_content | MOVE to Monitor config | HTTP-specific validation/schema |
| monitoring_url | MOVE to Monitor config | target mode follow_site_url або explicit URL |
| monitoring_recipient_ids | MOVE to Monitor recipient policy | inherit global або explicit list; без другого Site масиву |
| monitoring_revision | REMOVE in V2 | Monitor revision/config snapshot замінює stale-result protection |
| Site::created/deleting/restored monitoring hooks | TRANSITION ONLY | Тонкий MonitoringIntegration contract без old History calls |
| SiteMetadataService monitoring reset/validation | TRANSITION ONLY | Monitor settings service; control/URL safeguards лишаються |

Нових persisted Monitoring columns у `sites` для core V2 **не потрібно**. Додати лише relation/query/integration calls. Якщо UI потрібен computed monitoring_enabled — це projection, не масове поле для fill/save.

Primary HTTP slot: `slot = primary_http`, unique `(site_id, slot)`; усі додаткові monitors мають slot=NULL (MySQL unique допускає кілька NULL). Type/slot validation забороняє TCP/Heartbeat у primary_http. Follow-site-url Monitor отримує нову revision при зміні Site URL, завершує старе спостереження та починає UNKNOWN; explicit `/health` URL не змінюється мовчки. Scope змін control secrets/версій Site лишається чинним і не переноситься в Monitor.

На existing non-deleted Site cutover створює один primary HTTP, enabled із old monitoring_enabled; disabled Sites не активуються автоматично. Soft-deleted Site на першому cutover можна пропустити; restore інтеграція створить disabled primary за потреби. Для нового Site inclusion checkbox default YES, Monitor custom interval=NULL. Нові додаткові monitors — явне створення; Site creation не створює TCP/Heartbeat.

Site soft delete зупиняє expected periods і скасовує pending events, але не видаляє історію V2 до force delete. Restore починає свіжий UNKNOWN period, не переносить попередній UP. Existing hard-delete behavior можна зберегти cascade тільки в Monitoring-owned data; зміна Site/business deletion semantics не входить у цей patch. Бізнесові Site revisions/ActivityLog не очищаються навіть якщо historical JSON має old monitoring keys.

Company/Site optional display preferences рекомендовано тримати у Monitoring-owned sidecar tables, див. §8, щоб не редагувати Company business model. PM settings через monitoring.write; current sites.write pathway може керувати тільки primary HTTP integration на Site у дозволеному scope. Розширення Developer прав на всі Monitor types не є автоматичним наслідком V2; endpoint-level policy має це перевіряти. Access bot permissions/linking не змінюються.

## 4. Time contract

**DESIGN. Три окремі поняття, один узгоджений contract:**

| Layer | Contract |
| --- | --- |
| Technical storage | UTC instants: probe/ping/evaluation/incident/event/lease/due/cert timestamps; explicit UTC clock/serialization |
| Business/calendar | **Europe/Kyiv**: day/month/week boundaries, reporting periods, daily/monthly statistics, default UI/Telegram/status display |
| Optional display | Site override → Company preference → Europe/Kyiv; user-selected view може перекрити для конкретного UI view |

Elapsed intervals/grace/deadlines задаються seconds, арифметика UTC; business timezone не означає scheduling на naive Kyiv clock. Calendar windows обчислюються Kyiv local boundaries й тільки тоді переводяться в UTC. Other display timezone не змінює due logic, day key, rollup або uptime denominator. Якщо New York view відкриває Kyiv day report, підпис лишається «Календар Europe/Kyiv», boundaries можна додатково відобразити local; не створювати враження New York-day statistics.

Для V2 використати explicit UTC DATETIME(6) для instants, якщо target DB підтримує потрібну точність; жодних implicit TIMESTAMP/ON UPDATE defaults. MySQL connection/session для Monitoring також UTC. Global app.timezone уже UTC у repository; не перемикати його на Kyiv, бо це вплине на сторонні модулі. Query Builder не нормалізує timezone автоматично: UTC DateTime/strings приймає лише storage boundary. Display formatter: parse stored UTC один раз → desired IANA timezone один раз; API instants містять Z/offset, date keys містять explicit calendar semantics.

MySQL TIMESTAMP конвертує значення через session timezone, DATETIME — ні; перехід до DATETIME все одно потребує app UTC contract ([MySQL time-zone documentation](https://dev.mysql.com/doc/refman/8.0/en/time-zone-support.html)). Це не означає міняти типи бізнес-таблиць.

**FACT.** Current cron UI `resources/views/portal/monitoring/index.blade.php:9` досі робить `parse(started_at, Kyiv)` замість parse UTC → Kyiv. Current Telegram formatter виконує одне conversion; production cause заявленого +3h не підтверджена. Не переносимо legacy timestamps у V2 і не віднімаємо 3 години масово.

**DESIGN — точний future fix/verification plan:**

1. Перед cutover звірити effective app config web/CLI, PHP після Laravel bootstrap, Monitoring DB session timezone і DB types; не виводити config dump із secrets. Не припускати actual hosting state за repository.
2. У V2 ввести явний UTC Clock/storage boundary для всіх writer/query paths; run/delivery/retention/evaluator використовують його так само, як probe. Due comparisons — UTC, elapsed budgets — monotonic clock.
3. Прибираючи legacy views, замінити всі Monitoring formatters єдиним `displayInstant(utc, zone)`; якщо old cron page тимчасово існує до cutover, виправити її parser у цьому самому implementation scope. Жодних business-module formatter refactors.
4. Реєструвати occurred_at у domain event під час transition, не під час send. Notification retry змінює sent_at, не час самої події. Сертифікат — epoch/UTC, без залежності від PHP local timezone.
5. Почати V2 з explicit activated_at UTC, new UNKNOWN. Жоден old checked/opened/closed timestamp не backfill. Legacy export за потреби — offline archive, не V2 data source.
6. Tests: UTC serialization з PHP default≠UTC; web/CLI path parity; Kyiv midnight; cron freshness і Telegram; New York view; 23/25-hour DST; MySQL session/type round-trip на ізольованій копії. Один контрольований fresh event після cutover звірити UTC→DB→Kyiv message.

**FACT — read-only Carbon verification цієї задачі:** `05.10.2026 23:20 Europe/Kyiv` = `20:20 UTC` = `16:20 America/New_York`; Kyiv 2026-03-29 має82,800s, 2026-10-25 має90,000s. Не використовувати fixed +3 або86,400s для всіх calendar days.

## 5. Monitor model

**DESIGN.** `monitoring_monitors`: id, site_id, slot(nullable primary marker), name, type(http/heartbeat/tcp), enabled, revision, custom_interval_seconds(nullable), timeout_seconds(nullable override), failure_threshold(nullable override), recovery_threshold(nullable override), recipient_mode, validated config JSON, timestamps/archived_at за потреби. Scheduling/freshness/current state — у relation `monitoring_states`, не duplicated у definition. Config не містить executable commands або plaintext credentials.

| Type | Config | State / metrics | Notes |
| --- | --- | --- | --- |
| HTTP / HTTPS | follow Site URL або explicit URL, status codes/content, optional SSL lifecycle | HTTP result, response latency, availability | SafeHttp TLS/address validation зберігається; `/` і `/health` — два monitors |
| Heartbeat | explicit expected_interval_seconds, grace_seconds, initial activation policy | last accepted ping, deadline, missed incident | Кілька jobs на Site; глобальний pull check interval не змінює backup cadence |
| TCP | hostname, allowed port, bounded connect timeout | connection result/connect latency | TCP443 не рівнозначний HTTPS/application health |
| SSL relation/lifecycle | HTTP Monitor→configured TLS target (scheme/host/port/SNI) | latest reliable expiry/fingerprint/freshness, event markers | У V1 addon HTTPS Monitor, не четвертий availability type; standalone SSL later |

Перевага nullable custom interval перед boolean+value: немає суперечливого `use_global=true, custom=17`; NULL — inherit, value — custom. Розрізняти human «manual/custom interval» і manual probe (diagnostic only).

Heartbeat має **обов'язковий job interval**, не успадковує global HTTP/TCP5min. Наприклад backup24h не може стати5min після global default change. Це type-specific config, а custom pull interval до Heartbeat не застосовується. Тайм-аут HTTP/TCP теж не timeout виконання backup job.

Effective settings resolver дає immutable snapshot з відповідними Monitor/global check revisions. Небезпечний config/target change invalidate inflight claims, закриває old incident як configuration_changed (без recovery) і починає fresh observation generation. State counters/history прив'язані до generation; old intervals завершуються, статистика не переписується.

## 6. Scheduler model

**DESIGN.** Persisted Monitoring Settings default_check_interval_seconds=300; новий HTTP/TCP Monitor має custom=NULL. Effective interval = custom ?? global default. Defaults bootstrap один раз; .env не залишається конкуруючим джерелом interval. Global settings change має checks_revision і контрольоване reschedule тільки inherited pull monitors; custom targets та Heartbeat deadlines не зачіпає.

`next_due_at` — authoritative due UTC в state. Не використовувати `last_checked_at <= now()-interval` як scheduler: цей вираз не представляє short retry, unknown/new target або lease. Для regular success/confirmed DOWN cadence прив'язати до попереднього planned slot + interval, перескочити пропущені slots без catch-up storm. Initial phases deterministic hash(monitor_id) у межах interval; immediate first check допускається із bounded initial batch. Actual detection усе одно залежить від runner capacity.

При першій непідтвердженій failure запланувати confirmation через60 elapsed seconds (default). До threshold failure chain чинний тільки у bounded freshness window; gaps скидають candidate sequence. Після confirmed DOWN — normal interval; default recovery threshold1, більші значення потребують short confirmation probes. Retry не створює другу незалежну schedule; next due бере retry reason і generation. Global interval update закриває старий observed tail за старим effective interval; майбутні freshness bounds — нові. New due розподілити phasing, не робити сотні probes одночасно.

Direct cron щохвилини запускає bounded dispatcher/evaluator; delivery і pruning мають окремі бюджети/commands у Monitoring module. Проєкт не вимагає Redis/постійного worker для першої фази, але contract має дозволяти bounded multi-worker execution. Hosting concurrency/cron/process limits — runtime gate, не припущення.

**Claiming:** due query за indexed state; коротка transaction із fixed lock order Monitor→State, атомарне встановлення claim_token/dispatch_sequence/lease_until і captured revision. Probe виконується поза transaction; commit лише коли token/sequence/revision ще чинні й не consumed. Lease expiry дозволяє новий claim, late old result відкидається. Це DB authority; file cache lock може обмежити process overlap на одному сервері, але не є єдиною гарантією. Не покладатися на MySQL8 SKIP LOCKED: supported DB version визначається перед implementation, MySQL5.7-compatible conditional claims можливі.

Timeout budget охоплює DNS + redirects + transfer, не лише кожний hop; bounded concurrency + fair earliest due ordering, без fixed250ms pause як архітектурної вимоги. Якщо DB commit/probe pipeline fail — no synthetic DOWN; UNKNOWN/operational error і lease recovery. Manual check rate-limited, має власний probe budget, не збільшує auto counters/rollups/events/schedule. Shared probe lease може заборонити manual і auto одночасно, але manual не має довго блокувати due auto.

## 7. State transition model

**DESIGN.** Розділити останній technical observation, authoritative service state, last confirmed state та freshness. `PENDING`/`MISSED` — reason/phase, не довільні нові enum availability. Public/history states: UP / DOWN / UNKNOWN / PLANNED.

Рекомендована conservative V2 policy: до підтвердження першої failure candidate window — **UNKNOWN + confirming_failure**, не confirmed DOWN і не удаваний UP. Confirmed DOWN починається з detected_at; incident started_at зберігає first_failed_at. Цей design не backdates весь candidate time як гарантований downtime. Це відмінність від V1 (який записував observed DOWN до threshold); її треба явно прийняти, див. §21.

| Trigger | State / action | Incident / event |
| --- | --- | --- |
| Enable/new target, no observation | UNKNOWN; open expected period | Без incident/alert |
| First healthy auto probe | UP із bounded freshness | Без recovery, якщо не було incident |
| Failure нижче threshold | UNKNOWN/confirming_failure, last_confirmed збережений, retry60s | Candidate evidence, ще немає down event |
| Fresh consecutive failures до threshold | DOWN з detected_at | Один incident started_at=first_failed, detected_at=now; down event |
| Next failures тієї ж причини | DOWN, refreshed valid_until | Той самий incident; raw sampling, без repeat down |
| Healthy probe за default recovery1 | UP | Close incident recovered_at; recovery event |
| Recovery threshold>1 | UNKNOWN/confirming_recovery до threshold | Incident лишається open; немає раннього recovery |
| Pull observation expired | UNKNOWN за freshness boundary | Не закривати incident як recovered; observer gap |
| Explicit maintenance / confirmed Site disable за policy | PLANNED; technical result окремо | Incident closes reason=planned, без recovery event; maintenance event опційно |
| Pause/archive/Site soft delete | Немає expected time; state UNKNOWN/paused | Close reason=paused/deleted, cancel pending alerts; без recovery |
| Resume/target generation change | Новий expected period/UNKNOWN | Old episode не «відновився» автоматично |
| Heartbeat deadline missed | DOWN reason=heartbeat_missed після evaluator confirmation | Один missed incident/event; пінг — recovery |

Для pull UP/DOWN та probe-derived PLANNED bounded observation valid_until = completed_at +2×effective interval (policy explicit). Після цього UNKNOWN, не переносити останній UP на outage runner. Transition service flushes previous state до min(next observation, valid_until), решта expected time — UNKNOWN. First observation не заповнює попередній expected time заднім числом.

Maintenance from remote control тільки primary/follow-site HTTP із explicit `respect_site_control=true`: read-only перевірка is_active=false, confirmed_state=disabled, confirmed/current versions equal. Default TCP/backup jobs не suppress за Site disabled. Plan detection на наступному Monitoring evaluation не backdates control timestamp із іншого модуля; control commands/data не змінюються. Закінчення maintenance без fresh probe → UNKNOWN, не synthetic recovery. Additional scoped manual maintenance windows можна додати окремим entity пізніше; V2 contract source/reason вже дозволяє це.

**Accounting:** expected = eligible active monitoring time; unknown включає initial, pending і freshness gaps; availability=up/(up+down); coverage=(up+down)/(expected−planned). NULL denominator → «немає даних». Пауза не planned: вона поза expected. Incident wall duration, confirmed observed down seconds і candidate duration — різні величини.

## 8. Storage model

**DESIGN.** Імена нижче — schema proposal, не створені таблиці. Legacy names можна recreate з новим monitor_id, але не reuse old rows. Усі monitoring-owned FKs мають index; жоден DROP/CASCADE не повинен спрямовуватися від Monitoring до business table.

| Proposed table | Purpose | Expected growth | Recommended retention | Key indexes / invariants |
| --- | --- | --- | --- | --- |
| monitoring_monitors | Definition/type/revision/target config | O(M), не O(checks) | Active/archived lifecycle | PK; (site_id,type); unique(site_id,slot); enabled/type selection за plan |
| monitoring_states | Current state/counters/freshness/accounting cursor/due/lease | Рівно1 row/Monitor | До видалення Monitor | unique(monitor_id); next_due_at; lease_until для recovery; active incident pointer |
| monitoring_periods | Expected active boundaries | Enable/pause/archive cycles | Detail90d; open period зберігається; rollups long-term | (monitor_id,started_at); open pointer у State; writer row lock |
| monitoring_spans | Compressed state intervals, reason/generation | Transitions/gaps, не stable checks | Target90d, quota-triggered earlier detail archive | (monitor_id,started_at), (monitor_id,ended_at); deterministic span key/accounting cursor |
| monitoring_rollups | Kyiv daily/monthly duration + latency histogram aggregate | ≤1 day row/monitor; ≤1 month row/monitor | Daily3 роки; monthly довгостроково | unique(monitor_id,grain,bucket_key); calendar fixed Kyiv; format version |
| monitoring_diagnostics | Selected redacted probe/manual/SSL evidence | Sampling quota, не всі checks | Success/unusual/SSL7d; failed/manual14d, hard max14d V1 | expires_at; (monitor_id,recorded_at); (incident_id,kind) за потреби; sample dedup key |
| monitoring_incidents | One confirmed episode, bounded evidence snapshots | Meaningful outage episodes | Long-term; archive closed records за policy | (monitor_id,started_at); (monitor_id,closed_at); State active incident id prevents duplicates |
| monitoring_events | Immutable transition/certificate event outbox | Meaningful lifecycle transitions | Delivered terminal180d; unresolved до explicit disposition | unique(monitor_id,event_sequence); (status,created_at); occurred_at |
| monitoring_deliveries | Event→recipient transport state | Events×recipient fan-out | Terminal90d; pending до retry expiry/disposition | unique(event_id,user_id,channel); (status,next_attempt_at); lease/fencing fields |
| monitoring_monitor_recipients | Explicit recipient list | O(M×R), rare settings writes | Config lifecycle | unique(monitor_id,user_id); user FK; explicit mode у definition |
| monitoring_default_recipients | Global recipients | O(R) | Config lifecycle | unique(user_id); current eligibility rechecked |
| monitoring_settings | Typed singleton defaults/revisions | Один рядок | Persistent config | PK singleton; separate check/notification/settings versions |
| monitoring_runs | Aggregated dispatcher/evaluator/delivery/prune runs | O(invocations), без per-target successful run rows |7d default; failed/interrupted30d optional | (kind,started_at), started_at/finished status for prune |
| monitoring_certificates | Reliable TLS lifecycle projection + generation/notification markers | O(HTTPS monitors); no cert row/check | Current target lifecycle; selected transitions events | unique(monitor_id,target_key); next_inspect_at; generation/fingerprint markers |
| monitoring_heartbeat_credentials | Hashed ping credential/rotation metadata |1–2 active tokens/job + bounded retired keys | Retired token hash delete після overlap/revocation audit policy | unique(public_token_id); (monitor_id,revoked_at); secret не plaintext |
| monitoring_company_preferences (optional display phase) | Company display_timezone, Monitoring-owned | O(companies with override) | Config lifecycle | company_id PK/FK; nullable/default inherit via missing row |
| monitoring_site_preferences (optional display phase) | Site display_timezone override | O(sites with override) | Config lifecycle | site_id PK/FK; відсутність row = inherit Company/Kyiv |

Heartbeat last_ping/deadline/result/last_run_id projection у State, не row на кожний ping. Окрема необмежена heartbeat_events таблиця не потрібна V1: sampled diagnostics + incidents/events достатні. Status Page entity/component relations додаються в окремій фазі; не створювати їх зараз лише для theoretical readiness.

**Spans detail quota:** compressed spans теж можуть рости за частих transitions. Candidate quota10,000 retained spans/Monitor; при досягненні archive найстаріші **завершені Kyiv days** після rollup verification, навіть якщо вони молодші90d. Не merge UP/DOWN в одну fake state. Зберігати `detail_available_from` watermark; reports до цієї межі використовують day/month summaries, intraday clipping там не підтримується. 30-day history Status Page залишається daily aggregate навіть без детальних spans. Це quota recommendation, не затверджений SLA.

Open long span/period може перетинати cutoff: roll up prefix і пересунути retained detail boundary, не drop весь active row. Incidents мають власні короткі first/confirmation/recovery evidence snapshots, щоб raw purge не знищив cause. Unbounded event/incident growth неможливо чесно зробити O(M) без втрати історії: це meaningful transitions, для них потрібні archive/retention budget і flapping policy, а не snapshot кожної check.

## 9. Raw data strategy

**DESIGN. На звичайну healthy automatic check:** update existing State; append/extend existing compressed UP span; increment existing Kyiv-day rollup count/sum/histogram; refresh certificate projection лише за наявності нової reliable observation. **Не insert unconditional monitoring_checks/diagnostics/event/incident.** DB writes залишаються — O(checks) updates потрібні для correctness, але stable rows не множаться.

На failed probe теж не зберігати кожний однаковий DOWN довічно. Current redacted error/counters оновлюються; diagnostic insert лише за first failure/confirmation/зміною normalized error signature/дозволеним periodic sample. Повтор того самого error — counters/last_seen, без duplicate body. Selected success після recovery — event evidence, не початок нової raw stream.

| Diagnostic source | Proposed sampling | Expiry |
| --- | --- | --- |
| Routine success | Maximum1 optional representative/day/Monitor; default sampling може бути0 |7d |
| Failed probes | First/cause-change/confirmation + sparse unchanged-error sample; ≤8 standalone rows/day |14d |
| Unusual latency | Threshold + dedup/cooldown; ≤4/day |7d |
| Manual check | Result повернути caller; persist із rate/quota cap, орієнтир≤20/day |14d |
| SSL evidence | Certificate generation change/error-class transition, no per-check cert dump |7d |

Спільна quota **32 diagnostic rows/Monitor/Kyiv day**, body limit наприклад2KiB redacted metadata; selection quota atomic under Monitor lock. Додатковий hard live-table cap **448 standalone diagnostic rows/Monitor** зупиняє raw inserts навіть при зламаному pruning; oldest expired samples очищаються першими. Ліміт контролюється атомарним retained-count/quota accounting, не race-prone окремим SELECT COUNT перед insert. Storage pressure збільшує suppressed counter і operational warning, не створює raw warning row на кожний probe. Не відкидати automatic transitions/metric accounting через diagnostic cap. При manual quota exhaustion результат показати, але явно зазначити, що detail не збережено. Incident minimal evidence snapshot обмежений розміром і незалежний від raw sample quota; spam protection не може silently видалити факт confirmed incident.

Daily cap не можна збільшувати без finite hard ceiling; retention settings bounded. На denied diagnostics count suppression оновлює counter, не створює «quota exceeded» event на кожну probe. Raw response body, повні cert chains, Authorization/query tokens, remote control secrets, ping credentials не зберігаються.

Таким чином sustained500-monitor outage за5min не створює144,000 identical raw failures/day; maximum standalone diagnostics за default cap —16,000/day до різних shorter expiries. Архівні incidents/events — окремий lifecycle volume, не raw table.

## 10. Incident model

**DESIGN.** Incident належить Monitor/generation, не Site; поля: started_at(first candidate/missed expected time), detected_at(confirmation), recovered_at(nullable), closed_at, close_reason, cause/error_kind, first/confirmation/recovery evidence bounded snapshots. Duration derive із UTC boundaries, не довіряти independently edited duration column. Confirmed-down time з reports може відрізнятися від started→closed wall duration через pending/UNKNOWN/maintenance.

Один active incident через State.active_incident_id та Monitor/State row-lock invariant. Insert incident і down event у тій самій transaction. Repeated failures не insert новий incident. Success після gap може закрити існуючий incident із recovery event; gap не заповнюється fake DOWN. Pause/configuration_changed/maintenance/deleted closure — окремі close_reason, без «відновився».

No manual check incidents. Event timestamps snapshot на transition, delivery не читає mutable current incident для відтворення event meaning. Після cause change в тому самому outage можна update summary/evidence з bounded history, але не надсилати нескінченні down alerts.

Flapping: retain true transitions/aggregate durations, notification cooldown/coalescing + suppressed_count; diagnosis rate limits не змінюють uptime. Optional long-term incident archive зі збереженням calendar summaries, не безумовна infinite raw/event chain.

## 11. Metrics aggregation model

**DESIGN.** `monitoring_rollups` daily row по **Kyiv calendar day**, monthly row для rollup завершених daily days. duration columns: expected/up/down/planned/unknown seconds; check counts діагностичні, не uptime denominator. Metric columns: sample_count, latency_sum/min/max, fixed histogram, schema_version. HTTP metric=response latency; TCP=connect latency; Heartbeat має completion/ping facts, не fake response_ms. Different types не об'єднувати в один «середній response сайту».

Default histogram boundaries можна reuse `[100,250,500,1000,2000,5000,10000,30000,120000]` ms +overflow; p95 верхня межа bucket, sum/count average. Будь-яка зміна buckets має version/migration merge policy. Latency тільки eligible successful auto probes, не manual/error/planned. Всі actual samples враховуються, навіть коли raw sampling0.

Accounting cursor advance в transaction once per consumed observation sequence; flush previous bounded state + UNKNOWN remainder до completed_at і split на Kyiv boundaries. Same-state span extension та rollup delta мають один cursor — retries/crash не повторюють seconds/samples. Fresh tail після persisted cursor обчислюється read-only і не входить вдруге до persisted rollup. Enable/pause/maintenance boundary flush так само. Finalizer bounded batches матеріалізує завершені дні навіть якщо немає нових probes; великий outage gap не породжує per-minute raw rows.

Daily→monthly compaction idempotent: recompute/upsert monthly з відповідних retained closed daily rows до watermark; не «increment monthly» при кожному retry. Перед purge daily місяць має бути повністю finalized/verified; finalized monthly після purge не recompute з неповного набору days. Monthly є summary того самого часу, не додатковий час: query обирає nonoverlapping grains. Lifetime ratio — sums of durations; monthly p95 — merged histograms, не середній daily p95.

Daily3y +monthly після цього зберігає багаторічні totals/latency з меншою кількістю рядків. Детальна timeline90d, daily history3y, далі monthly resolution — явно advertised accuracy/retention. Custom intraday report можливий лише в span retention window; daily latency bucket не дає exact intraday p95. Не змішувати partially clipped availability із «точною» whole-day latency без підпису; для першого backend reports — Kyiv calendar windows. Future arbitrary rolling24h metrics потребують finer buckets, не є V1 requirement.

## 12. SSL lifecycle

**DESIGN.** HTTPS Monitor має certificate relation з target identity scheme/host/port/SNI. V1 спостерігає configured HTTPS origin; final redirected HTTPS certificate можна зберігати окремим observation target, але не приписувати його початковому hostname. SafeHttp не повертає фінальний URL у current result; future adapter має explicit target attribution. Якщо потрібна незалежна оцінка starting host cert навіть за redirect, дозволений lightweight TLS inspection із тими самими pinned IP/TLS verification/address policies. Не вимикати verify для expiry збору.

Projection: last reliable fingerprint/not_before/not_after, generation, verified_at, last_error/freshness, warning_sent/critical_sent/renewal markers per generation. TLS inspect окремо bounded, орієнтир daily; observed HTTP cert data можуть refresh projection без extra raw row. Умови14d/3d рахуються elapsed time до UTC expiry, presentation Kyiv. Thresholds persisted settings default14/3, critical<warning; daily precision/delivery jitter оголосити, не обіцяти секунду перетину.

Event keys: certificate target+generation+warning14/critical3/renewed; delivery unique per event/user. Перший trustworthy observation уже≤3d → лише critical; не відправляти warning і critical одночасно. Відсутній certinfo/TLS error не стирає last reliable certificate і не означає renewal. Expired/stale cert state показується з freshness; failure TLS може також підтвердити HTTP outage, але lifecycle і outage events незалежні.

Renewal: same target, new fingerprint із later valid not_after — reliable renewal; лише expiry increase без fingerprint — lower-confidence evidence і окремий diagnostic flag. Зміна host/target generation → new baseline, не renewal. Renewal notification підтримується, default enabledness остаточно погодити. Current warning/critical markers переживають delivery history pruning, інакше spam відновиться після purge. Threshold setting change не reset cert generation; новий escalation можливий, але не duplicate identical alert.

## 13. Heartbeat design

**DESIGN.** Один job = один heartbeat Monitor: DB Backup, Files Backup, Import тощо. Config expected elapsed interval +grace; job timestamp не canonical time. Серверний UTC received_at — truth; `next_expected_at=last_accepted_ping+job_interval`, `deadline=next_expected_at+grace`. У V1 completion ping(success) без start/fail payload state machine; additional lifecycle later.

Activation recommendation: UNKNOWN і initial deadline=enabled_at+job_interval+grace. До першого ping не показувати UP; якщо initial deadline минув — missed після evaluator check. Після ping UP до deadline. Deadline evaluator runs each minute, atomically compares last accepted ping/generation з due deadline і створює один missed incident. started_at=missed deadline, detected_at=evaluation; conservative history DOWN починається з detection, gap до цього UNKNOWN. Grace — tolerance, не scheduling delay.

UP freshness визначається job deadline, **не2×global5min**. Repeated missed state не має створювати failed raw rows. DOWN trust залежить також від fresh evaluator heartbeat: при зупинці evaluator/portal stale state view UNKNOWN; incident залишається open. Persist last_evaluated_at/evaluator freshness у current projection без event на кожний tick. Відомий observer gap не backfill як гарантоване missed виконання job; після restart re-evaluate receipts/deadline і зберегти gap. External observer потрібен для повідомлення, коли сам portal не працює; internal cron не може гарантувати self-alert.

Ping/deadline race під тим самим Monitor→State lock: evaluator перевіряє captured latest receipt перед missed commit; ping після missed закриває episode й enqueue recovery. Ping до deadline перешкоджає старому missed result. Pause invalidates generation/credentials eligibility; resume UNKNOWN/new initial deadline. Heartbeat не автоматично planned від disabled HTTP Site.

Security: random256-bit bearer secret, public lookup token id, hash-at-rest, constant-time verify, HTTPS POST header credential. Secret видається один раз; rotation/revoke bounded overlap, ніякого GET secret URL. Credential body/log/query redaction, small schema-validated payload, quotas per monitor +source, existing Web/CSRF endpoint policy окремо для machine POST. `job_run_id` optional idempotency, duplicate same run не переносить deadline; latest run id у State без raw event на кожний ping. Складні out-of-order replay semantics за потреби — bounded replay store, не unlimited ping history.

Звичайний bearer не захищає від fresh replay викраденого secret: strict mode HMAC timestamp/nonce з clock-skew window — optional окреме рішення. Don't trust caller's timestamp для freshness. Rotation не запускає synthetic recovery і не змінює expected job interval.

## 14. TCP design/security

**DESIGN.** Probe connect до numeric validated pinned IP: `stream_socket_client` або еквівалентна sockets implementation, bounded timeout, fclose; connect_ms окремий metric. Для async потрібен explicit deadline/select/error check: аргумент connect timeout не обмежує async attempts/read/write автоматично ([PHP manual](https://www.php.net/manual/en/function.stream-socket-client.php)). Немає виконання shell ping/commands або довільного payload у V1 TCP.

Validate hostname/port1–65535 +admin-managed allowed ports; public target policy default. Resolve всі A/AAAA, reject private/reserved mixed answers, connect саме numeric chosen IP, не hostname повторного implicit DNS. IPv6 brackets, mapped/transition ranges, RFC1918/ULA/loopback/link-local/metadata blocked. Reuse IP classification policy, але HTTP origin exceptions не стають TCP allowlist.

Internal TCP, якщо потрібний, — окремий exact hostname+port+IP allowlist від адміністратора, без unrestricted subnet scans чи user-controlled ranges; loopback/metadata не exception. Quotas target creation/concurrency/requests, ACL, network egress controls; TCP DB port не означає DB query readiness. DNS lookup теж bounded execution concern; fallback rotation між перевіреними IP за явно погодженою policy, не нескінченний retry all hosts.

У майбутньому Shared helper дозволено доповнювати тільки narrow network policy без weakening SafeHttp/postControl behavior і з security parity tests. Access/Support не рефакторяться. Real shared-hosting sockets/egress/DNS behavior **Requires runtime verification**.

ICMP optional/later: permissions і executable/raw-socket portability не гарантовані; ICMP failure не доказ HTTP DOWN. Generic UDP V1 не включати: socket open connectionless не підтверджує remote liveness, потрібен protocol-specific response/deadline ([PHP manual](https://www.php.net/manual/en/function.stream-socket-client.php)).

## 15. Notification/event design

**DESIGN.** Domain event незалежний від incident_id: outage down/recovered, heartbeat missed/recovered, certificate warning/critical/renewed, optional maintenance. incident_id nullable, certificate identity nullable, subject завжди Monitor/generation. Snapshot: occurred_at UTC, monitor/site names і sanitized target, immutable cause/state transition; credentials не копіюються. Events sequence increment у State/cert lifecycle transaction — idempotent uniqueness, без re-emission кожний cron.

Recipient mode Monitor: inherit глобальний список або explicit list. Explicit empty = mute, distinct від inherit; не використовувати ambiguous empty array fallback. State events формуються навіть за mute, deliveries — ні. User eligibility +Monitor current enabled/recipient policy recheck перед send; revoke recipient cancels pending, але не переписує historical event. Для recovery рекомендовано зберегти current allowed recipients і receivers попереднього down для одного episode тільки якщо вони досі eligible/authorized; не надсилати людям, яких видалено з policy.

Delivery state machine pending→claimed→sent /retry_wait /cancelled /failed_terminal. Lease/fencing, attempts, next_attempt_at, transport description classification без sensitive payload. Explicit retry failures backoff5→10→…30min, max event age/retry attempts; permanent errors terminal. Невизначений external outcome після success/crash може дублювати send: exactly-once Telegram не обіцяється. Event uniqueness не є external delivery exactly-once.

Fair batches різним recipients, bounded message size/chunks, queue lag; one permanently failing recipient не блокує всіх. У sustained observer outage grouping/cooldown/coalescing без silent loss severity; queued down після recovery або доставляється як completed episode summary, або explicitly superseded згідно notification policy. Recovery без delivered down має ясний context, не повідомлення «відновився» без пояснення.

Formatter uses event occurred_at → default Kyiv або explicit Site/Company display preference і підпис timezone. Retry formatting не перезаписує start/detection instant. Transport — існуючий TelegramAccess service; Access linking/auth/webhook/secret access видача та Telegram Support незмінні.

## 16. Monitoring Settings

**DESIGN.** Окремий backend contract для `Monitoring → Settings`: typed validated singleton +recipient relations; не broad unvalidated JSON defaults. Minimal settings page implementation можлива без UX redesign. Значення, які не винесені у форму першої фази, мають один internal default source й bounded validation.

| Group | Proposed defaults / constraints | Scope |
| --- | --- | --- |
| Checks | Default interval300s; timeout6s; failure threshold2; retry60s; recovery threshold1 | HTTP/TCP nullable overrides; job intervals explicit |
| Calendar | Canonical Europe/Kyiv, не mutable user preference | Відображення окремо; не перераховувати calendar при Site preference |
| SSL | Warning14d, critical3d; critical<warning; inspection cadence bounded | Lifecycle policy independent of HTTP interval |
| Diagnostics | Success7d, failed14d, manual14d; caps32/day, max payload, sparse samples | Retention settings не вмикають unconditional raw checks |
| History | Detail90d/quota; daily3y/monthly archive; diagnostics max14d V1 | Long-term accounting з explicit precision |
| Runs/events/deliveries | Runs7d; failed30d optional; terminal events180d/deliveries90d | Pending expiry explicit, не prune unresolved silently |
| Telegram | Default eligible responsibles; renewal/cooldown policy | Monitor inherit/explicit/mute |
| Capacity | Bounded max batch/seconds/concurrency/target quotas | Admin-controlled; hardware/hosting caps не підвищуються звичайним PM |
| Later | Status Page publication/access/cache defaults | Separate phase |

Settings update transactional з ActivityLog без secrets; relevant revisions, scheduled reschedule bounded batches, не global stampede. Тайм-аут, threshold, histogram/calendar semantics не змінювати для historical rows заднім числом. Recipient update не invalidates network result; check config revision окремий від display/delivery settings version.

## 17. Scalability

**FACT.** User-reported приблизно77,878 monitoring records за тиждень20 Sites — симптом росту, але actual tables/cadence/count змішаних records не перевірені. За рівно20×5min×7days лише routine probes було б40,320; відмінність може включати інший cadence/retry/manual/aggregate rows. Не приписувати зайві records конкретній причині без DB evidence.

**DESIGN / ESTIMATE.** Для таблиці нижче один pull Monitor/Site, interval5min, без retries. Це capacity algebra, не benchmark. Heartbeats/TCP/additional HTTP додають кількість monitors незалежно від Site count.

| Scale | Probes/day | Legacy unconditional raw/30d | V2 daily rollups/year | V2 diagnostic hard upper bound14d при32/day |
| --- | --- | --- | --- | --- |
|100 Sites /100 monitors |28,800 |864,000 |36,500 |44,800 |
|200 Sites /200 monitors |57,600 |1,728,000 |73,000 |89,600 |
|500 monitors |144,000 |4,320,000 |182,500 |224,000 |
|1000 monitors |288,000 |8,640,000 |365,000 |448,000 |

Healthy routine optional raw1/day/7d дає лише700/1400/3500/7000 sampled rows; cap32 — стресова верхня межа, не план звичайного трафіку. Three-year daily horizon ≤1096×M приблизно, плюс12×M monthly rows/year; за1000 monitors це близько1.1m bounded daily rows +12k monthly/year, не8.64m raw/month. Спans stable UP — один extended row, state один рядок; flap details bounded retained quota. Подані row counts не є оцінкою MB: payload/index sizes і DB engine потребують вимірювання.

Storage efficiency не зменшує probes/DB updates/network load. Для M pull monitors interval300s потрібна nominal throughput M/300 probes/s. При average end-to-end cost c (без fixed pause), workers≈M×c/300 плюс headroom. За c=1s:100≈0.33,200≈0.67,500≈1.67,1000≈3.33 concurrent capacity; рекомендувати приблизно2/2/4/8 як **початкові кандидатні caps**, а не гарантію. При c=6s для1000 nominal20 concurrency, що може бути неприйнятним для shared hosting; збільшення budget не робить SLA реальним.

100/200: bounded sequential runner може вистачити за fast probes, але потрібні phasing/observability;500: controlled concurrency та fairness likely;1000: dedicated runner/worker capacity evaluation, shared claim store. 1min intervals множать nominal probes у5 разів. Поділ dispatcher/evaluator/delivery/prune бюджетів не дозволяє Telegram timeout забрати весь check budget.

Read model для overview reads projected states +bounded daily rollups/SQL sums, не всі raw history в PHP memory. Aggregate dashboard не має виконувати full-lifetime span traversal на кожний page request. Status Page readiness: monitor states +daily30d +sanitized incident/event projection, current freshness/UNKNOWN/planned; opt-in publication/entity/ACL/cache later, read-only. Capacity telemetry: due lag/p95 lag, claim expiries, coverage, runtime duration, diagnostics suppressed, pending delivery age, observer freshness; no run row per successful Monitor check.

## 18. Legacy Monitoring cleanup plan

**DESIGN — список для майбутньої implementation, нічого не видалено.** DROP only explicit names, dependency order; не `DROP monitoring_%` wildcard, не rollback старих mixed business migrations.

| Legacy table | V2 disposition |
| --- | --- |
| monitoring_checks | DROP; replace selective monitoring_diagnostics |
| monitoring_daily | DROP; check counts не canonical uptime; rollups замінюють |
| monitoring_metrics | DROP; latency fields/histograms у rollups |
| monitoring_periods | DROP/RECREATE monitor-owned expected periods, no old rows |
| monitoring_spans | DROP/RECREATE monitor-owned compressed bounded spans |
| monitoring_states | DROP/RECREATE unique monitor_id projection/claim, no site state |
| monitoring_incidents | DROP/RECREATE monitor-owned incidents/evidence |
| monitoring_notifications | DROP; events +deliveries замінюють incident-only queue |
| monitoring_runs | DROP/RECREATE bounded operational runs |
| monitoring_settings | DROP/RECREATE typed defaults; optional explicit recipient transfer only as config |

Obsolete Site columns — рівно перелічені monitoring_* із §3 після extraction configuration і перевірки no consumers. `sites` не drop/recreate/truncate; business columns не змінюються. Legacy settings/config можна прочитати offline для погодженого initialization, це не history migration. Default V2 primary uses global interval=NULL; старі5/17 не переносяться автоматично як обов'язкова compatibility constraint.

Obsolete implementation: SiteMonitor Site-bound state writer, current History/Report query paths, incident-only Notifications, SiteProbe signature, old Controller/routes/views/options (замінюються Monitor-centric services); old monitoring config keys interval_minutes/detail_days/daily_days (замінюються single Settings contract). Old Monitoring tests не «виправляються», щоб підтримувати хибну legacy schema: нові contracts потребують V2 tests. Useful security/time algorithms можна reuse після адаптації, не копіювати старі storage assumptions.

Integration inventory для майбутнього patch: Site model lifecycle/Metadata/Controller/routes add/show monitoring sections; Monitoring registration у Console/Provider/web routes та sidebar link. `operation-mode.blade.php` має remote-control semantics — його не видаляти тільки через слово «моніторинг». Historical migration files лишаються в repository, щоб fresh-install sequence проходив до V2 reset; не видаляти mixed migrations09-09/09-14, які містять немоніторингові дані.

**FACT — зовнішній dependency gate.** Current RunMonitoring викликає `SupportWebhookInbox::recoverInterrupted()` і cleanup pending `site_control_attempts` до checks. V2 не може просто видалити команду й залишити ці виклики без запуску. Мінімальний варіант: command wrapper з тим самим existing ancillary contract делегує тільки Monitoring частину V2; зовнішні service methods/таблиці/semantics не змінюються. Якщо їх хочуть винести в окремий portal maintenance cron — це окремо погоджена задача поза цим scope. Тут інші модулі не досліджувалися й не рефакторилися.

## 19. Safe migration concept

**DESIGN.** Future deployment steps, не виконані команди. Одна Monitoring reset migration/install operation з exact allowlist, schema version marker/recovery checks; MySQL DDL не вважати transaction rollback-safe.

1. **Preflight:** actual hosting DB/PHP/network/process compatibility, baseline code+DB snapshot, count/checksum business critical rows, decryptability test без виводу secrets. Узгодити selected configuration carryover: enabled intent, HTTP target/expectations/recipients; global interval inheritance default, без old timestamp import.
2. **Backup:** consistent database +code version; APP_KEY/.env/control credentials зберегти приватно. Git checkpoint без DB backup не відновлює business data. Legacy Monitoring backup можна залишити offline, не як live dual writer.
3. **Stop:** тільки Monitoring cron/workers, drain in-flight HTTP/delivery/claims; коротке узгоджене portal write maintenance window для атомарного Site extraction/cutover. Не змінювати webhook чи зупиняти/перебудовувати Support logic для цього patch.
4. **Deploy coordinated V2 code:** new Site integration не звертається до old monitoring columns/history. До schema ready Monitoring entrypoint disabled, не стартувати старий/новий writer одночасно.
5. **Extract initialization manifest перед DROP:** non-deleted Site IDs, enabled intent і погоджений HTTP config/recipients у private bounded snapshot. Відновлюваний installer має stage marker до destructive step; не перечитувати вже видалені columns після часткового DDL. Не містить business credentials чи legacy statistics/timestamps.
6. **Reset only allowlisted10 old Monitoring tables:** спочатку notifications FK dependents, решта exact names; create V2 definitions/state/history/rollups/events/etc. Ніякого business table truncate, FK-wide cascade reset або down() старих mixed migrations. Site IDs/rows/company relations/control tables immutable invariant.
7. **Initialize config/default monitors:** singleton Settings, per-Site primary HTTP with custom interval=NULL; unique slot/upsert +manifest stage для retry. Enable intent із old snapshot; new UNKNOWN/current cursor/period починаються activated_at, не old history. Це targeted initializer, **не загальний UserSeeder/db:seed**.
8. **Drop only obsolete Site monitoring columns** після V2/integration readiness та mapping verification. Non-monitoring Site revisions/ActivityLog лишаються. Частковий DDL retry не дублює monitors і не вдруге скидає вже нові V2 rows.
9. **Verify before cron:** business counts/IDs/relations/config hashes/credentials/control-version data з baseline; Monitoring schema invariants; isolated controlled HTTP down/recovery, heartbeat, TCP policy, SSL generation/time path і no unconditional raw. Site Control integration test без справжнього вимкнення чужих сайтів.
10. **Enable same approved Monitoring schedule:** phased initial due/short bounded canary; delivery окремий budget; verify UTC event→DB→Kyiv, due lag/coverage/storage sampling. Для actual live notification потрібна явно контрольована verification target/user.

Rollback після reset — узгоджена code+DB backup restore або forward repair; old application code сам по собі не працює з V2 schema. Fresh install: old migrations виконуються у звичайному порядку, потім V2 reset; existing upgrade: лише V2 cutover. MySQL5.7/strict mode/partial DDL ізольовано перевірити. Не передбачати автоматичний down(), який drop sites або запускає старі business seeders.

**Не виконано:** DB queries/migrations/destructive commands/deploy/cron/config changes. Дозвіл відкинути Monitoring history описує майбутній scope, а не authorization виконувати reset у цій architecture задачі.

## 20. Implementation phases

**DESIGN.** Конкретні майбутні етапи після окремого implementation запиту:

| Phase | Work | Acceptance gate |
| --- | --- | --- |
|0 — Decisions/preflight | Прийняти state candidate policy, initialization manifest, retention defaults; actual hosting/capacity evidence | Немає випадкових legacy compatibility assumptions; business preservation checklist exact |
|1 — V2 core contracts | Monitor/type config, UTC clock/Kyiv calendar, Settings resolver, states/claims/revisions, compressed history/rollups | Null inheritance, DST23/25h, freshness/unknown/planned, idempotent accounting; MySQL compatibility |
|2 — HTTP/incident/event pipeline | SafeHttp adapter, confirmation/recovery, selective diagnostics, immutable events/deliveries через current Access transport | Normal success не insert raw; sustained DOWN sampled; crash/retry/stale results; no remote-control writes |
|3 — Safe reset/Site integration | Allowlisted migration/initializer, Site hooks/URL/slot, minimal Settings/Site summary/backend endpoints | Existing business rows/IDs/control secrets+versions unchanged; partialDDL retry; no old history consumers |
|4 — SSL lifecycle | Reliable certificate target/fingerprint state,14/3/renewal dedup/message timezone | Temporary errors не renewal; no raw-per-check; correct redirected target attribution |
|5 — Heartbeat | Hashed credentials/ping/deadline, missed/recovery, quotas/rotation | Concurrent ping/evaluator, initialUNKNOWN, observer gap, no per-ping raw stream |
|6 — TCP/capacity | Narrow target policy, pinned connect adapter, bounded concurrency/fair claims | Private/metadata/IPv6/rebinding/port tests; failed-diagnostics cap; process budget evidence |
|7 — Projection/UX handoff | Compact read DTOs/report APIs/settings; public Status backend contract |30-day daily history, unknown/planned, sanitized opt-in visibility/caching; UX separate |

Core model одразу має всі3 Monitor types, але незавершений adapter type disabled/feature-gated до tests. Production cutover не зобов'язаний відбуватися після кожної фази: можна завершити потрібні adapters/settings до одного запуску V2. Legacy history не потрібно підтримувати для проміжних development milestones. Capacity testing/production verification не замінювати алгеброю §17.

## 21. Open decisions

**OPEN — лише незакриті product/operational choices.** Clean Monitor-centric V2, UTC storage, Kyiv calendar, display-only other zones, global interval inheritance, fresh statistics і business preservation вже задані; їх не потрібно перепитувати.

1. **Pending failure accounting:** прийняти рекомендований UNKNOWN до confirmation, DOWN з detection (incident started з first failure), або вимагати ретроспективного bounded DOWN candidate history? Другий варіант змінює rollup correction contract і потребує окремого правила.
2. **Configuration carryover:** чи перенести existing HTTP target/content/status expectations/recipients, або почати default Site URL/global recipients? Recommended: enabled intent+explicit meaningful HTTP config/recipients зберегти; all intervals спочатку inherit global, custom після explicit review.
3. **Retention/storage budget:** погодити candidate raw7/14d, cap32/day, spans90d/quota10k, daily3y/monthly long-term та diagnostics size. Це target bound, не historical compatibility requirement.
4. **Renewal messages:** default ON чи opt-in OFF? Warning14/critical3 і dedup потрібні в обох випадках. SSL initial vs final redirected hostname attribution має визначену target identity, але які host targets автоматично включати — product choice.
5. **Heartbeat producer contract:** V1 completion-only POST+job_run_id достатній чи потрібні start/failure або strict signed replay protection одразу? Interval/grace від job; calendar cron expressions later.
6. **Deployment capacity / TCP exceptions:** hosting process/egress limits, acceptable due/alert lag; чи потрібні internal DB TCP targets і який exact admin allowlist. Це runtime/user data, не факт репозиторію.
7. **Optional display preferences та future publication:** Company/Site overrides у першому backend patch чи окремій display phase; Status Page public/private/token/password policy — перед її окремою реалізацією, не блокер core.

**Validation цієї architecture задачі:** перевірено current direct integration points, виконано pure Carbon examples/DST boundaries та arithmetic storage estimates; application tests не перезапускали, бо code/schema не змінювалися. Попередній audit scoped22tests/128assertions не оголошується валідацією ще не реалізованого V2. Application code, DB/schema, migrations, legacy tables, cron, runtime config, Access/Support і deployment у цій задачі не змінено. Наступний крок — окрема implementation-задача після перегляду документа.
