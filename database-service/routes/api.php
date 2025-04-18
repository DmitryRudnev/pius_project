<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DatabaseController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/check-limit', [DatabaseController::class, 'checkLimit']);
Route::post('/user-info', [DatabaseController::class, 'getUserInfo']);
Route::post('/reset-limits', [DatabaseController::class, 'resetLimits']);
Route::post('/subscribe', [DatabaseController::class, 'subscribe']);
