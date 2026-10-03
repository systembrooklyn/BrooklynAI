<?php

namespace App\Modules\Integrations\Application\DTOs;

final class CreateGmailLabelInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly ?int $connectionId = null,
    ) {}
}
