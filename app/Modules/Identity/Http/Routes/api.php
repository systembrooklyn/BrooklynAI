<?php

use App\Modules\Identity\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('/api/login', LoginController::class);
