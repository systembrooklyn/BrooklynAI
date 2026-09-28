<?php

use App\Modules\Execution\Http\Controllers\InternalSchedulerTickController;
use App\Modules\Execution\Http\Middleware\InternalSchedulerMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware([InternalSchedulerMiddleware::class])
    ->prefix('api/internal')
    ->group(function () {
        Route::post('/scheduler/tick', InternalSchedulerTickController::class);
    });
