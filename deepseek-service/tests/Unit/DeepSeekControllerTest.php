<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Http;

/**
 * Class DeepSeekControllerTest.
 *
 * Набор unit-тестов для проверки функциональности DeepSeekController.
 */
class DeepSeekControllerTest extends TestCase
{
    /**
     * Тестирует успешную генерацию текста при корректном запросе к DeepSeek API.
     *
     * Проверяет, что метод generate возвращает статус 200 и ожидаемый JSON-ответ
     * с текстом, полученным от API, при передаче корректного prompt.
     */
    public function test_generate_successful_response(): void
    {
        $mockResponse = [
            'choices' => [
                ['message' => ['content' => 'Пересказ фильма']],
            ],
        ];

        Http::fake([
            'https://api.deepseek.com/v1/chat/completions' => Http::response($mockResponse, 200),
        ]);

        $response = $this->postJson('/api/generate-text', [
            'prompt' => 'Перескажи фильм "Назад в будущее" в стиле Тарантино',
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'text' => 'Пересказ фильма',
                 ]);
    }

    /**
     * Тестирует обработку ошибки при отсутствии параметра prompt в запросе.
     *
     * Проверяет, что метод generate возвращает статус 400 и сообщение об ошибке,
     * если параметр prompt не передан.
     */
    public function test_generate_missing_prompt(): void
    {
        $response = $this->postJson('/api/generate-text', []);

        $response->assertStatus(400)
                 ->assertJson([
                     'error' => 'Prompt is required',
                 ]);
    }

    /**
     * Тестирует обработку ошибки при неуспешном ответе от DeepSeek API.
     *
     * Проверяет, что метод generate возвращает статус 500 и сообщение об ошибке,
     * если API вернул ошибку (например, статус 500).
     */
    public function test_generate_api_failure(): void
    {
        Http::fake([
            'https://api.deepseek.com/v1/chat/completions' => Http::response('API error', 500),
        ]);

        $response = $this->postJson('/api/generate-text', [
            'prompt' => 'Перескажи фильм "Назад в будущее" в стиле Тарантино',
        ]);

        $response->assertStatus(500)
                 ->assertJson([
                     'error' => 'DeepSeek API error',
                 ]);
    }

    /**
     * Тестирует обработку исключений при запросе к DeepSeek API.
     *
     * Проверяет, что метод generate возвращает статус 500 и JSON-структуру
     * с информацией об ошибке, если во время HTTP-запроса возникает исключение.
     */
    public function test_generate_handles_exception(): void
    {
        Http::fake([
            'https://api.deepseek.com/v1/chat/completions' => function () {
                throw new \Exception('Connection error');
            },
        ]);

        $response = $this->postJson('/api/generate-text', [
            'prompt' => 'Перескажи фильм "Назад в будущее" в стиле Тарантино',
        ]);

        $response->assertStatus(500)
                 ->assertJsonStructure([
                     'error',
                     'message',
                 ]);
    }
}
