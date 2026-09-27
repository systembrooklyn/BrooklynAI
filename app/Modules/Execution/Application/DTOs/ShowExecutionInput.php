<?php

namespace App\Modules\Execution\Application\DTOs;

final class ShowExecutionInput
{
    public function __construct(
        public readonly int $userId,
        public readonly int $executionId,
    ) {}
}
