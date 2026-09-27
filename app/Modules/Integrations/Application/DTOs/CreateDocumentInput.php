<?php

namespace App\Modules\Integrations\Application\DTOs;

final class CreateDocumentInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly ?int $connectionId = null,
    ) {}
}
