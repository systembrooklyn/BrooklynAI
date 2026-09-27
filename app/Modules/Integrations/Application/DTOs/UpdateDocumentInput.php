<?php

namespace App\Modules\Integrations\Application\DTOs;

final class UpdateDocumentInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $documentId,
        public readonly string $content,
        public readonly ?int $connectionId = null,
    ) {}
}
