<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserLimitController extends Controller
{
    public function checkLimit(Request $request)
    {
        $telegramId = $request->input('telegram_id');
        if (!$telegramId) {
            return response()->json(['error' => 'telegram_id is required'], 400);
        }

        $user = User::firstOrCreate(
            ['telegram_id' => $telegramId],
            [
                'subscription_end_date' => '2000-01-01',
                'todays_requests_count' => 0,
                'last_request_date' => '2000-01-01',
            ]
        );

        $today = Carbon::today();

        if ($user->last_request_date->lt($today)) {
            $user->last_request_date = $today;
            $user->todays_requests_count = 1;
            $user->save();
            return response()->json(['allowed' => true]);
        }

        if ($user->todays_requests_count < 10) {
            $user->todays_requests_count += 1;
            $user->save();
            return response()->json(['allowed' => true]);
        }

        if ($user->todays_requests_count < 50 && $today->lt(Carbon::parse($user->subscription_end_date))) {
            $user->todays_requests_count += 1;
            $user->save();
            return response()->json(['allowed' => true]);
        }

        return response()->json(['allowed' => false]);
    }
}