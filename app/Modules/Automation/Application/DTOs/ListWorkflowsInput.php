<?php

namespace App\Modules\Automation\Application\DTOs;

final class ListWorkflowsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly bool $onlyTrashed = false,
    ) {}
}
