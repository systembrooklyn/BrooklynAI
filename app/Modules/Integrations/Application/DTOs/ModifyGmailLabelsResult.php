<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ModifyGmailLabelsResult
{
    public function __construct(
        public readonly bool $modified,
        public readonly string $messageId,
        public readonly string $labelId,
    ) {}
}
