<?php

use App\Modules\Identity\Http\Controllers\LoginController;
use App\Modules\Identity\Http\Controllers\RequestPasswordResetController;
use App\Modules\Identity\Http\Controllers\ResetPasswordController;
use Illuminate\Support\Facades\Route;

Route::post('/api/login', LoginController::class);

Route::post('/api/password/forgot', RequestPasswordResetController::class);
Route::post('/api/password/reset', ResetPasswordController::class);
