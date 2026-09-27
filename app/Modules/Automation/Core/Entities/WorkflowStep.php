<?php

namespace App\Modules\Automation\Core\Entities;

final class WorkflowStep
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $workflowId,
        public readonly int $position,
        public readonly string $integrationKey,
        public readonly string $actionKey,
        public readonly ?int $connectionId,
        public readonly array $config,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}
}
