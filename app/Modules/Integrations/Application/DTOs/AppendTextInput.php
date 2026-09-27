<?php

namespace App\Modules\Integrations\Application\DTOs;

final class AppendTextInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $documentId,
        public readonly string $text,
        public readonly ?int $connectionId = null,
    ) {}
}
