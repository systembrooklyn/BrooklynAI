<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class WorkflowStepNotFoundException extends RuntimeException
{
    public static function forPosition(int $workflowId, int $position): self
    {
        return new self(sprintf(
            'Workflow %d has no step at position %d.',
            $workflowId,
            $position,
        ));
    }
}
