<?php

namespace App\Modules\Automation\Core\Entities;

use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use DateTimeImmutable;

final class Workflow
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $userId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly WorkflowStatus $status,
        public readonly ?DateTimeImmutable $deletedAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {}

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }
}
