<?php

use App\Modules\Connections\Http\Controllers\ConnectionController;
use App\Modules\Connections\Http\Controllers\GoogleConnectionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('api/connections')->group(function () {
    Route::get('', [ConnectionController::class, 'index']);
    Route::post('/google/start', [GoogleConnectionController::class, 'start']);
    Route::delete('/{connection}', [ConnectionController::class, 'destroy']);
});

Route::get('/api/connections/google/callback', [GoogleConnectionController::class, 'callback']);
