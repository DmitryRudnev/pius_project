# movie_summary_tg_bot

## Название и назначение сервиса

**movie_summary_tg_bot** — Telegram-бот для пересказа фильмов в заданном стиле. Проект построен на микросервисной архитектуре и включает три сервиса:

1. **telegrambot-service**: Основной сервис, обрабатывающий входящие сообщения от Telegram и взаимодействующий с другими микросервисами.
2. **database-service**: Сервис для работы с базой данных PostgreSQL, хранящей информацию о пользователях (Telegram ID, дата окончания подписки, количество запросов и дата последнего запроса).
3. **deepseek-service**: Сервис для генерации текстового пересказа фильмов через API DeepSeek.

Бот позволяет пользователям:

- Указать фильм для пересказа (`/set_movie`).
- Выбрать стиль пересказа (`/set_style`, по умолчанию — "Бухой дед").
- Сгенерировать пересказ (`/generate_summary`).
- Просмотреть информацию о подписке и лимитах (`/info`).
- Оформить подписку для увеличения лимита запросов (`/subscribe`, временно недоступно).

## Архитектура и зависимости

### Технологии и фреймворки

- **PHP 8.1+** и **Laravel 10** — основной фреймворк для всех микросервисов.
- **PostgreSQL** — база данных для хранения пользовательских данных.
- **LocalTunnel** — инструмент для создания публичного домена из localhost.
- **Guzzle HTTP Client** — для отправки HTTP-запросов между микросервисами и к внешним API.
- **DeepSeek API** — внешний сервис для генерации текстов.
- **Laravel Cache (file driver)** — для временного хранения настроек пользователей.

### Взаимодействие микросервисов

- **telegrambot-service**:
  - Отправляет запросы к `database-service` для проверки лимитов, получения информации о пользователе и обновления счётчика запросов.
  - Отправляет запросы к `deepseek-service` для генерации пересказа.
  - Взаимодействует с Telegram API для отправки сообщений и регистрации команд.
- **database-service**:
  - Предоставляет API для работы с данными пользователей (`/api/user-info`, `/api/check-limits`, `/api/increment-limits`).
- **deepseek-service**:
  - Предоставляет API для генерации текста (`/api/generate-text`) через DeepSeek.

### Используемые внешние сервисы

- **Telegram API** — для взаимодействия с Telegram-ботом.
- **DeepSeek API** — для генерации текстовых пересказов.
- **LocalTunnel** — для проброса локального сервера на публичный домен (piusbot.loca.lt).

## Способы запуска сервиса

### Требования

- PHP 8.1+
- Composer
- PostgreSQL 13+
- Node.js и npm (для LocalTunnel)
- PhpStorm или любой текстовый редактор
- Терминал PhpStorm / командная строка 

### Локальный запуск

1. **Клонируйте репозиторий** (если используется Git):

   ```bash
   git clone https://github.com/DmitryRudnev/pius_project
   ```

2. **Установите зависимости для каждого микросервиса**: Перейдите в директории `telegrambot-service`, `database-service`, `deepseek-service` и выполните:

   ```bash
   composer install
   ```

3. **Настройте переменные окружения**: Скопируйте `.env.example` в `.env` в каждой директории и заполните параметры:

   - `telegrambot-service/.env`:

     ```env
     APP_URL=http://localhost:8000
     TELEGRAM_BOT_TOKEN=<your-telegram-bot-token>
     DATABASE_USER_INFO_URL=http://localhost:8001/api/user-info
     DATABASE_CHECK_LIMITS_URL=http://localhost:8001/api/check-limits
     DATABASE_INCREMENT_LIMITS_URL=http://localhost:8001/api/increment-limits
     DEEPSEEK_SERVICE_URL=http://localhost:8002/api/generate-text
     ```

   - `database-service/.env`:

     ```env
     APP_URL=http://localhost:8001
     DB_CONNECTION=pgsql
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_DATABASE=tg_bot
     DB_USERNAME=postgres
     DB_PASSWORD=<your-postgres-password>
     ```

   - `deepseek-service/.env`:

     ```env
     APP_URL=http://localhost:8002
     DEEPSEEK_TOKEN=<your-deepseek-api-token>
     ```

4. **Создайте базу данных**: В pgAdmin4(или другом инструменте для управления базой данных) создайте базу данных `tg_bot`.

5. **Примените миграции**: В директории `database-service` выполните:

   ```bash
   php artisan migrate
   ```

6. **Запустите микросервисы**: В отдельных терминалах для каждой директории выполните:

   ```bash
   php artisan serve --port=8000  # telegrambot-service
   php artisan serve --port=8001  # database-service
   php artisan serve --port=8002  # deepseek-service
   ```

