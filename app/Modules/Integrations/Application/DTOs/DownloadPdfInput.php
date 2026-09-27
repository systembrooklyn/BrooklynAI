<?php

namespace App\Modules\Integrations\Application\DTOs;

final class DownloadPdfInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $documentId,
        public readonly ?int $connectionId = null,
    ) {}
}
