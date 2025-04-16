<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class TelegramBotController extends Controller {
    public function handle(Request $request) {
        $messageText = $request->input('message.text');
        $chatId = $request->input('message.chat.id');
        $telegramId = $request->input('message.from.id');

        if (!$messageText || !$chatId || !$telegramId) {
            return response()->json(['status' => 'ignored']);
        }

        // временное хранилище настроек пользователя
        $userKey = "user_settings_{$telegramId}";
        $settings = Cache::get($userKey, [
            'movie' => 'Во все тяжкие',
            'style' => 'Тарантино',
        ]);

        if (str_starts_with($messageText, '/start')) {
            $this->sendMessage($chatId, "Привет! Я бот, который расскажет фильм в определённом стиле.\n\n
                Используй:\n/set_movie Название фильма\n/set_style Название стиля\n/send_request – чтобы получить результат.");
        }

        elseif (str_starts_with($messageText, '/set_movie')) {
            $movie = trim(str_replace('/set_movie', '', $messageText));
            if ($movie === '') {
                $this->sendMessage($chatId, "Пожалуйста, укажи название фильма после /set_movie");
            } 
            else {
                $settings['movie'] = $movie;
                Cache::put($userKey, $settings, now()->addHours(1));
                $this->sendMessage($chatId, "Фильм установлен: {$movie}");
            }
        }

        elseif (str_starts_with($messageText, '/set_style')) {
            $style = trim(str_replace('/set_style', '', $messageText));
            if ($style === '') {
                $this->sendMessage($chatId, "Пожалуйста, укажи стиль после /set_style");
            } 
            else {
                $settings['style'] = $style;
                Cache::put($userKey, $settings, now()->addHours(1));
                $this->sendMessage($chatId, "Стиль установлен: {$style}");
            }
        }

        elseif (str_starts_with($messageText, '/send_request')) {
            // database-service
            $limitResponse = Http::withoutVerifying()->post(env('DATABASE_SERVICE_URL'), [
                'telegram_id' => $telegramId,
            ]);

            if ($limitResponse->failed()) {
                $this->sendMessage($chatId, 'Ошибка связи с сервисом проверки лимитов.');
                return response()->json(['status' => 'error']);
            }

            if (!$limitResponse->json('allowed')) {
                $this->sendMessage($chatId, 'Вы достигли лимита запросов в день!');
                return response()->json(['status' => 'limited']);
            }

            $prompt = "Перескажи фильм {$settings['movie']} в стиле {$settings['style']}.";

            // deepseek-service
            $generationResponse = Http::withoutVerifying()->post(env('DEEPSEEK_SERVICE_URL'), [
                'prompt' => $prompt,
            ]);

            if ($generationResponse->failed()) {
                $this->sendMessage($chatId, 'Ошибка генерации текста. Попробуйте позже.');
                return response()->json(['status' => 'error']);
            }

            $generatedText = $generationResponse->json('text') ?? 'Ответ не получен.';
            $this->sendMessage($chatId, $generatedText);
        }

        else {
            $this->sendMessage($chatId, "Неизвестная команда. Используй /start, /set_movie, /set_style, /send_request.");
        }

        return response()->json(['status' => 'ok']);
    }



    private function sendMessage($chatId, $text) {
        Http::withoutVerifying()->post("https://api.telegram.org/bot" . env('TELEGRAM_BOT_TOKEN') . "/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
    }
}
