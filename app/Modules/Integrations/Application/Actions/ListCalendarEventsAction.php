<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ListCalendarEventsInput;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;
use Google\Service\Calendar\Events as GoogleCalendarEvents;

final class ListCalendarEventsAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleCalendarClient $calendar,
    ) {}

    public function execute(ListCalendarEventsInput $input): GoogleCalendarEvents
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->calendar->listEvents($resolved, 10);
    }
}
