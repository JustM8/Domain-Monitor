# Оновлення моніторингу через FTP

Інструкція відповідає патчу поверх базового коміту **73375d8**. Сам базовий коміт містить попередні зміни порталу, а не лише цей моніторинг. Якщо вони вже встановлені на хостингу, повторно завантажувати весь базовий коміт не потрібно.

Деплой автоматично не виконувався. Усі шляхи нижче відносні до кореня Laravel-проєкту на хостингу — каталогу, де лежить artisan.

## 1. Передумови

- PHP 8.2+ із чинними розширеннями Laravel, cURL, OpenSSL і PDO MySQL.
- На хостингу вже встановлений попередній портал із модулем Monitoring та міграціями до **2026_09_14_000001_add_recovery_and_control_history** включно.
- Перевірити через SSH/термінал хостингу:

~~~sh
php -v
php artisan migrate:status
~~~

Якщо ця попередня міграція не має статусу Ran або відсутня, спочатку встановити попередній патч за DEPLOYMENT.md. Цей FTP-перелік не містить усіх його залежностей.

Перед змінами зберегти резервну копію бази й коду. .env та APP_KEY залишити чинними: новий ключ зламає розшифрування збережених доступів.

## 2. Які папки та файли завантажити

Завантажити **лише перелічені файли**, накладаючи їх у відповідні папки. Не очищати й не замінювати каталоги видаленням: у них є інші потрібні файли.

### Робочий PHP-код

~~~text
app/Modules/Monitoring/Console/RunMonitoring.php
app/Modules/Monitoring/Http/Controllers/MonitoringController.php
app/Modules/Monitoring/Services/MonitoringHistory.php
app/Modules/Monitoring/Services/MonitoringNotifications.php
app/Modules/Monitoring/Services/MonitoringOptions.php
app/Modules/Monitoring/Services/MonitoringReport.php
app/Modules/Monitoring/Services/SiteMonitor.php
app/Modules/Monitoring/Services/SiteProbe.php
app/Modules/Monitoring/config.php
app/Modules/Monitoring/routes/web.php
app/Modules/Shared/Http/SafeHttp.php
app/Modules/Site/Http/Controllers/SiteController.php
app/Modules/Site/Models/Site.php
app/Modules/Site/Services/SiteMetadataService.php
~~~

### Відображення і форми

~~~text
resources/views/portal/monitoring/index.blade.php
resources/views/portal/monitoring/options.blade.php
resources/views/portal/monitoring/report.blade.php
resources/views/portal/monitoring/show.blade.php
resources/views/portal/sites/add.blade.php
resources/views/portal/sites/operation-mode.blade.php
resources/views/portal/sites/show.blade.php
~~~

### Нова міграція

~~~text
database/migrations/2026_09_19_000001_extend_site_monitoring.php
~~~

**Для роботи патча обов’язкові 22 файли вище.** Документацію та тести із повного переліку нижче можна зберігати лише в локальному репозиторії; на виконання порталу вони не впливають.

### Повний перелік змінених і нових файлів цього коміту

Це точний перелік патча відносно базового коміту, включно з документацією і тестами. Видалених файлів у цьому патчі немає.

~~~text
AGENTS.md
app/Modules/Monitoring/config.php
app/Modules/Monitoring/Console/RunMonitoring.php
app/Modules/Monitoring/Http/Controllers/MonitoringController.php
app/Modules/Monitoring/routes/web.php
app/Modules/Monitoring/Services/MonitoringHistory.php
app/Modules/Monitoring/Services/MonitoringNotifications.php
app/Modules/Monitoring/Services/MonitoringOptions.php
app/Modules/Monitoring/Services/MonitoringReport.php
app/Modules/Monitoring/Services/SiteMonitor.php
app/Modules/Monitoring/Services/SiteProbe.php
app/Modules/Shared/Http/SafeHttp.php
app/Modules/Site/Http/Controllers/SiteController.php
app/Modules/Site/Models/Site.php
app/Modules/Site/Services/SiteMetadataService.php
database/migrations/2026_09_19_000001_extend_site_monitoring.php
docs/DEPLOYMENT.md
docs/MODULE-MAP.md
docs/MONITORING-DEPLOY.md
docs/MONITORING-PATCH-FILES.txt
docs/MONITORING-PATCH.md
docs/MONITORING-PLAN.md
docs/PROJECT-CONTEXT.md
README.md
resources/views/portal/monitoring/index.blade.php
resources/views/portal/monitoring/options.blade.php
resources/views/portal/monitoring/report.blade.php
resources/views/portal/monitoring/show.blade.php
resources/views/portal/sites/add.blade.php
resources/views/portal/sites/operation-mode.blade.php
resources/views/portal/sites/show.blade.php
tests/Feature/MonitoringPatchTest.php
tests/Feature/OutboundSecurityTest.php
~~~

