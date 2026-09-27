<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ListDocumentsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $connectionId = null,
    ) {}
}
