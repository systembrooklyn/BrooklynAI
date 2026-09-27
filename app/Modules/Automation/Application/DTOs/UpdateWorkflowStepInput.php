<?php

namespace App\Modules\Automation\Application\DTOs;

final class UpdateWorkflowStepInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly int $position,
        public readonly string $integrationKey,
        public readonly string $actionKey,
        public readonly ?int $connectionId,
        public readonly array $config,
    ) {}
}