Машиночитний дубль: [MONITORING-PATCH-FILES.txt](MONITORING-PATCH-FILES.txt).

## 3. Порядок завантаження та Laravel-команди

1. Тимчасово вимкнути **наявне завдання cron monitoring:run** у панелі хостингу та дочекатися завершення його поточного процесу.
2. У корені **порталу** ввімкнути коротке вікно обслуговування перед FTP-завантаженням:

~~~sh
php artisan down --retry=60
~~~

3. Завантажити 22 робочі файли зі збереженням структури папок. Не завантажувати локальний .env, vendor, node_modules або storage.
4. Після завантаження виконати в тому самому корені порталу:

~~~sh
php artisan config:clear
php artisan migrate --path=database/migrations/2026_09_19_000001_extend_site_monitoring.php --force
php artisan config:cache
php artisan view:clear
php artisan route:cache
php artisan migrate:status
php artisan up
~~~

У прикладах php означає фактичний CLI PHP 8.2+ вашого хостингу. Якщо стандартний php старіший, у кожній команді замінити його на повний шлях до потрібної версії.

Якщо міграція чи кешування завершилися помилкою, спочатку з’ясувати причину; cron поновлювати після успішного завершення оновлення. Не запускати db:seed, migrate:fresh чи key:generate.

Нова міграція:

- додає параметри моніторингу до sites;
- переносить наявний глобальний інтервал MONITORING_INTERVAL_MINUTES до налаштувань сайтів (типово 5 хв, допустимі межі 1–60);
- залишає автоматичними наявні Prod, а Dev — ручними;
- додає поля до monitoring_states, monitoring_checks, monitoring_incidents;
- створює monitoring_periods, monitoring_spans, monitoring_metrics для часової історії;
- не видаляє наявні перевірки/інциденти й не змінює ключі, режими або версії дистанційного керування;
- починає нову часову статистику з моменту оновлення. Невідомі тривалості зі старих лічильників не вигадуються.

## 4. Cron

Змінити розклад **наявного** monitoring:run із кожних 5 хвилин на **щохвилини**. Якщо він уже щохвилини, розклад залишити. Друге завдання не створювати.

Для crontab:

~~~text
* * * * * /шлях/до/php82/bin/php /home/ACCOUNT/PORTAL/artisan monitoring:run >> /home/ACCOUNT/monitoring.log 2>&1
~~~

У графічній панелі хостингу: період «кожну хвилину»; у полі команди вказати частину починаючи зі шляху PHP, без п’яти зірочок. Замінити ACCOUNT, PORTAL і шлях PHP фактичними значеннями — шляхів сервера в локальному проєкті немає.

Зберегти захист від одночасних прогонів; CACHE_DRIVER=file на одному сервері та право запису storage/framework/cache. schedule:run і queue:work для цього модуля не потрібні.

Реальний інтервал задається в картці кожного сайту. Щохвилинний запуск дозволяє швидку повторну перевірку після невдачі; ліміти порції/часу та повільні сайти можуть затримати її.

## 5. .env та public/build

**Обов’язкових нових змінних .env немає.** Чинні токени ботів, webhook-секрети, APP_KEY та підключення БД зберегти.

Наявні MONITORING_BATCH_SIZE, MONITORING_MAX_SECONDS, MONITORING_PAUSE_MS, MONITORING_DETAIL_DAYS та MONITORING_DAILY_DAYS лишаються чинними. MONITORING_INTERVAL_MINUTES використовується при перенесенні старих налаштувань і як типове значення створення через модель; після міграції він не перевизначає інтервал кожного сайту. Стандартний бюджет прогону — 240 с; надто малий бюджет може не вмістити перевірку з кількома редиректами.

**public/build оновлювати не потрібно.** Графіки цього патча сформовані у Blade/SVG. package.json, composer.json, JS/CSS-джерела та lang не змінювалися; npm build і повторне встановлення залежностей для цього патча не потрібні.

## 6. Перевірка після оновлення

