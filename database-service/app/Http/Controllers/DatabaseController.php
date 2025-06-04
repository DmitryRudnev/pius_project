<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * Class DatabaseController.
 *
 * Контроллер для управления данными пользователей и их лимитами.
 */
class DatabaseController extends Controller
{
    /**
     * Проверяет и возвращает telegram_id из запроса.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateTelegramId(Request $request): int
    {
        $validator = Validator::make($request->all(), [
            'telegram_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            throw new \Illuminate\Validation\ValidationException($validator, response()->json([
                'success' => false,
                'error'   => 'telegram_id is required and must be an integer',
            ], 400));
        }

        return $request->input('telegram_id');
    }

    /**
     * Получает или создаёт пользователя по telegram_id.
     */
    private function getOrCreateUser(int $telegramId): User
    {
        return User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'subscription_end_date' => Carbon::parse('2000-01-01'),
                'todays_requests_count' => 0,
                'last_request_date'     => Carbon::parse('2000-01-01'),
            ]
        );
    }

    /**
     * Возвращает информацию о пользователе.
     */
    public function userInfo(Request $request): JsonResponse
    {
        $telegramId = $this->validateTelegramId($request);
        $user       = $this->getOrCreateUser($telegramId);

        $today = Carbon::today();
        if ($user->last_request_date->lt($today)) {
            $user->todays_requests_count = 0;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'telegram_id'           => $user->telegram_id,
                'has_subscription'      => $user->hasActiveSubscription(),
                'subscription_end_date' => $user->subscription_end_date,
                'todays_requests_count' => $user->todays_requests_count,
                'max_requests_per_day'  => $user->getMaxRequestsPerDay(),
            ],
        ]);
    }

    /**
     * Оформляет подписку для пользователя.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $telegramId = $this->validateTelegramId($request);
        $user       = $this->getOrCreateUser($telegramId);

        $user->subscription_end_date = Carbon::today()->addMonth();
        $user->save();

        return response()->json([
            'success' => true,
            'data'    => [
                'status'                => 'subscribed',
                'subscription_end_date' => $user->subscription_end_date,
            ],
        ]);
    }

    /**
     * Сбрасывает лимиты запросов пользователя.
     */
    public function resetLimits(Request $request): JsonResponse
    {
        $telegramId = $this->validateTelegramId($request);
        $user       = $this->getOrCreateUser($telegramId);

        $user->todays_requests_count = 0;
        $user->save();

        return response()->json([
            'success' => true,
            'data'    => ['status' => 'limits_reset'],
        ]);
    }

    /**
     * Проверяет лимиты запросов пользователя.
     */
    public function checkLimits(Request $request): JsonResponse
    {
        $telegramId = $this->validateTelegramId($request);
        $user       = $this->getOrCreateUser($telegramId);

        $today = Carbon::today();
        if ($user->last_request_date->lt($today)) {
            $user->todays_requests_count = 0;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'todays_requests_count' => $user->todays_requests_count,
                'max_requests_per_day'  => $user->getMaxRequestsPerDay(),
            ],
        ]);
    }

    /**
     * Увеличивает счётчик запросов пользователя.
     */
    public function incrementLimits(Request $request): JsonResponse
    {
        $telegramId = $this->validateTelegramId($request);
        $user       = $this->getOrCreateUser($telegramId);

        $user->todays_requests_count += 1;
        $user->last_request_date = Carbon::today();
        $user->save();

        return response()->json([
            'success' => true,
            'data'    => ['status' => 'limits_incremented'],
        ]);
    }
}
