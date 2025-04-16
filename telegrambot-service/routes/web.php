<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TelegramBotController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhook', [TelegramBotController::class, 'handle']);
