<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\JsonResponse;

/**
 * Class DeepSeekController.
 *
 * Контроллер для обработки запросов к DeepSeek API и генерации текстовых пересказов.
 */
class DeepSeekController extends Controller
{
    /**
     * Генерирует текст на основе переданного промпта через DeepSeek API.
     *
     * @param Request $request Входящий HTTP-запрос с параметром prompt.
     * @return JsonResponse JSON-ответ с сгенерированным текстом или сообщением об ошибке.
     */
    public function generate(Request $request): JsonResponse
    {
        $prompt = $request->input('prompt');

        if (!$prompt) {
            return response()->json(['error' => 'Prompt is required'], 400);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withToken(env('DEEPSEEK_TOKEN'))
                ->post('https://api.deepseek.com/v1/chat/completions', [
                    'model'    => 'deepseek-chat',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if ($response->failed()) {
                return response()->json([
                    'error'   => 'DeepSeek API error',
                    'details' => $response->body(),
                ], 500);
            }

            $text = $response->json('choices.0.message.content');

            return response()->json(['text' => $text]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Exception occurred',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
