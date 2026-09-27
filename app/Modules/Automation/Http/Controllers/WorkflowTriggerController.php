<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Application\Actions\DeleteWorkflowTriggerAction;
use App\Modules\Automation\Application\Actions\UpsertWorkflowTriggerAction;
use App\Modules\Automation\Application\DTOs\UpsertWorkflowTriggerInput;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Exceptions\TemplateSyntaxException;
use App\Modules\Automation\Core\Exceptions\TriggerNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Http\Requests\UpsertWorkflowTriggerRequest;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowTriggerController extends Controller
{
    public function upsert(
        UpsertWorkflowTriggerRequest $request,
        int $id,
        UpsertWorkflowTriggerAction $action,
    ): JsonResponse {
        try {
            $data = $action->execute(new UpsertWorkflowTriggerInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
                integrationKey: (string) $request->input('integration_key'),
                triggerKey: (string) $request->input('trigger_key'),
                connectionId: $request->filled('connection_id')
                    ? (int) $request->input('connection_id')
                    : null,
                config: (array) ($request->input('config') ?? []),
                intervalMinutes: $request->filled('interval_minutes')
                    ? (int) $request->input('interval_minutes')
                    : null,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        } catch (ConnectionNotFoundException) {
            return $this->connectionNotFound();
        } catch (TemplateSyntaxException $e) {
            return $this->templateError($e);
        }

        return response()->json([
            'message' => __('automation::messages.trigger_upsert_success'),
            'data' => $data->toArray(),
        ]);
    }

    public function destroy(
        Request $request,
        int $id,
        DeleteWorkflowTriggerAction $action,
    ): JsonResponse {
        try {
            $action->execute(new WorkflowIdInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        } catch (TriggerNotFoundException) {
            return response()->json([
                'message' => __('automation::messages.trigger_not_found'),
            ], 404);
        }

        return response()->json([
            'message' => __('automation::messages.trigger_delete_success'),
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.not_found'),
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
