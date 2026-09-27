<?php

namespace App\Modules\Automation\Application\DTOs;

final class WorkflowStepPositionInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly int $position,
    ) {}
}
