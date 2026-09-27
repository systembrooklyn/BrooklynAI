<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class WorkflowNotFoundException extends RuntimeException
{
    public static function forUser(int $userId, int $workflowId): self
    {
        return new self(sprintf('Workflow %d not found for user %d.', $workflowId, $userId));
    }
}
