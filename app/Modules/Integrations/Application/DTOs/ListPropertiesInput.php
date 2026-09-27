<?php

namespace App\Modules\Integrations\Application\DTOs;

final class ListPropertiesInput
{
    public function __construct(
        public readonly int $userId,
        public readonly ?int $connectionId = null,
    ) {}
}
