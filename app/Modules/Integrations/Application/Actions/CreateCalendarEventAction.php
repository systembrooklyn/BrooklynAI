<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\CreateCalendarEventInput;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Illuminate\Support\Facades\Log;

final class CreateCalendarEventAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleCalendarClient $calendar,
        private readonly GmailEmailSender $gmail,
    ) {}

    public function execute(CreateCalendarEventInput $input): GoogleCalendarEvent
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        $startDateTime = str_replace(' ', 'T', $input->startDateTime);
        $endDateTime = str_replace(' ', 'T', $input->endDateTime);

        $event = $this->calendar->createEvent(
            credentials: $resolved,
            title: $input->title,
            description: $input->description,
            startDateTime: $startDateTime,
            endDateTime: $endDateTime,
            attendees: $input->attendees,
        );

        if ($input->sendEmailNotification && ! empty($input->attendees)) {
            $subject = $input->notificationSubject ?: 'You’re invited to an event';
            $body = $input->notificationBody ?: "You've been invited to '{$input->title}'.";

            foreach ($input->attendees as $attendee) {
                try {
                    $this->gmail->sendPlainText(
                        credentials: $resolved,
                        fromName: $input->userName ?: 'Team',
                        fromEmail: $input->userEmail,
                        to: (string) $attendee,
                        subject: $subject,
                        body: $body,
                    );
                } catch (\Exception $e) {
                    Log::warning('Failed to send email: '.$e->getMessage());
                }
            }
        }

        return $event;
    }
}