1. Під Admin відкрити «Сайти → Додати». Переконатися, що є два режими роботи й окреме «Перевіряти через cron». У наявних картках ключі та режим керування мають залишитися попередніми.
2. Відкрити сторінку моніторингу сайту під PM: змінити інтервал і зберегти, перезавантажити сторінку, перевірити збережене значення. Обрати відповідальних або лишити глобальний список. Developer не повинен отримати керування вимкненням.
3. Після поновлення cron перевірити останній завершений прогон, час автоматичної перевірки та новий запис із позначкою Cron. За потреби виконати один контрольний прогон із термінала (він реально перевірить увімкнені сайти та може надіслати повідомлення):

~~~sh
php artisan monitoring:run
~~~

4. Натиснути «Перевірити зараз». Має з’явитися ручний запис; автоматичний розклад, лічильник невдач і статистика не повинні змінитися.
5. Для перевірки інцидентів використати **власний тестовий URL**, який контрольовано повертає 500/503. Увімкнути для нього cron, інтервал 1 хв, поріг 2. Після першої невдачі — очікування підтвердження, після другої — один інцидент і повідомлення. Повернути URL до 200: наступна автоматична перевірка закриває інцидент і надсилає відновлення. Робочі сайти вимикати для цієї перевірки не потрібно.
6. Вибрати день/тиждень/місяць/власні дати. Перевірити доступність, покриття, графік, тривалість інциденту. На новій установці історія спочатку коротка — це очікувано.
7. Для тестового сайту вимкнути автоматичний моніторинг: перевірки припиняться, час паузи не збільшуватиме простій. Після поновлення накопичення продовжиться. Прогалини cron довші за два інтервали показуються як відсутність даних.
8. TLS-відомості відображаються після успішної HTTPS-перевірки, якщо cURL надав certinfo; їх відсутність для HTTP або TLS-помилки не є помилкою міграції.
9. Спроба перейти у «лише моніторинг» для вимкненого керованого сайту повинна вимагати спочатку підтверджене ввімкнення. Секрети та протокол дочірньої інтеграції не змінені.

## 7. Відкат і межі перевірки

Відкат — попередній код разом із відповідною резервною копією бази. down() нової міграції прибирає нові параметри й часову історію; не запускати її як звичайне очищення.

Фінально: 54 тести, 389 assertions, синтаксис 201 PHP-файлу та Pint — успішно. Локально перевірено PHP 8.3, SQLite, HTTP/Telegram-заглушки, міграцію в обидва боки та збереження наявних ключів/режимів/інтервалу. Реальний MySQL хостингу, реальні повідомлення й cron агентом не змінювалися. Перед оновленням продуктивної бази перевірити міграцію на її копії.


## Відновлення після MySQL 1067: Invalid default value for ended_at

Якщо старий варіант цієї міграції впав на створенні monitoring_spans, попередні ALTER/CREATE могли вже застосуватися. Виправлений файл можна запустити повторно: він додає лише відсутні поля/таблиці, не скидає вже перенесені налаштування й не дублює періоди.

На сервер повторно завантажити **лише**:

~~~text
database/migrations/2026_09_19_000001_extend_site_monitoring.php
~~~

Виправлення використовує DATETIME для меж періодів/спостережень. На MySQL воно також виправляє TIMESTAMP у вже створеній monitoring_periods, прибираючи неявний ON UPDATE без видалення даних.

Вимкнути cron, зберегти резервну копію бази, завантажити файл і виконати з кореня порталу:

~~~sh
php artisan down --retry=60
php artisan config:clear
php artisan migrate --path=database/migrations/2026_09_19_000001_extend_site_monitoring.php --force
~~~

**Лише після успішної міграції**:

~~~sh
php artisan config:cache
php artisan view:clear
php artisan route:cache
php artisan up
~~~

Після цього повернути cron щохвилини. Очищення кешів саме по собі не завершує міграцію. Не видаляти створені таблиці, не запускати migrate:rollback/migrate:fresh і не додавати запис про виконання вручну в migrations.

Перевірка виправлення: на окремому локальному MySQL 5.7.44 зі strict mode та explicit_defaults_for_timestamp=OFF відтворено помилку старого коду, перевірено відновлення з того самого часткового стану, повторний запуск, збереження налаштувань/ключів/історії та чисте встановлення/відкат. Це ізольована перевірка міграції, не прогін усього застосунку на MySQL і не зміна бази хостингу.
