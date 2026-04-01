<?php

use App\Http\Controllers\Auth\AuditLogController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);

    Route::middleware('auth.token')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::prefix('internal')->middleware('internal')->group(function (): void {
    Route::middleware('auth.token')->get('tokens/inspect', [AuthController::class, 'inspect']);
    Route::get('audit/events', [AuditLogController::class, 'index']);
});
