<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class TriggerNotFoundException extends RuntimeException
{
    public static function forWorkflow(int $workflowId): self
    {
        return new self(sprintf('Workflow %d has no trigger.', $workflowId));
    }
}
