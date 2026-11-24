<?php

use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\UserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckinController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Người học
    Route::get('/checkins/today', [CheckinController::class, 'today']);
    Route::put('/checkins/today', [CheckinController::class, 'saveToday']);
    Route::get('/checkins/stats', [CheckinController::class, 'stats']);
    Route::get('/checkins', [CheckinController::class, 'index']);

    // Quản trị
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/export', [DashboardController::class, 'export']);
        Route::get('/users/{user}/checkins', [UserController::class, 'checkins']);
        Route::apiResource('users', UserController::class)->except(['destroy']);
    });
});
