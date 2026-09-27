<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GetReportInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $propertyId,
        public readonly array $dimensions,
        public readonly array $metrics,
        public readonly string $startDate,
        public readonly string $endDate,
        public readonly ?int $connectionId = null,
    ) {}
}
