<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * Class TelegramBotControllerTest.
 *
 * Набор unit-тестов для проверки функциональности TelegramBotController.
 */
class TelegramBotControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тестирует обработку команды /start.
     *
     * Проверяет, что метод handle отправляет приветственное сообщение
     * и возвращает JSON с подтверждением обработки команды.
     */
    public function test_start_command_sends_welcome_message(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/start',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'start_command_handled']);
    }

    /**
     * Тестирует обработку команды /set_movie с установкой состояния.
     *
     * Проверяет, что метод handle сохраняет состояние awaiting_movie_input в кэше
     * и возвращает JSON с подтверждением ожидания ввода фильма.
     */
    public function test_set_movie_command_sets_awaiting_state(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        Cache::shouldReceive('get')->twice()->with('user_settings_456', [])->andReturn([]);
        Cache::shouldReceive('put')->once()->with('user_settings_456', ['state' => 'awaiting_movie_input'], \Mockery::any());

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/set_movie',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'awaiting_movie_input']);
    }

    /**
     * Тестирует обработку ввода фильма после команды /set_movie.
     *
     * Проверяет, что метод handle сохраняет название фильма в кэше
     * и возвращает JSON с подтверждением сохранения.
     */
    public function test_movie_input_saves_to_cache(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        Cache::shouldReceive('get')->twice()->with('user_settings_456', [])->andReturn(['state' => 'awaiting_movie_input']);
        Cache::shouldReceive('put')->once()->with('user_settings_456', ['movie' => 'Назад в будущее', 'state' => null], \Mockery::any());

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => 'Назад в будущее',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'movie_input_saved']);
    }

    /**
     * Тестирует обработку команды /set_style с установкой состояния.
     *
     * Проверяет, что метод handle сохраняет состояние awaiting_style_input в кэше
     * и возвращает JSON с подтверждением ожидания ввода стиля.
     */
    public function test_set_style_command_sets_awaiting_state(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        Cache::shouldReceive('get')->twice()->with('user_settings_456', [])->andReturn([]);
        Cache::shouldReceive('put')->once()->with('user_settings_456', ['state' => 'awaiting_style_input'], \Mockery::any());

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/set_style',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'awaiting_style_input']);
    }

    /**
     * Тестирует обработку ввода стиля после команды /set_style.
     *
     * Проверяет, что метод handle сохраняет стиль в кэше
     * и возвращает JSON с подтверждением сохранения.
     */
    public function test_style_input_saves_to_cache(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        Cache::shouldReceive('get')->twice()->with('user_settings_456', [])->andReturn(['state' => 'awaiting_style_input']);
        Cache::shouldReceive('put')->once()->with('user_settings_456', ['style' => 'Тарантино', 'state' => null], \Mockery::any());

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => 'Тарантино',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'style_input_saved']);
    }

    /**
     * Тестирует обработку команды /info.
     *
     * Проверяет, что метод handle запрашивает информацию о пользователе
     * и возвращает JSON с подтверждением отправки информации.
     */
    public function test_info_command_returns_user_info(): void
    {
        Http::fake([
            'https://api.telegram.org/*'  => Http::response([], 200),
            env('DATABASE_USER_INFO_URL') => Http::response([
                'success' => true,
                'data'    => [
                    'telegram_id'           => 456,
                    'has_subscription'      => true,
                    'subscription_end_date' => Carbon::today()->addMonth()->toDateTimeString(),
                    'todays_requests_count' => 5,
                    'max_requests_per_day'  => 50,
                ],
            ], 200),
        ]);

        Cache::shouldReceive('get')->once()->with('user_settings_456', [])->andReturn(['movie' => 'Назад в будущее', 'style' => 'Бухой дед']);

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/info',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'user_info_sent']);
    }

    /**
     * Тестирует обработку команды /subscribe.
     *
     * Проверяет, что метод handle возвращает сообщение о недоступности сервиса
     * и JSON с подтверждением статуса subscription_unavailable.
     */
    public function test_subscribe_command_returns_unavailable(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/subscribe',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'subscription_unavailable']);
    }

    /**
     * Тестирует обработку команды /generate_summary с корректными настройками.
     *
     * Проверяет, что метод handle проверяет лимиты, генерирует пересказ
     * и возвращает JSON с подтверждением успешной генерации.
     */
    public function test_generate_summary_with_valid_settings(): void
    {
        Http::fake([
            'https://api.telegram.org/*'     => Http::response([], 200),
            env('DATABASE_CHECK_LIMITS_URL') => Http::response([
                'success' => true,
                'data'    => ['todays_requests_count' => 1, 'max_requests_per_day' => 10],
            ], 200),
            env('DEEPSEEK_SERVICE_URL') => Http::response([
                'text' => 'Пересказ фильма',
            ], 200),
            env('DATABASE_INCREMENT_LIMITS_URL') => Http::response([
                'success' => true,
                'data'    => ['status' => 'limits_incremented'],
            ], 200),
        ]);

        Cache::shouldReceive('get')->once()->with('user_settings_456', [])->andReturn(['movie' => 'Назад в будущее', 'style' => 'Бухой дед']);

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/generate_summary',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'summary_generated']);
    }

    /**
     * Тестирует обработку команды /generate_summary без указанного фильма.
     *
     * Проверяет, что метод handle возвращает ошибку, если фильм не указан.
     */
    public function test_generate_summary_without_movie(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response([], 200),
        ]);

        Cache::shouldReceive('get')->once()->with('user_settings_456', [])->andReturn([]);

        $response = $this->postJson('/webhook', [
            'message' => [
                'text' => '/generate_summary',
                'chat' => ['id' => 123],
                'from' => ['id' => 456],
            ],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => false, 'status' => 'incomplete_settings']);
    }

    /**
     * Тестирует регистрацию команд бота.
     *
     * Проверяет, что метод setBotCommands отправляет запрос на регистрацию команд
     * и возвращает JSON с подтверждением успешной регистрации.
     */
    public function test_set_bot_commands_success(): void
    {
        Http::fake([
            'https://api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $response = $this->postJson('/set-bot-commands');

        $response->assertStatus(200)
                 ->assertJson(['success' => true, 'status' => 'commands_set']);
    }

    /**
     * Тестирует обработку ошибки при отсутствии обязательных полей в запросе.
     *
     * Проверяет, что метод handle возвращает статус ignored, если отсутствуют
     * message.text, message.chat.id или message.from.id.
     */
    public function test_missing_required_fields_returns_ignored(): void
    {
        $response = $this->postJson('/webhook', [
            'message' => [],
        ]);

        $response->assertStatus(200)
                 ->assertJson(['success' => false, 'status' => 'ignored']);
    }
}
