<?php

namespace App\Modules\Automation\Application\DTOs;

final class WorkflowIdInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
    ) {}
}
