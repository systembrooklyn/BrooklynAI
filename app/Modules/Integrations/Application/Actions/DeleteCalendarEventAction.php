<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\DeleteCalendarEventInput;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;

final class DeleteCalendarEventAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleCalendarClient $calendar,
    ) {}

    public function execute(DeleteCalendarEventInput $input): void
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $this->calendar->deleteEvent($resolved, $input->eventId);
    }
}
