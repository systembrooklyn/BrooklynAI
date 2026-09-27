<?php

namespace App\Modules\Execution\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Execution\Application\Actions\ListExecutionsAction;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\DTOs\ListExecutionsInput;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Core\Exceptions\ExecutionAlreadyRunningException;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use App\Modules\Execution\Http\Requests\ListExecutionsRequest;
use App\Modules\Execution\Http\Requests\RunWorkflowRequest;
use Illuminate\Http\JsonResponse;

class WorkflowExecutionController extends Controller
{
    public function store(
        RunWorkflowRequest $request,
        int $id,
        RunWorkflowAction $action,
    ): JsonResponse {
        try {
            $result = $action->execute(new RunWorkflowInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
                triggerPayload: (array) ($request->input('trigger_payload') ?? []),
                idempotencyKey: $request->input('idempotency_key'),
            ));
        } catch (WorkflowNotFoundException) {
            return response()->json([
                'message' => __('execution::messages.workflow_not_found'),
            ], 404);
        } catch (WorkflowNotExecutableException) {
            return response()->json([
                'message' => __('execution::messages.workflow_not_executable'),
            ], 409);
        } catch (ExecutionAlreadyRunningException) {
            return response()->json([
                'message' => __('execution::messages.execution_already_running'),
            ], 409);
        }

        return response()->json([
            'message' => __('execution::messages.run_success'),
            'data' => $result->execution->toArray(),
        ], $result->replayed ? 200 : 201);
    }

    public function index(
        ListExecutionsRequest $request,
        int $id,
        ListExecutionsAction $action,
    ): JsonResponse {
        try {
            $items = $action->execute(new ListExecutionsInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
                limit: (int) ($request->input('limit') ?? 50),
            ));
        } catch (WorkflowNotFoundException) {
            return response()->json([
                'message' => __('execution::messages.workflow_not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('execution::messages.list_success'),
            'data' => array_map(static fn ($d) => $d->toArray(), $items),
        ]);
    }
}
