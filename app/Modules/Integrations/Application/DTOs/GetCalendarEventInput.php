<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GetCalendarEventInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $eventId,
        public readonly ?int $connectionId = null,
    ) {}
}
