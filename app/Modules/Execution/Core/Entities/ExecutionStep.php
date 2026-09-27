<?php

namespace App\Modules\Execution\Core\Entities;

use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use DateTimeImmutable;

final class ExecutionStep
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $executionId,
        public readonly int $position,
        public readonly array $stepSnapshot,
        public readonly ExecutionStepStatus $status,
        public readonly ?array $input,
        public readonly ?array $output,
        public readonly ?string $errorMessage,
        public readonly ?DateTimeImmutable $startedAt,
        public readonly ?DateTimeImmutable $finishedAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {}
}
