<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Execution\Application\Actions\ShowExecutionAction;
use App\Modules\Execution\Application\DTOs\ShowExecutionInput;
use App\Modules\Execution\Core\Exceptions\ExecutionNotFoundException;
use App\Modules\Execution\Http\Requests\ShowExecutionRequest;
use Illuminate\Http\JsonResponse;

class ExecutionController extends Controller
{
    public function show(
        ShowExecutionRequest $request,
        int $id,
        ShowExecutionAction $action,
    ): JsonResponse {
        try {
            $data = $action->execute(new ShowExecutionInput(
                userId: (int) $request->user()->id,
                executionId: $id,
            ));
        } catch (ExecutionNotFoundException) {
            return response()->json([
                'message' => __('execution::messages.not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('execution::messages.show_success'),
            'data' => $data->toArray(),
        ]);
    }
}
