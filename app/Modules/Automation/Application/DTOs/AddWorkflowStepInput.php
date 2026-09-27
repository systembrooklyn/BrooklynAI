<?php

namespace App\Modules\Automation\Application\DTOs;

final class AddWorkflowStepInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly string $integrationKey,
        public readonly string $actionKey,
        public readonly ?int $connectionId,
        public readonly array $config,
    ) {}
}