7. **Настройте LocalTunnel**: Установите LocalTunnel:

   ```bash
   npm install -g localtunnel
   ```

   Запустите туннель:

   ```bash
   lt --port 8000 --subdomain piusbot
   ```

## API документация

### OpenAPI/Swagger

Документация API для каждого микросервиса описана в файлах `public/docs/openapi.yaml`, соответствующих стандарту OpenAPI. Эти файлы содержат спецификации всех доступных эндпоинтов, включая параметры, запросы, ответы и возможные ошибки.

Для просмотра и тестирования API используется Swagger UI, файлы которого размещены в директории `public/swagger` каждого микросервиса. Swagger UI основан на версии 5.27.1 из официального репозитория (https://github.com/swagger-api/swagger-ui).

#### Просмотр документации

1. **Запустите микросервисы**:
   Следуйте инструкциям из раздела "Локальный запуск", чтобы запустить `telegrambot-service` (порт 8000), `database-service` (порт 8001) и `deepseek-service` (порт 8002).

2. **Настройте Swagger UI**:
   - Убедитесь, что файл `public/swagger/swagger-initializer.js` ссылается на соответствующий `openapi.yaml`. 
   - Откройте в браузере:
     - Для `telegrambot-service`: `http://localhost:8000/swagger`
     - Для `database-service`: `http://localhost:8001/swagger/`
     - Для `deepseek-service`: `http://localhost:8002/swagger/`

3. **Использование Swagger UI**:
   - Swagger UI предоставляет интерактивный интерфейс для просмотра документации API.
   - Вы можете отправлять тестовые запросы к эндпоинтам прямо из интерфейса, указав необходимые параметры (например, `telegram_id` или `prompt`).
   - Проверьте корректность ответов API, включая коды состояния и формат JSON.

#### Примечания

- Убедитесь, что файлы `openapi.yaml` валидны. Вы можете проверить их с помощью инструментов, таких как Swagger Editor (https://editor.swagger.io).
- Для отправки запросов через Swagger UI, требующих авторизации (например, к DeepSeek API), укажите токены (такие как `DEEPSEEK_TOKEN`) в соответствующих полях авторизации.
- Если возникают проблемы с загрузкой Swagger UI, проверьте правильность путей к файлам в `public/swagger/index.html` и доступность сервера.

### Основные эндпоинты

#### telegrambot-service
- **POST /webhook**
  - **Описание**: Обрабатывает входящие сообщения от Telegram.
  - **Параметры**: JSON от Telegram API (например, `message.text`, `message.chat.id`, `message.from.id`).
  - **Ответ**: JSON с полем `status` (например, `start_command_handled`, `summary_generated`).
- **POST /set-bot-commands**
  - **Описание**: Регистрирует команды бота в Telegram.
  - **Ответ**: JSON с полем `status` (например, `commands_set`).

#### database-service
- **POST /api/user-info**
  - **Описание**: Возвращает информацию о пользователе.
  - **Параметры**: `{ "telegram_id": <int> }`
  - **Ответ**: JSON с полями `telegram_id`, `has_subscription`, `subscription_end_date`, `todays_requests_count`, `max_requests_per_day`.
- **POST /api/subscribe**
  - **Описание**: Оформляет подписку для пользователя.
  - **Параметры**: `{ "telegram_id": <int> }`
  - **Ответ**: JSON с полем `status` и `subscription_end_date`.
- **POST /api/reset-limits**
  - **Описание**: Сбрасывает лимиты запросов пользователя.
  - **Параметры**: `{ "telegram_id": <int> }`
  - **Ответ**: JSON с полем `status`.
- **POST /api/check-limits**
  - **Описание**: Проверяет лимиты запросов.
  - **Параметры**: `{ "telegram_id": <int> }`
  - **Ответ**: JSON с полями `todays_requests_count`, `max_requests_per_day`.
- **POST /api/increment-limits**
  - **Описание**: Увеличивает счётчик запросов.
  - **Параметры**: `{ "telegram_id": <int> }`
  - **Ответ**: JSON с полем `status`.

#### deepseek-service
- **POST /api/generate-text**
  - **Описание**: Генерирует текст на основе промпта.
  - **Параметры**: `{ "prompt": <string> }`
  - **Ответ**: JSON с полем `text`.

## Как тестировать

### Unit-тесты

Проект включает unit-тесты для всех микросервисов, написанные с использованием PHPUnit. Тесты покрывают основные функции контроллеров и взаимодействие между сервисами.

1. **Убедитесь, что зависимости установлены**:
   Перейдите в директории `telegrambot-service`, `database-service`, `deepseek-service` и выполните:
   ```bash
   composer install
   ```

2. **Настройте тестовое окружение**:
   - Убедитесь, что файлы `.env.testing` настроены для каждого сервиса.
   - Для `database-service` используется SQLite в памяти (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
   - Для `telegrambot-service` и `deepseek-service` тесты используют моки для HTTP-запросов (например, Telegram API, DeepSeek API), поэтому внешние сервисы не требуются.

3. **Запустите тесты**:
   В каждой директории сервиса выполните:
   ```bash
   php artisan test
   ```
   Это запустит unit- и feature-тесты, определённые в директориях `tests/Unit` и `tests/Feature`.

4. **Проверка покрытия тестами**:
   Для генерации отчёта о покрытии тестами выполните:
   ```bash
   vendor/bin/phpunit --coverage-text
   ```
   Отчёт покажет процент покрытия кода тестами.

### Ручное тестирование

1. **Запустите микросервисы**:
   Следуйте инструкциям из раздела "Локальный запуск" для запуска `telegrambot-service` (порт 8000), `database-service` (порт 8001), `deepseek-service` (порт 8002) и LocalTunnel.

2. **Настройте вебхук Telegram**:
   - Убедитесь, что LocalTunnel запущен и предоставляет публичный URL (например, `https://piusbot.loca.lt`).
   - Установите вебхук для Telegram-бота, отправив GET-запрос:
     ```bash
     curl -X GET "https://api.telegram.org/bot<your-telegram-bot-token>/setWebhook?url=https://piusbot.loca.lt/webhook"
     ```
   - Проверьте статус вебхука:
     ```bash
     curl -X GET "https://api.telegram.org/bot<your-telegram-bot-token>/getWebhookInfo"
     ```

3. **Тестирование бота**:
   - Откройте Telegram и найдите вашего бота по его имени (например, `@YourBotName`).
   - Отправьте команды:
     - `/start` — получить приветственное сообщение и список команд.
     - `/set_movie` — указать название фильма (например, "Назад в будущее").
     - `/set_style` — выбрать стиль пересказа (например, "Тарантино" или "Бухой дед").
     - `/generate_summary` — сгенерировать пересказ фильма.
     - `/info` — проверить информацию о пользователе, подписке и лимитах.
     - `/subscribe` — протестировать оформление подписки (ожидается сообщение о недоступности).
   - Убедитесь, что бот отвечает корректно и взаимодействует с `database-service` и `deepseek-service`.

4. **Тестирование API**:
   - Используйте инструменты, такие как Postman или cURL, для отправки запросов к эндпоинтам:
     - `POST http://localhost:8001/api/user-info` с телом `{"telegram_id": 12345}`.
     - `POST http://localhost:8001/api/check-limits` с телом `{"telegram_id": 12345}`.
     - `POST http://localhost:8001/api/increment-limits` с телом `{"telegram_id": 12345}`.
     - `POST http://localhost:8002/api/generate-text` с телом `{"prompt": "Перескажи фильм 'Титаник' в стиле 'Бухой дед'"}`.
   - Проверьте, что ответы соответствуют описанным в разделе "API документация".

5. **Проверка базы данных**:
   - Подключитесь к PostgreSQL базе данных `tg_bot` через pgAdmin или другой инструмент.
   - Выполните запросы для проверки данных:
     ```sql
     SELECT * FROM users WHERE telegram_id = 12345;
     ```
   - Убедитесь, что поля `todays_requests_count`, `last_request_date` и `subscription_end_date` обновляются корректно после взаимодействия с ботом.

### Инструменты для тестирования

- **PHPUnit**: Для запуска unit- и feature-тестов.
- **Postman/cURL**: Для тестирования API-эндпоинтов.
- **pgAdmin**: Для проверки данных в базе PostgreSQL.
- **Telegram**: Для взаимодействия с ботом.

### Примечания

- Тесты используют моки для внешних API (Telegram, DeepSeek), поэтому для их запуска не требуется доступ к реальным сервисам.
- Для ручного тестирования убедитесь, что `DEEPSEEK_TOKEN` в `deepseek-service\.env` действителен, так как генерация текста зависит от DeepSeek API.
- Если возникают ошибки, проверьте логи в `storage/logs/laravel.log` и убедитесь, что все сервисы запущены и доступны по указанным портам.

## Контакты и поддержка

- **Автор**: Дмитрий Руднев
- **Telegram**: @here_my_username
- **GitHub Issues**: https://github.com/DmitryRudnev/pius_project/issues
