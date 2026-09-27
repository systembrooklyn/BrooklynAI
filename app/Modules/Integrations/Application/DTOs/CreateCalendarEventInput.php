<?php

namespace App\Modules\Integrations\Application\DTOs;

final class CreateCalendarEventInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly string $title,
        public readonly ?string $description,
        public readonly string $startDateTime,
        public readonly string $endDateTime,
        public readonly array $attendees,
        public readonly bool $sendEmailNotification,
        public readonly ?string $notificationSubject,
        public readonly ?string $notificationBody,
        public readonly ?int $connectionId = null,
    ) {}
}
