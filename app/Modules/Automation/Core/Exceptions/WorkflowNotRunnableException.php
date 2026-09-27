<?php

namespace App\Modules\Automation\Core\Exceptions;

use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use RuntimeException;

final class WorkflowNotRunnableException extends RuntimeException
{
    public static function forTransition(int $workflowId, WorkflowStatus $from, WorkflowStatus $to): self
    {
        return new self(sprintf(
            'Workflow %d cannot transition from "%s" to "%s".',
            $workflowId,
            $from->value,
            $to->value,
        ));
    }
}
