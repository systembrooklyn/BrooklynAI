<?php

namespace App\Modules\Integrations\Application\DTOs;

final class SendGmailEmailResult
{
    public function __construct(
        public readonly bool $sent,
        public readonly ?string $messageId = null,
    ) {}
}
