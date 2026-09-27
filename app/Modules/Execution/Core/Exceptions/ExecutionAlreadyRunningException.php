<?php

namespace App\Modules\Execution\Core\Exceptions;

use RuntimeException;

final class ExecutionAlreadyRunningException extends RuntimeException
{
    public static function forWorkflow(int $workflowId): self
    {
        return new self(sprintf(
            'Workflow %d already has an execution in progress.',
            $workflowId,
        ));
    }
}
