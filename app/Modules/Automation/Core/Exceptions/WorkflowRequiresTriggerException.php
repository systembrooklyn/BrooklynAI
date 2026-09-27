<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class WorkflowRequiresTriggerException extends RuntimeException
{
    public static function forWorkflow(int $workflowId): self
    {
        return new self(sprintf('Workflow %d cannot be activated without a trigger.', $workflowId));
    }
}
