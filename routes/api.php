<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\EmailController as ApiEmailController;

// Public routes (no auth required)
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Google Auth Routes (some public, some protected)
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/redirect-gmail', [GoogleAuthController::class, 'redirectGmail']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [GoogleAuthController::class, 'logout']);
    Route::post('/account/deactivate', [GoogleAuthController::class, 'deactivateAccount']);
    Route::post('/email/send', [ApiEmailController::class, 'sendTestEmail']);
});

