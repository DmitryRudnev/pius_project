<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

/**
 * Class DatabaseControllerTest
 *
 * Набор unit-тестов для проверки функциональности DatabaseController.
 */
class DatabaseControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Тестирует получение информации о пользователе по telegram_id.
     *
     * Проверяет, что метод userInfo создаёт нового пользователя, если он не существует,
     * и возвращает корректный JSON с информацией о пользователе, подписке и лимитах.
     *
     * @return void
     */
    public function test_user_info_creates_and_returns_user_data(): void
    {
        $telegramId = 12345;

        $response = $this->postJson('/api/user-info', [
            'telegram_id' => $telegramId,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'telegram_id' => $telegramId,
                     'has_subscription' => false,
                     'todays_requests_count' => 0,
                     'max_requests_per_day' => 10,
                 ]);

        $this->assertDatabaseHas('users', [
            'telegram_id' => $telegramId,
            'todays_requests_count' => 0,
        ]);
    }

    /**
     * Тестирует оформление подписки для пользователя.
     *
     * Проверяет, что метод subscribe обновляет subscription_end_date
     * и возвращает JSON с подтверждением подписки.
     *
     * @return void
     */
    public function test_subscribe_updates_subscription_date(): void
    {
        $telegramId = 12345;

        $response = $this->postJson('/api/subscribe', [
            'telegram_id' => $telegramId,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'subscribed',
                 ])
                 ->assertJsonStructure([
                     'status',
                     'subscription_end_date',
                 ]);

        $this->assertDatabaseHas('users', [
            'telegram_id' => $telegramId,
            'subscription_end_date' => Carbon::today()->addMonth()->toDateTimeString(),
        ]);
    }

    /**
     * Тестирует сброс лимитов запросов пользователя.
     *
     * Проверяет, что метод resetLimits сбрасывает todays_requests_count
     * и возвращает JSON с подтверждением сброса.
     *
     * @return void
     */
    public function test_reset_limits_resets_request_count(): void
    {
        $telegramId = 12345;
        User::create([
            'telegram_id' => $telegramId,
            'todays_requests_count' => 5,
            'last_request_date' => Carbon::today(),
            'subscription_end_date' => Carbon::parse('2000-01-01'),
        ]);

        $response = $this->postJson('/api/reset-limits', [
            'telegram_id' => $telegramId,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'limits reseted',
                 ]);

        $this->assertDatabaseHas('users', [
            'telegram_id' => $telegramId,
            'todays_requests_count' => 0,
        ]);
    }

    /**
     * Тестирует проверку лимитов запросов пользователя.
     *
     * Проверяет, что метод checkLimits возвращает текущий счётчик запросов
     * и максимальное количество запросов в зависимости от подписки.
     *
     * @return void
     */
    public function test_check_limits_returns_correct_limits(): void
    {
        $telegramId = 12345;
        User::create([
            'telegram_id' => $telegramId,
            'todays_requests_count' => 3,
            'last_request_date' => Carbon::today(),
            'subscription_end_date' => Carbon::tomorrow(),
        ]);

        $response = $this->postJson('/api/check-limits', [
            'telegram_id' => $telegramId,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'todays_requests_count' => 3,
                     'max_requests_per_day' => 50,
                 ]);
    }

    /**
     * Тестирует инкремент лимитов запросов пользователя.
     *
     * Проверяет, что метод incrementLimits увеличивает todays_requests_count
     * и обновляет last_request_date, возвращая JSON с подтверждением.
     *
     * @return void
     */
    public function test_increment_limits_increases_request_count(): void
    {
        $telegramId = 12345;
        User::create([
            'telegram_id' => $telegramId,
            'todays_requests_count' => 2,
            'last_request_date' => Carbon::yesterday(),
            'subscription_end_date' => Carbon::parse('2000-01-01'),
        ]);

        $response = $this->postJson('/api/increment-limits', [
            'telegram_id' => $telegramId,
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'limits incremented',
                 ]);

        $this->assertDatabaseHas('users', [
            'telegram_id' => $telegramId,
            'todays_requests_count' => 3,
            'last_request_date' => Carbon::today()->toDateTimeString(),
        ]);
    }

    /**
     * Тестирует обработку ошибки при отсутствии telegram_id.
     *
     * Проверяет, что методы возвращают статус 400 и сообщение об ошибке,
     * если telegram_id не передан.
     *
     * @return void
     */
    public function test_missing_telegram_id_returns_error(): void
    {
        $endpoints = [
            '/api/user-info',
            '/api/subscribe',
            '/api/reset-limits',
            '/api/check-limits',
            '/api/increment-limits',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->postJson($endpoint, []);
            $response->assertStatus(400)
                     ->assertJson([
                         'error' => 'telegram_id is required',
                     ]);
        }
    }
}