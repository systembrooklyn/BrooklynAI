<?php

use App\Modules\Execution\Http\Controllers\ExecutionController;
use App\Modules\Execution\Http\Controllers\WorkflowExecutionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    Route::post('/workflows/{id}/execute', [WorkflowExecutionController::class, 'store'])
        ->where('id', '[0-9]+');

    Route::get('/workflows/{id}/executions', [WorkflowExecutionController::class, 'index'])
        ->where('id', '[0-9]+');

    Route::get('/executions/{id}', [ExecutionController::class, 'show'])
        ->where('id', '[0-9]+');
});
