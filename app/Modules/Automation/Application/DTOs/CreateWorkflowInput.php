<?php

namespace App\Modules\Automation\Application\DTOs;

final class CreateWorkflowInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly ?string $description,
    ) {}
}
