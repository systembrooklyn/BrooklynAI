<?php

namespace App\Modules\Automation\Application\DTOs;

final class UpdateWorkflowInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly string $name,
        public readonly ?string $description,
    ) {}
}
