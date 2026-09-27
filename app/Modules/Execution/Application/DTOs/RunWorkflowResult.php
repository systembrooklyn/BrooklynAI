<?php

namespace App\Modules\Execution\Application\DTOs;

final class RunWorkflowResult
{
    public function __construct(
        public readonly ExecutionData $execution,
        public readonly bool $replayed,
    ) {}
}
