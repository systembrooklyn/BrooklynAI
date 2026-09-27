<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Application\Actions\AddWorkflowStepAction;
use App\Modules\Automation\Application\Actions\DeleteWorkflowStepAction;
use App\Modules\Automation\Application\Actions\UpdateWorkflowStepAction;
use App\Modules\Automation\Application\DTOs\AddWorkflowStepInput;
use App\Modules\Automation\Application\DTOs\UpdateWorkflowStepInput;
use App\Modules\Automation\Application\DTOs\WorkflowStepPositionInput;
use App\Modules\Automation\Core\Exceptions\TemplateSyntaxException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowStepNotFoundException;
use App\Modules\Automation\Http\Requests\AddWorkflowStepRequest;
use App\Modules\Automation\Http\Requests\UpdateWorkflowStepRequest;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use Illuminate\Http\JsonResponse;

class WorkflowStepController extends Controller
{
    public function store(
        AddWorkflowStepRequest $request,
        int $id,
        AddWorkflowStepAction $action,
    ): JsonResponse {
        try {
            $data = $action->execute(new AddWorkflowStepInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
                integrationKey: (string) $request->input('integration_key'),
                actionKey: (string) $request->input('action_key'),
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
                config: (array) ($request->input('config') ?? []),
            ));
        } catch (WorkflowNotFoundException) {
            return $this->workflowNotFound();
        } catch (ConnectionNotFoundException) {
            return $this->connectionNotFound();
        } catch (TemplateSyntaxException $e) {
            return $this->templateError($e);
        }

        return response()->json([
            'message' => __('automation::messages.step_create_success'),
            'data' => $data->toArray(),
        ], 201);
    }

    public function update(
        UpdateWorkflowStepRequest $request,
        int $id,
        int $position,
        UpdateWorkflowStepAction $action,
    ): JsonResponse {
        try {
            $data = $action->execute(new UpdateWorkflowStepInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
                position: $position,
                integrationKey: (string) $request->input('integration_key'),
                actionKey: (string) $request->input('action_key'),
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
                config: (array) ($request->input('config') ?? []),
            ));
        } catch (WorkflowNotFoundException) {
            return $this->workflowNotFound();
        } catch (WorkflowStepNotFoundException) {
            return $this->stepNotFound();
        } catch (ConnectionNotFoundException) {
            return $this->connectionNotFound();
        } catch (TemplateSyntaxException $e) {
            return $this->templateError($e);
        }

        return response()->json([
            'message' => __('automation::messages.step_update_success'),
            'data' => $data->toArray(),
        ]);
    }

    public function destroy(
        int $id,
        int $position,
        DeleteWorkflowStepAction $action,
    ): JsonResponse {
        try {
            $action->execute(new WorkflowStepPositionInput(
                userId: (int) request()->user()->id,
                workflowId: $id,
                position: $position,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->workflowNotFound();
        } catch (WorkflowStepNotFoundException) {
            return $this->stepNotFound();
        }

        return response()->json([
            'message' => __('automation::messages.step_delete_success'),
        ]);
    }

    private function workflowNotFound(): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.not_found'),
        ], 404);
    }

    private function stepNotFound(): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.step_not_found'),
        ], 404);
    }

    private function connectionNotFound(): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.connection_not_found'),
        ], 404);
    }

    private function templateError(TemplateSyntaxException $e): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.validation_failed'),
            'errors' => [
                'config' => [$e->getMessage()],
            ],
        ], 422);
    }
}
