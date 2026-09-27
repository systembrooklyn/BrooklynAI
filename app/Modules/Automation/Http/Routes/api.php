<?php

use App\Modules\Automation\Http\Controllers\WorkflowController;
use App\Modules\Automation\Http\Controllers\WorkflowStepController;
use App\Modules\Automation\Http\Controllers\WorkflowTriggerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('api')->group(function () {
    // Workflow CRUD (Batch 6.2)
    Route::get('/workflows', [WorkflowController::class, 'index']);
    Route::post('/workflows', [WorkflowController::class, 'store']);
    Route::get('/workflows/{id}', [WorkflowController::class, 'show']);
    Route::put('/workflows/{id}', [WorkflowController::class, 'update']);
    Route::delete('/workflows/{id}', [WorkflowController::class, 'destroy']);
    Route::post('/workflows/{id}/restore', [WorkflowController::class, 'restore']);
    Route::post('/workflows/{id}/activate', [WorkflowController::class, 'activate']);
    Route::post('/workflows/{id}/pause', [WorkflowController::class, 'pause']);

    // Workflow trigger (Batch 6.3)
    Route::put('/workflows/{id}/trigger', [WorkflowTriggerController::class, 'upsert']);
    Route::delete('/workflows/{id}/trigger', [WorkflowTriggerController::class, 'destroy']);

    // Workflow steps (Batch 6.3)
    Route::post('/workflows/{id}/steps', [WorkflowStepController::class, 'store']);
    Route::put('/workflows/{id}/steps/{position}', [WorkflowStepController::class, 'update'])
        ->where('position', '[0-9]+');
    Route::delete('/workflows/{id}/steps/{position}', [WorkflowStepController::class, 'destroy'])
        ->where('position', '[0-9]+');
});
