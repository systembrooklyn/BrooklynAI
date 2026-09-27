<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GenerateAndEmailPdfInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userEmail,
        public readonly array $toEmails,
        public readonly string $subject,
        public readonly ?string $body,
        public readonly array $data,
        public readonly ?string $filename = 'document.pdf',
        public readonly ?int $connectionId = null,
    ) {}
}
