<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ListGmailLabelsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $connectionId = null,
    ) {}
}
