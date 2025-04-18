<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;



class TelegramBotController extends Controller {
    public function handle(Request $request) {
        $messageText = $request->input('message.text');
        $chatId = $request->input('message.chat.id');
        $telegramId = $request->input('message.from.id');

        if (!$messageText || !$chatId || !$telegramId) {
            return response()->json(['status' => 'ignored']);
        }

        $this->sendMessage($chatId, "Parsing request...");

        // Временное хранилище настроек пользователя
        $userKey = "user_settings_{$telegramId}";
        $settings = Cache::get($userKey, []);



        if (isset($settings['state'])) {
            if ($settings['state'] === 'awaiting_movie_input') {
                $settings['movie'] = $messageText;
                unset($settings['state']);
                Cache::put($userKey, $settings, now()->addMinutes(15));
                $this->sendMessage($chatId, "✅ Фильм установлен: {$messageText}");
                return response()->json(['status' => 'movie_input_saved']);
            }

            elseif ($settings['state'] === 'awaiting_style_input') {
                $settings['style'] = $messageText;
                unset($settings['state']);
                Cache::put($userKey, $settings, now()->addMinutes(15));
                $this->sendMessage($chatId, "✅ Стиль установлен: {$messageText}");
                return response()->json(['status' => 'style_input_saved']);
            }

            else {
                unset($settings['state']);
                return response()->json(['status' => 'invalid_state']);
            }
        }



        elseif ($messageText === '/start') {
            $startText = <<<TEXT
            👋 Привет! Я бот, который может пересказать фильм в выбранном тобой стиле.

            Доступные команды:

            🎬 /set_movie – Установить фильм для пересказа.

            🎭 /set_style – Установить стиль (режиссёр, жанр и т.д.).

            📤 /summary – Сгенерировать пересказ.

            ℹ️ /info – Посмотреть информацию о себе: подписка, лимиты и текущие настройки.

            🔄 /reset_limits – Сбросить количество запросов за сегодня (0).

            💎 /subscribe – Оформить подписку (даёт больше запросов в день).
            TEXT;

            $this->sendMessage($chatId, $startText);
            return response()->json(['status' => 'start_command_handled']);
        }



        elseif ($messageText === '/info') {
            // database-service getUserInfo
            $this->sendMessage($chatId, 'Получение данных о пользователе...');
            $infoResponse = Http::withoutVerifying()->timeout(60)->post(env('DATABASE_INFO_URL'), [
                'telegram_id' => $telegramId,
            ]);

            if ($infoResponse->failed()) {
                $this->sendMessage($chatId, 'Не удалось получить информацию.');
                return response()->json(['status' => 'user_info_fetch_failed']);
            }

            $userInfo = $infoResponse->json();
            $movie = $settings['movie'] ?? '';
            $style = $settings['style'] ?? '';

            $subscriptionStatus = $userInfo['has_subscription'] ? '✅ Активна' : '❌ Не Активна';
            $maxRequests = $userInfo['max_requests_per_day'] ?? 'Неизвестно';

            $infoText = <<<TEXT
            ℹ️ Информация о пользователе:

            🔹 telegram_id: {$telegramId}
            🔹 Подписка: {$subscriptionStatus}
            TEXT;

            if ($userInfo['has_subscription']) {
                $formattedDate = Carbon::parse($userInfo['subscription_end_date'])->format('d.m.Y');
                $infoText .= " (до {$formattedDate})\n";
            }

            $infoText .= <<<TEXT
            🔹 Лимит запросов в день: {$maxRequests}
            🔹 Запросов за сегодня: {$userInfo['todays_requests_count']}

            🎬 Фильм: {$movie}
            🎭 Стиль: {$style}
            TEXT;

            $this->sendMessage($chatId, $infoText);
            return response()->json(['status' => 'user_info_sent']);
        }



        elseif ($messageText === '/reset_limits') {
            $this->sendMessage($chatId, 'Сброс лимитов...');

            $resetResponse = Http::withoutVerifying()->timeout(60)->post(env('DATABASE_RESET_LIMITS_URL'), [
                'telegram_id' => $telegramId,
            ]);

            if ($resetResponse->failed()) {
                $this->sendMessage($chatId, 'Не удалось сбросить лимиты.');
                return response()->json(['status' => 'limits_reset_failed']);
            }

            $this->sendMessage($chatId, 'Лимиты успешно сброшены!');
            return response()->json(['status' => 'limits_reset_success']);
        }



        elseif ($messageText === '/subscribe') {
            $this->sendMessage($chatId, 'Оформление подписки...');

            $subscribeResponse = Http::withoutVerifying()->timeout(60)->post(env('DATABASE_SUBSCRIBE_URL'), [
                'telegram_id' => $telegramId,
            ]);

            if ($subscribeResponse->failed()) {
                $this->sendMessage($chatId, 'Не удалось оформить подписку. Попробуйте позже.');
                return response()->json(['status' => 'subscription_failed']);
            }

            $this->sendMessage($chatId, 'Подписка успешно оформлена на 1 месяц! Спасибо 🥳');
            return response()->json(['status' => 'subscription_success']);
        }



        elseif ($messageText === '/set_movie') {
            $this->sendMessage($chatId, "🎬 Введите название фильма:");
            $settings['state'] = 'awaiting_movie_input';
            Cache::put($userKey, $settings, now()->addMinutes(15));
            return response()->json(['status' => 'awaiting_movie_input']);
        } 



        elseif ($messageText === '/set_style') {
            $this->sendMessage($chatId, "🎨 Введите стиль:");
            $settings['state'] = 'awaiting_style_input';
            Cache::put($userKey, $settings, now()->addMinutes(15));
            return response()->json(['status' => 'awaiting_style_input']);
        } 



        elseif ($messageText === '/summary') {
            $movie = $settings['movie'] ?? '';
            $style = $settings['style'] ?? '';
            if (empty($movie) || empty($style)) {
                $this->sendMessage($chatId, "Укажи фильм и стиль с помощью /set_movie и /set_style.");
                return response()->json(['status' => 'incomplete_settings']);
            }

            // database-service checkLimit
            $this->sendMessage($chatId, 'Проверка лимитов запросов...');
            $limitResponse = Http::withoutVerifying()->timeout(60)->post(env('DATABASE_SERVICE_URL'), [
                'telegram_id' => $telegramId,
            ]);

            if ($limitResponse->failed()) {
                $this->sendMessage($chatId, 'Ошибка связи с сервисом проверки лимитов.');
                return response()->json(['status' => 'user_limits_fetch_failed']);
            }

            if (!$limitResponse->json('allowed')) {
                $this->sendMessage($chatId, 'Вы достигли лимита запросов в день!');
                return response()->json(['status' => 'limited']);
            }
            $this->sendMessage($chatId, 'Лимиты не превышены!');

            // DEEPSEEK SERVICE
            $this->sendMessage($chatId, "Генерация текста...\n🎬 Фильм: {$movie}\n🎭 Стиль: {$style}");
            $prompt = "Перескажи фильм {$movie} в стиле {$style}.";
            $generationResponse = Http::withoutVerifying()->timeout(180)->post(env('DEEPSEEK_SERVICE_URL'), [
                'prompt' => $prompt,
            ]);

            if ($generationResponse->failed()) {
                $this->sendMessage($chatId, 'Ошибка генерации текста. Попробуйте позже.');
                return response()->json(['status' => 'text_generation_failed']);
            }

            $generatedText = $generationResponse->json('text') ?? 'Ответ не получен.';
            $this->sendMessage($chatId, $generatedText);
            return response()->json(['status' => 'summary_generated']);
        } 



        else {
            $availableCommandsText = <<<TEXT
            Доступные команды:

            🎬 /set_movie – Установить фильм для пересказа.

            🎭 /set_style – Установить стиль (режиссёр, жанр и т.д.).

            📤 /summary – Сгенерировать пересказ.

            ℹ️ /info – Посмотреть информацию о себе: подписка, лимиты и текущие настройки.

            🔄 /reset_limits – Сбросить количество запросов за сегодня (0).

            💎 /subscribe – Оформить подписку (даёт больше запросов в день).
            TEXT;

            $this->sendMessage($chatId, $availableCommandsText);
            return response()->json(['status' => 'unknown_command']);
        }
    }



    private function sendMessage($chatId, $text) {
        try {
            Http::withoutVerifying()->timeout(60)->post(
                "https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage",
                ['chat_id' => $chatId, 'text' => $text]
            );
        } 
        catch (\Exception $e) {
            \Log::error("Ошибка отправки Telegram-сообщения: " . $e->getMessage());
        }
    }
}
