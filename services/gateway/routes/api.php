<?php

use App\Http\Controllers\AuditDashboardController;
use App\Http\Controllers\AuthProxyController;
use App\Http\Controllers\IpAddressController;
use Illuminate\Support\Facades\Route;

Route::middleware('correlation')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('login', [AuthProxyController::class, 'login']);
        Route::post('refresh', [AuthProxyController::class, 'refresh']);

        Route::middleware('gateway.auth')->group(function (): void {
            Route::get('me', [AuthProxyController::class, 'me']);
            Route::post('logout', [AuthProxyController::class, 'logout']);
        });
    });

    Route::middleware('gateway.auth')->group(function (): void {
        Route::get('ip-addresses', [IpAddressController::class, 'index']);
        Route::post('ip-addresses', [IpAddressController::class, 'store']);
        Route::patch('ip-addresses/{id}', [IpAddressController::class, 'update']);
        Route::delete('ip-addresses/{id}', [IpAddressController::class, 'destroy']);

        Route::middleware('super-admin')->get('audit/dashboard', [AuditDashboardController::class, 'index']);
    });
});
