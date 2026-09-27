<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetCalendarEventInput;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;
use Google\Service\Calendar\Event as GoogleCalendarEvent;

final class GetCalendarEventAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleCalendarClient $calendar,
    ) {}

    public function execute(GetCalendarEventInput $input): GoogleCalendarEvent
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->calendar->getEvent($resolved, $input->eventId);
    }
}
