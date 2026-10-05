# Support Attribution Patch

Дата: 2026-09-24.

## Що змінено

Патч розділяє два поняття в Telegram-підтримці:

- відповідальні за напрям у `support_topic_user` - організаційне призначення для груп/тем;
- фактичний виконавець - користувач порталу, який реально відповів або закрив тікет з Telegram.

Support webhook тепер визначає автора staff-повідомлення з Telegram-групи за `message.from.id` і шукає підтверджений портал-акаунт у `users.telegram_chat_id`. Якщо користувач активний, approved і має `telegram_verified_at`, його `users.id` записується в `support_messages.sent_by_user_id`.

Telegram username лишається snapshot-даними для відображення й аудиту. У `support_messages.payload` додатково зберігаються `source_user_id`, `source_username`, `source_first_name`, `source_last_name`, `source_message_id`, `source_chat_id`.

Закриття тікета з Telegram-команди `/close` або callback теж записує `support_tickets.closed_by_user_id`, якщо автора вдалось верифікувати через портал-акаунт.

## UI

У картці тікета staff-повідомлення показують автора:

- `Менеджер: {портальне ім'я} (@telegram_username)` для верифікованого користувача;
- `Staff (@username, не верифіковано)` для Telegram-відповіді без прив'язки до порталу;
- `Staff`, якщо немає жодних даних про автора.

## Аналітика

`SupportAnalyticsController` тепер повертає два різні набори:

- `byManager` - фактична робота менеджерів за `support_messages.sent_by_user_id` і `support_tickets.closed_by_user_id`.

Графік і таблиця “Топ менеджери” використовують фактичну роботу, а не дублюють всі тікети теми між усіма призначеними відповідальними.

Фактична таблиця менеджерів показує:

- унікальні тікети, де менеджер відповідав або закривав;
- кількість staff-відповідей;
- кількість перших staff-відповідей;
- кількість закритих ним тікетів;
- середній час першої відповіді;
- середню оцінку;
- окремий рядок для неверифікованих staff-відповідей.

## Міграції

Нові міграції не потрібні. Патч використовує наявні поля:

- `support_messages.sent_by_user_id`;
- `support_messages.telegram_username`;
- `support_messages.payload`;
- `support_tickets.closed_by_user_id`;
- `users.telegram_chat_id`, `users.telegram_username`, `users.telegram_verified_at`.

## Файли для викладки

Оновити на хостингу:

- `app/Modules/TelegramSupport/Services/SupportUpdateHandler.php`
- `app/Modules/TelegramSupport/Http/Controllers/SupportAnalyticsController.php`
- `resources/views/portal/support/show.blade.php`
- `resources/views/portal/support/analytics.blade.php`
- `docs/SUPPORT-ATTRIBUTION-PATCH.md`

Для тестового середовища також додано:

- `tests/Feature/SupportTelegramAttributionTest.php`

`resources/js/app.js` не змінювався. `public/build` оновлювати не потрібно, якщо на хостингу не запускається окрема збірка Blade/assets з інших змін.

## Дії після викладки

1. Запустити очищення кешу Laravel:

```bash
php artisan optimize:clear
```

2. Міграції для цього патчу не потрібні.

3. Менеджери, які мають враховуватись у фактичній статистиці, повинні зайти у свій профіль порталу й підтвердити Telegram через Access bot.

4. Після підтвердження менеджер має відповідати в Support Telegram-групах саме з цього Telegram-акаунта. Якщо відповідь прийде з іншого або непідтвердженого акаунта, вона буде доставлена клієнту, але в аналітиці потрапить у “неверифіковані staff-відповіді”.

## Перевірка

Додано feature-тести на:

- атрибуцію відповіді з Telegram-групи верифікованому користувачу порталу;
- доставку неверифікованої відповіді без `sent_by_user_id`;
- запис `closed_by_user_id` при закритті з Telegram;
- відокремлення фактичної аналітики менеджерів від призначених відповідальних за напрям.
