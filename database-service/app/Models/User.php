<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class User extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'telegram_id';
    public $incrementing = false;

    protected $fillable = [
        'telegram_id',
        'subscription_end_date',
        'todays_requests_count',
        'last_request_date',
    ];

    protected $casts = [
        'subscription_end_date' => 'date',
        'last_request_date' => 'date',
    ];

    public const MAX_REQUESTS_FREE = 10;
    public const MAX_REQUESTS_SUBSCRIBED = 50;

    /**
     * Проверяет, активна ли подписка пользователя.
     *
     * @return bool
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_end_date->gt(Carbon::today());
    }

    /**
     * Возвращает максимальное количество запросов в день.
     *
     * @return int
     */
    public function getMaxRequestsPerDay(): int
    {
        return $this->hasActiveSubscription() ? self::MAX_REQUESTS_SUBSCRIBED : self::MAX_REQUESTS_FREE;
    }
}