<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ModifyGmailMessageInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $messageId,
        public readonly ?int $connectionId = null,
    ) {}
}
