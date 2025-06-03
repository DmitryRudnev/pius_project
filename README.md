# movie_summary_tg_bot

## Название и назначение сервиса

**movie_summary_tg_bot** — это Telegram-бот, который пересказывает фильмы в заданном стиле. movie_summary_tg_bot — это микросервисная архитектура, состоящая из трёх сервисов:

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

Документация OpenAPI/Swagger отсутствует. API микросервисов описаны ниже.

### Основные эндпоинты

#### telegrambot-service

- **POST /webhook**
  - **Описание**: Обрабатывает входящие сообщения от Telegram.
  - **Параметры**: JSON от Telegram API (например, `message.text`, `message.chat.id`, `message.from.id`).
  - **Ответ**: JSON с полем `status` (например, `start_command_handled`, `summary_generated`).

#### database-service

- **POST /api/user-info**
  - **Описание**: Возвращает информацию о пользователе.
  - **Параметры**: `{ "telegram_id": <int> }`
  - **Ответ**: JSON с полями `telegram_id`, `has_subscription`, `subscription_end_date`, `todays_requests_count`, `max_requests_per_day`.
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

## Контакты и поддержка

- **Авторы**: Руднев Дмитрий, Комаров Никита, Курочкин Арсений.
- **Telegram**: @here_is_my_nickname, @komaroffski, @deyverr.
- **GitHub Issues**: https://github.com/DmitryRudnev/pius_project/issues
