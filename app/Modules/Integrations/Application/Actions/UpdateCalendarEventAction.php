<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\UpdateCalendarEventInput;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;
use Google\Service\Calendar\Event as GoogleCalendarEvent;

final class UpdateCalendarEventAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleCalendarClient $calendar,
    ) {}

    public function execute(UpdateCalendarEventInput $input): GoogleCalendarEvent
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $startDateTime = str_replace(' ', 'T', $input->startDateTime);
        $endDateTime = str_replace(' ', 'T', $input->endDateTime);

        return $this->calendar->updateEvent(
            credentials: $resolved,
            eventId: $input->eventId,
            title: $input->title,
            description: $input->description,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            attendees: $input->attendees,
        );
    }
}
