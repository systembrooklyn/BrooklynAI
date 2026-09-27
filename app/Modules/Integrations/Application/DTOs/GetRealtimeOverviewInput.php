<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GetRealtimeOverviewInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $propertyId,
        public readonly ?int $connectionId = null,
    ) {}
}
