<?php

namespace App\Modules\Integrations\Application\DTOs;

final class CreateGmailDraftResult
{
    public function __construct(
        public readonly bool $created,
        public readonly ?string $draftId = null,
        public readonly ?string $messageId = null,
    ) {}
}
