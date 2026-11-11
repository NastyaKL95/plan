# Planner: MAX уведомления и мониторинг

## Расписание воркера MAX

Очередь MAX обрабатывается скриптом:

- `public/cron/max_notify_worker.php`

Рекомендуемое расписание: **каждую минуту**.

### Linux / хостинг (planiro75.ru и др.)

Сначала найдите **корень проекта** на сервере (где лежат `public/`, `includes/`, `config/`):

```bash
cd ~
find . -maxdepth 5 -path '*/public/cron/max_notify_worker.php' 2>/dev/null
# или
find . -maxdepth 4 -name 'bootstrap.php' -path '*/includes/*' 2>/dev/null
```

Запомните каталог (например `/home/u3508000` или `/home/u3508000/www/другой-путь`).
Путь `~/www/planiro75.ru` на части серверов **отсутствует** — не используйте его вслепую.

Ручной запуск из корня проекта (`PROJECT` — найденный путь):

```bash
cd PROJECT
php public/cron/max_notify_worker.php
```

**Crontab** — лучше через обёртку (сама ищет `php` и пишет лог):

```cron
* * * * * /bin/sh /home/ВАШ_ЛОГИН/www/planiro75.ru/public/cron/run_max_notify_worker.sh
```

Права на скрипт (один раз по SSH):

```bash
chmod +x ~/www/planiro75.ru/public/cron/run_max_notify_worker.sh
```

Проверка лога:

```bash
tail -20 ~/www/planiro75.ru/var/log/max-cron.log
```

Если вручную работает, а cron нет:

1. Узнайте путь к PHP: `which php` (тот же, что в SSH).
2. В crontab укажите **полные пути**, без `ВАШ_ЛОГИН` — подставьте логин с сервера (`whoami`).
3. Вариант без обёртки (обязательно `cd` в корень проекта):

```cron
* * * * * cd /home/LOGIN/www/planiro75.ru && /usr/bin/php public/cron/max_notify_worker.php >> /home/LOGIN/www/planiro75.ru/var/log/max-cron.log 2>&1
```

4. На shared-хостинге cron часто настраивают в **панели** (ISPmanager, cPanel), а не в `crontab -e`.

### Windows Task Scheduler (OSPanel)

- Program/script: `C:\OSPanel\modules\PHP-8.1\PHP\php.exe`
- Arguments: `-f "c:\www\OSPanel\home\planner\public\cron\max_notify_worker.php"`

Пример также есть в `.osp/tasks.ini`.

## Проверка, что cron включен

Воркер пишет heartbeat в файл:

- `var/log/max-worker-heartbeat.json`

Проверка доступна через UI профиля (блок "Мониторинг MAX") и API:

- `GET /api/max_metrics.php`

Если heartbeat не обновляется, в алертах появится сообщение о проблеме с cron.

## Тест сообщения в MAX

Endpoint для проверки канала:

- `POST /api/max_test.php`

Из профиля можно отправить тест через кнопку **"Тест сообщения"**.

## Персональные подписки MAX

Пользователь может в профиле включать/выключать типы уведомлений MAX (назначение, статус, дедлайн, mentions и т.д.).

Для этого нужна таблица:

- `user_max_prefs` (миграция `sql/migration_user_max_prefs.sql`)

## Расширенные возможности MAX (SLA, quiet hours, DLQ, диагностика)

Нужна миграция:

- `sql/migration_max_advanced.sql`

Что добавлено:

- SLA-эскалации:
  - `sla_due_2h` (срок через 2 часа),
  - `sla_overdue_24h` (просрочка > 24 часов),
  - `sla_stale_days` (нет движения X дней).
- Quiet hours (отложенная доставка некритичных событий).
- Идемпотентность отправки через `message_id` / `X-Idempotency-Key`.
- DLQ для окончательно неудачных отправок: `notification_events_dlq`.
- Админ API:
  - `GET/PATCH /api/max_settings.php`
  - `GET /api/max_queue_list.php`
  - `GET /api/max_queue_export.php`
  - `POST /api/max_retry_failed.php`
  - `POST /api/max_webhook.php?secret=...`

## Команды бота в чате

Webhook endpoint:

- `POST /api/max_webhook.php?secret=<webhook_secret>`
- `POST /api/max_webhook_test.php` (внутренний тест в админке)

Поддерживаемые команды:

- `/start`
- `/help`
- `/settings`
- `/settings assigned|status|due|mention|created|participant|sla_due|sla_overdue|sla_stale on|off`
- `/settings digest <minutes|off>`
- `/settings quiet on|off`

## Интерактивные действия из сообщения

В сообщениях о назначении/SLA генерируются защищённые ссылки-действия:

- открыть задачу,
- взять в работу,
- отметить выполненной.

Технически:

- endpoint `public/max_action.php`
- таблица токенов `max_action_tokens`

## Метрики и алерты очереди

API:

- `GET /api/max_metrics.php`

Возвращает:

- счетчики `pending/sent/failed/cancelled`
- возраст самого старого `pending`
- heartbeat воркера
- список алертов (включая `failed > 0` и старые `pending`)
