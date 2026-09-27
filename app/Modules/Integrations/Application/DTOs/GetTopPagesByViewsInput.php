<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GetTopPagesByViewsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $propertyId,
        public readonly ?int $connectionId = null,
    ) {}
}
