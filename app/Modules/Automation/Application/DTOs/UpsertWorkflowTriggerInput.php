<?php

namespace App\Modules\Automation\Application\DTOs;

final class UpsertWorkflowTriggerInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly string $integrationKey,
        public readonly string $triggerKey,
        public readonly ?int $connectionId,
        public readonly array $config,
        public readonly ?int $intervalMinutes,
    ) {}
}
