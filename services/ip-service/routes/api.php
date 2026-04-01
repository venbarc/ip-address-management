<?php

use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\IpAddressController;
use Illuminate\Support\Facades\Route;

Route::prefix('internal')->middleware('internal')->group(function (): void {
    Route::get('ip-addresses', [IpAddressController::class, 'index']);
    Route::post('ip-addresses', [IpAddressController::class, 'store']);
    Route::patch('ip-addresses/{record}', [IpAddressController::class, 'update']);
    Route::delete('ip-addresses/{record}', [IpAddressController::class, 'destroy']);

    Route::get('audit/events', [AuditLogController::class, 'index']);
});
