<?php

namespace App\Modules\Execution\Application\DTOs;

final class ListExecutionsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly int $limit,
    ) {}
}
