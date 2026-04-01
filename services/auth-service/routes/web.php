<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => config('app.name'),
        'status' => 'ok',
    ]);
});
