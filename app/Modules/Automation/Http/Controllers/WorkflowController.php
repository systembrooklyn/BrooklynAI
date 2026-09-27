<?php

namespace App\Modules\Automation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automation\Application\Actions\ActivateWorkflowAction;
use App\Modules\Automation\Application\Actions\CreateWorkflowAction;
use App\Modules\Automation\Application\Actions\DeleteWorkflowAction;
use App\Modules\Automation\Application\Actions\ListWorkflowsAction;
use App\Modules\Automation\Application\Actions\PauseWorkflowAction;
use App\Modules\Automation\Application\Actions\RestoreWorkflowAction;
use App\Modules\Automation\Application\Actions\ShowWorkflowAction;
use App\Modules\Automation\Application\Actions\UpdateWorkflowAction;
use App\Modules\Automation\Application\DTOs\CreateWorkflowInput;
use App\Modules\Automation\Application\DTOs\ListWorkflowsInput;
use App\Modules\Automation\Application\DTOs\UpdateWorkflowInput;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotRunnableException;
use App\Modules\Automation\Core\Exceptions\WorkflowRequiresTriggerException;
use App\Modules\Automation\Http\Requests\CreateWorkflowRequest;
use App\Modules\Automation\Http\Requests\ListWorkflowsRequest;
use App\Modules\Automation\Http\Requests\UpdateWorkflowRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    public function index(ListWorkflowsRequest $request, ListWorkflowsAction $action): JsonResponse
    {
        $items = $action->execute(new ListWorkflowsInput(
            userId: (int) $request->user()->id,
            onlyTrashed: (bool) $request->boolean('trashed'),
        ));

        return response()->json([
            'message' => __('automation::messages.list_success'),
            'data' => array_map(static fn ($d) => $d->toArray(), $items),
        ]);
    }

    public function store(CreateWorkflowRequest $request, CreateWorkflowAction $action): JsonResponse
    {
        $data = $action->execute(new CreateWorkflowInput(
            userId: (int) $request->user()->id,
            name: (string) $request->input('name'),
            description: $request->input('description'),
        ));

        return response()->json([
            'message' => __('automation::messages.create_success'),
            'data' => $data->toArray(),
        ], 201);
    }

    public function show(Request $request, int $id, ShowWorkflowAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new WorkflowIdInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'message' => __('automation::messages.show_success'),
            'data' => $data->toArray(),
        ]);
    }

    public function update(UpdateWorkflowRequest $request, int $id, UpdateWorkflowAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new UpdateWorkflowInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
                name: (string) $request->input('name'),
                description: $request->input('description'),
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'message' => __('automation::messages.update_success'),
            'data' => $data->toArray(),
        ]);
    }

    public function destroy(Request $request, int $id, DeleteWorkflowAction $action): JsonResponse
    {
        try {
            $action->execute(new WorkflowIdInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'message' => __('automation::messages.delete_success'),
        ]);
    }

    public function restore(Request $request, int $id, RestoreWorkflowAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new WorkflowIdInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'message' => __('automation::messages.restore_success'),
            'data' => $data->toArray(),
        ]);
    }

    // public function activate(Request $request, int $id, ActivateWorkflowAction $action): JsonResponse
    // {
    //     try {
    //         $data = $action->execute(new WorkflowIdInput(
    //             userId: (int) $request->user()->id,
    //             workflowId: $id,
    //         ));
    //     } catch (WorkflowNotFoundException) {
    //         return $this->notFound();
    //     } catch (WorkflowRequiresTriggerException) {
    //         return response()->json([
    //             'message' => __('automation::messages.cannot_activate_without_trigger'),
    //         ], 409);
    //     } catch (WorkflowNotRunnableException) {
    //         return $this->invalidTransition();
    //     }

    //     return response()->json([
    //         'message' => __('automation::messages.activate_success'),
    //         'data' => $data->toArray(),
    //     ]);
    // }
    public function activate(Request $request, int $id, ActivateWorkflowAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new WorkflowIdInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        } catch (WorkflowRequiresTriggerException) {
            return response()->json([
                'message' => __('automation::messages.cannot_activate_without_trigger'),
            ], 409);
        } catch (WorkflowCapabilityException $e) {
            return response()->json([
                'message' => __($this->capabilityMessageKey($e->errorCode())),
                'error' => $e->errorCode(),
                'context' => $e->context(),
            ], 409);
        } catch (WorkflowNotRunnableException) {
            return $this->invalidTransition();
        }

        return response()->json([
            'message' => __('automation::messages.activate_success'),
            'data' => $data->toArray(),
        ]);
    }

    private function capabilityMessageKey(string $errorCode): string
    {
        return match ($errorCode) {
            WorkflowCapabilityException::ERROR_MISSING_CONNECTION => 'automation::messages.capability_missing_connection',
            WorkflowCapabilityException::ERROR_CONNECTION_NOT_FOUND => 'automation::messages.capability_connection_unavailable',
            WorkflowCapabilityException::ERROR_UNSUPPORTED_CAPABILITY => 'automation::messages.capability_unsupported',
            WorkflowCapabilityException::ERROR_MISSING_SCOPES => 'automation::messages.capability_missing_scopes',
            WorkflowCapabilityException::ERROR_UNKNOWN_INTEGRATION => 'automation::messages.capability_unknown_integration',
            WorkflowCapabilityException::ERROR_UNKNOWN_TRIGGER => 'automation::messages.capability_unknown_trigger',
            WorkflowCapabilityException::ERROR_UNKNOWN_ACTION => 'automation::messages.capability_unknown_action',
            default => 'automation::messages.capability_validation_failed',
        };
    }

    public function pause(Request $request, int $id, PauseWorkflowAction $action): JsonResponse
    {
        try {
            $data = $action->execute(new WorkflowIdInput(
                userId: (int) $request->user()->id,
                workflowId: $id,
            ));
        } catch (WorkflowNotFoundException) {
            return $this->notFound();
        } catch (WorkflowNotRunnableException) {
            return $this->invalidTransition();
        }

        return response()->json([
            'message' => __('automation::messages.pause_success'),
            'data' => $data->toArray(),
        ]);
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.not_found'),
        ], 404);
    }

    private function invalidTransition(): JsonResponse
    {
        return response()->json([
            'message' => __('automation::messages.invalid_transition'),
        ], 409);
    }
}
