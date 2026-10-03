<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ModifyGmailMessageResult
{
    public function __construct(
        public readonly bool $modified,
        public readonly string $messageId,
    ) {}
}
