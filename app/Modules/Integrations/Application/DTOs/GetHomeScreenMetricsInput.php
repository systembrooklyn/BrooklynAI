<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GetHomeScreenMetricsInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $propertyId,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?int $connectionId = null,
    ) {}
}
