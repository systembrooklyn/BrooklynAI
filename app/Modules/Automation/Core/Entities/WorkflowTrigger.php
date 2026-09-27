<?php

namespace App\Modules\Automation\Core\Entities;

final class WorkflowTrigger
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $workflowId,
        public readonly string $integrationKey,
        public readonly string $triggerKey,
        public readonly ?int $connectionId,
        public readonly string $strategy,
        public readonly array $config,
        public readonly ?int $intervalMinutes,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
        public readonly ?int $pollCursor = null,
        public readonly ?\DateTimeImmutable $nextPollAt = null,
    ) {}
}
