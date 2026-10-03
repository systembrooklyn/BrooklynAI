<?php

namespace App\Modules\Integrations\Application\DTOs;

final class CreateGmailDraftInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $fromEmail,
        public readonly string $to,
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly ?int $connectionId = null,
    ) {}
}
