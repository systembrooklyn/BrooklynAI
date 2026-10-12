<?php

namespace App\Modules\Automation\Http\Controllers\Concerns;

use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use Illuminate\Http\JsonResponse;

trait TranslatesCapabilityErrors
{
    protected function capabilityError(WorkflowCapabilityException $e): JsonResponse
    {
        return response()->json([
            'message' => __($this->capabilityMessageKey($e->errorCode())),
            'error' => $e->errorCode(),
            'context' => $e->context(),
        ], 409);
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
            WorkflowCapabilityException::ERROR_INCOMPATIBLE_TRIGGER => 'automation::messages.capability_incompatible_trigger',
            default => 'automation::messages.capability_validation_failed',
        };
    }
}
