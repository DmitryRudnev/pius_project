<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

/**
 * Class TelegramBotController
 *
 * Контроллер для обработки вебхуков Telegram и управления командами бота.
 */
class TelegramBotController extends Controller
{
    /**
     * Время хранения настроек пользователя в кэше (в минутах).
     */
    private const CACHE_TTL = 60 * 24; // 24 часа

    /**
     * Валидирует входящий запрос вебхука Telegram.
     *
     * @param Request $request
     * @return array{chat_id: int, user_id: int, text: string}
     * @throws ValidationException
     */
    private function validateWebhookRequest(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'message.text' => 'required|string',
            'message.chat.id' => 'required|integer',
            'message.from.id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator, response()->json([
                'success' => false,
                'status' => 'ignored',
                'error' => 'Invalid webhook payload',
            ], 200));
        }

        return [
            'chat_id' => $request->input('message.chat.id'),
            'user_id' => $request->input('message.from.id'),
            'text' => $request->input('message.text'),
        ];
    }

    /**
     * Отправляет сообщение в Telegram.
     *
     * @param int $chatId
     * @param string $message
     * @return void
     */
    private function sendMessage(int $chatId, string $message): void
    {
        /*Http::post('https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
        ]);*/
        Http::withoutVerifying()
                ->post(
                    'https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/sendMessage',
                    ['chat_id' => $chatId, 'text' => $message]
                );
    }

    /**
     * Получает или обновляет настройки пользователя в кэше.
     *
     * @param int $userId
     * @param array $updates
     * @return array
     */
    private function manageUserSettings(int $userId, array $updates = []): array
    {
        $key = "user_settings_{$userId}";
        $settings = Cache::get($key, []);

        if ($updates) {
            $settings = array_merge($settings, $updates);
            Cache::put($key, $settings, self::CACHE_TTL);
        }

        return $settings;
    }

    /**
     * Обрабатывает входящий вебхук Telegram.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handle(Request $request): JsonResponse
    {
        try {
            $data = $this->validateWebhookRequest($request);
            $chatId = $data['chat_id'];
            $userId = $data['user_id'];
            $messageText = $data['text'];

            $settings = $this->manageUserSettings($userId);

            if (isset($settings['state'])) {
                if ($settings['state'] === 'awaiting_movie_input') {
                    $this->manageUserSettings($userId, ['movie' => $messageText, 'state' => null]);
                    $this->sendMessage($chatId, '✅ Фильм сохранён: ' . $messageText);
                    return response()->json(['success' => true, 'status' => 'movie_input_saved']);
                }

                if ($settings['state'] === 'awaiting_style_input') {
                    $this->manageUserSettings($userId, ['style' => $messageText, 'state' => null]);
                    $this->sendMessage($chatId, '✅ Стиль сохранён: ' . $messageText);
                    return response()->json(['success' => true, 'status' => 'style_input_saved']);
                }
            }

            switch ($messageText) {
                case '/start':
                    $startText = <<<TEXT
                    👋 Привет! Я бот, который может пересказать фильм от лица твоего бухого деда.

                    Доступные команды:

                    🎬 /set_movie – Выбрать фильм для пересказа.

                    🎭 /set_style – Выбрать кастомный стиль (режиссёр, жанр и т.д.).
                    Стиль по умолчанию - 'Бухой дед'.

                    📤 /generate_summary – Сгенерировать пересказ фильма.

                    ℹ️ /info – Посмотреть информацию о себе: подписка, лимиты и текущие настройки.

                    💎 /subscribe – Оформить подписку (даёт больше запросов в день).
                    TEXT;

                    $this->sendMessage($chatId, $startText);
                    return response()->json(['success' => true, 'status' => 'start_command_handled']);

                case '/set_movie':
                    $this->manageUserSettings($userId, ['state' => 'awaiting_movie_input']);
                    $this->sendMessage($chatId, '🎬 Введите название фильма:');
                    return response()->json(['success' => true, 'status' => 'awaiting_movie_input']);

                case '/set_style':
                    $this->manageUserSettings($userId, ['state' => 'awaiting_style_input']);
                    $this->sendMessage($chatId, "🎨 Введите стиль:\n(Для того, чтобы выбрать стиль по умолчанию, введите 'Бухой дед')");
                    return response()->json(['success' => true, 'status' => 'awaiting_style_input']);

                case '/info':
                    $response = Http::post(env('DATABASE_USER_INFO_URL'), ['telegram_id' => $userId]);
                    $userInfo = $response->json();

                    if (!$response->successful() || !$userInfo['success']) {
                        $this->sendMessage($chatId, 'Ошибка получения информации.');
                        return response()->json(['success' => false, 'status' => 'user_info_failed']);
                    }

                    $data = $userInfo['data'];
                    $movie = $settings['movie'] ?? 'Не указан';
                    $style = $settings['style'] ?? 'Бухой дед';
                    if ($data['has_subscription']) {
                        $formattedDate = Carbon::parse($data['subscription_end_date'])->format('d.m.Y');
                        $subscriptionInfo = "✅ Активна(до {$formattedDate})";
                    }
                    else {
                        $subscriptionInfo = "❌ Не активна\n";
                    }

                    $infoText = <<<TEXT
                    ℹ️ Информация о пользователе:

                    🔹 telegram_id: {$data['telegram_id']}
                    🔹 Подписка: {$subscriptionInfo}
                    🔹 Лимит запросов в день: {$data['max_requests_per_day']}
                    🔹 Запросов за сегодня: {$data['todays_requests_count']}

                    🎬 Фильм: {$movie}
                    🎭 Стиль: {$style}
                    TEXT;

                    $this->sendMessage($chatId, $infoText);
                    return response()->json(['success' => true, 'status' => 'user_info_sent']);

                case '/subscribe':
                    $this->sendMessage($chatId, '🚧 Извините, данный сервис пока что не доступен');
                    return response()->json(['success' => true, 'status' => 'subscription_unavailable']);

                case '/generate_summary':
                    $movie = $settings['movie'] ?? '';
                    $style = $settings['style'] ?? 'Бухой дед';
                    if (empty($movie)) {
                        $this->sendMessage($chatId, '⚠️ Сначала укажите фильм с помощью /set_movie.');
                        return response()->json(['success' => false, 'status' => 'incomplete_settings']);
                    }

                    $this->sendMessage($chatId, '🔄 Проверка лимитов запросов...');
                    $response = Http::post(env('DATABASE_CHECK_LIMITS_URL'), ['telegram_id' => $userId]);
                    $limits = $response->json();

                    if (!$response->successful() || !$limits['success']) {
                        $this->sendMessage($chatId, '❌ Ошибка проверки лимитов.');
                        return response()->json(['success' => false, 'status' => 'limits_check_failed']);
                    }

                    $requestsCount = $limits['data']['todays_requests_count'];
                    $maxRequests = $limits['data']['max_requests_per_day'];
                    if ($requestsCount >= $maxRequests) {
                        $this->sendMessage($chatId, "🚫 Лимит запросов исчерпан: {$requestsCount}/{$maxRequests}");
                        return response()->json(['success' => false, 'status' => 'limit_exceeded']);
                    }
                    $this->sendMessage($chatId, "✅ Лимиты не превышены! ({$requestsCount}/{$maxRequests})");
                    
                    $this->sendMessage(
                        $chatId,
                        "🛠 Генерация пересказа...\n🎬 Фильм: {$movie}\n🎭 Стиль: {$style}\n\n"
                        . 'P.S. Обычно это занимает примерно 30 сек.'
                    );

                    if ($style == 'Бухой дед') {
                        $prompt = <<<TEXT
                        Представь, что ты русский дед, который воевал во второй мировой войне.
                        Ты любишь СССР, Сталина и водку.
                        Ты ненавидишь Америку, Европу, Обаму, Байдена и фашистов.
                        Ты часто упоминаешь, что раньше(в СССР) было лучше.
                        Также ты часто говоришь, что Америка и Европа - загнивающие страны,
                        там гораздо хуже, чем в матушке-России.
                        Используй устареший диалект и просторечия("ихний", "евонный" и т. д.).
                        В соответствии с этим образом перескажи фильм '{$movie}'.
                        TEXT;
                    } else {
                        $prompt = "Перескажи фильм '{$movie}' в стиле '{$style}'.";
                    }

                    $response = Http::timeout(60)->post(env('DEEPSEEK_SERVICE_URL'), ['prompt' => $prompt]);
                    $summary = $response->json()['text'];

                    if (!$response->successful() || empty($summary)) {
                        $this->sendMessage($chatId, '❌ Ошибка генерации пересказа.');
                        return response()->json(['success' => false, 'status' => 'summary_generation_failed']);
                    }

                    $this->sendMessage($chatId, $summary);
                    $updateResponse = Http::post(env('DATABASE_INCREMENT_LIMITS_URL'), ['telegram_id' => $userId]);
                    if ($updateResponse->failed()) {
                        return response()->json(['status' => 'user_request_update_failed']);
                    }
                    return response()->json(['success' => true, 'status' => 'summary_generated']);

                default:
                    $availableCommandsText = <<<TEXT
                    Доступные команды:

                    🎬 /set_movie – Выбрать фильм для пересказа.

                    🎭 /set_style – Выбрать кастомный стиль для пересказа

                    📤 /generate_summary – Сгенерировать пересказ фильма.

                    ℹ️ /info – Посмотреть информацию о себе: подписка, лимиты и текущие настройки.

                    💎 /subscribe – Оформить подписку (даёт больше запросов в день).
                    TEXT;

                    $this->sendMessage($chatId, $availableCommandsText);
                    return response()->json(['success' => true, 'status' => 'unknown_command']);
            }
        } catch (ValidationException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Регистрирует команды бота в Telegram.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function setBotCommands(Request $request): JsonResponse
    {
        $commands = [
            ['command' => 'start', 'description' => 'Запустить бота'],
            ['command' => 'set_movie', 'description' => 'Указать фильм'],
            ['command' => 'set_style', 'description' => 'Указать кастомный стиль пересказа'],
            ['command' => 'info', 'description' => 'Показать информацию о подписке, лимитах и настройках'],
            ['command' => 'subscribe', 'description' => 'Оформить подписку'],
            ['command' => 'generate_summary', 'description' => 'Сгенерировать пересказ'],
        ];

        $response = Http::post('https://api.telegram.org/bot' . env('TELEGRAM_BOT_TOKEN') . '/setMyCommands', [
            'commands' => $commands,
        ]);

        if ($response->successful() && $response->json()['ok']) {
            return response()->json(['success' => true, 'status' => 'commands_set']);
        }

        return response()->json(['success' => false, 'status' => 'commands_set_failed'], 500);
    }
}
