<?php

namespace App\Modules\Execution\Core\Exceptions;

use RuntimeException;

final class WorkflowNotExecutableException extends RuntimeException
{
    public static function forWorkflow(int $workflowId, string $status): self
    {
        return new self(sprintf(
            'Workflow %d is not executable (status: %s).',
            $workflowId,
            $status,
        ));
    }
}
