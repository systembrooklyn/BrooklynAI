<?php

namespace App\Modules\Execution\Core\Entities;

use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use DateTimeImmutable;

final class Execution
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $workflowId,
        public readonly int $userId,
        public readonly ExecutionStatus $status,
        public readonly string $triggerSource,
        public readonly ?array $triggerPayload,
        public readonly array $workflowSnapshot,
        public readonly ?string $idempotencyKey,
        public readonly ?string $errorMessage,
        public readonly ?DateTimeImmutable $startedAt,
        public readonly ?DateTimeImmutable $finishedAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        public readonly int $retryAttempts = 0,
    ) {}
}
