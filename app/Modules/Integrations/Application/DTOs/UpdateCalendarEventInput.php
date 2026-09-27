<?php

namespace App\Modules\Integrations\Application\DTOs;

final class UpdateCalendarEventInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $eventId,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $startDateTime,
        public readonly string $endDateTime,
        public readonly ?array $attendees,
        public readonly ?int $connectionId = null,
    ) {}
}
