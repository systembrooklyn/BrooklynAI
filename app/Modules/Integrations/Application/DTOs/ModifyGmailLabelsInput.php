<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ModifyGmailLabelsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $messageId,
        public readonly string $labelId,
        public readonly ?int $connectionId = null,
    ) {}
}
