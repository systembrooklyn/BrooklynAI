<?php

namespace App\Modules\Integrations\Infrastructure\Google\Calendar;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\EventAttendee as GoogleCalendarEventAttendee;
use Google\Service\Calendar\EventDateTime as GoogleCalendarEventDateTime;
use Google\Service\Calendar\Events as GoogleCalendarEvents;

class GoogleCalendarClient
{
    public function createEvent(
        ResolvedGoogleCredentials $credentials,
        string $title,
        ?string $description,
        string $startDateTime,
        string $endDateTime,
        array $attendees = [],
    ): GoogleCalendarEvent {
        $service = $this->createCalendarService($credentials);

        $event = $this->buildEvent($title, $description, $startDateTime, $endDateTime, $attendees);

        return $this->dispatchInsert($service, $event);
    }

    public function listEvents(
        ResolvedGoogleCredentials $credentials,
        int $maxResults = 10,
    ): GoogleCalendarEvents {
        $service = $this->createCalendarService($credentials);

        $timeMin = now()->subDays(1)->toIso8601String();
        $timeMax = now()->addYears(1)->toIso8601String();

        $optParams = [
            'maxResults' => min($maxResults, 250),
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
        ];

        return $this->dispatchList($service, $optParams);
    }

    public function getEvent(
        ResolvedGoogleCredentials $credentials,
        string $eventId,
    ): GoogleCalendarEvent {
        $service = $this->createCalendarService($credentials);

        return $this->dispatchGet($service, $eventId);
    }

    public function updateEvent(
        ResolvedGoogleCredentials $credentials,
        string $eventId,
        string $title,
        ?string $description,
        string $startDateTime,
        string $endDateTime,
        ?array $attendees,
    ): GoogleCalendarEvent {
        $service = $this->createCalendarService($credentials);

        $event = $this->dispatchGet($service, $eventId);

        $timeZone = config('app.timezone', 'Africa/Cairo');

        $event->setSummary($title);
        $event->setDescription($description);

        $startObj = new GoogleCalendarEventDateTime;
        $startObj->setDateTime($startDateTime);
        $startObj->setTimeZone($timeZone);
        $event->setStart($startObj);

        $endObj = new GoogleCalendarEventDateTime;
        $endObj->setDateTime($endDateTime);
        $endObj->setTimeZone($timeZone);
        $event->setEnd($endObj);

        if ($attendees !== null) {
            $attendeeObjects = [];
            foreach ($attendees as $email) {
                $attendee = new GoogleCalendarEventAttendee;
                $attendee->setEmail(is_string($email) ? $email : $email['email']);
                $attendeeObjects[] = $attendee;
            }
            $event->setAttendees($attendeeObjects);
        }

        return $this->dispatchUpdate($service, $eventId, $event);
    }

    public function deleteEvent(
        ResolvedGoogleCredentials $credentials,
        string $eventId,
    ): void {
        $service = $this->createCalendarService($credentials);

        $this->dispatchDelete($service, $eventId);
    }

    protected function createCalendarService(ResolvedGoogleCredentials $credentials): GoogleCalendarService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken(json_encode([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]));

        return new GoogleCalendarService($client);
    }

    protected function buildEvent(
        string $title,
        ?string $description,
        string $startDateTime,
        string $endDateTime,
        array $attendees,
    ): GoogleCalendarEvent {
        $timeZone = config('app.timezone', 'Africa/Cairo');

        $event = new GoogleCalendarEvent([
            'summary' => $title,
            'description' => $description,
            'start' => new GoogleCalendarEventDateTime([
                'dateTime' => $startDateTime,
                'timeZone' => $timeZone,
            ]),
            'end' => new GoogleCalendarEventDateTime([
                'dateTime' => $endDateTime,
                'timeZone' => $timeZone,
            ]),
        ]);

        if (! empty($attendees)) {
            $event->attendees = array_map(static function ($email) {
                return ['email' => $email];
            }, $attendees);
        }

        return $event;
    }

    protected function dispatchInsert(GoogleCalendarService $service, GoogleCalendarEvent $event): GoogleCalendarEvent
    {
        return $service->events->insert('primary', $event);
    }

    protected function dispatchList(GoogleCalendarService $service, array $optParams): GoogleCalendarEvents
    {
        return $service->events->listEvents('primary', $optParams);
    }

    protected function dispatchGet(GoogleCalendarService $service, string $eventId): GoogleCalendarEvent
    {
        return $service->events->get('primary', $eventId);
    }

    protected function dispatchUpdate(GoogleCalendarService $service, string $eventId, GoogleCalendarEvent $event): GoogleCalendarEvent
    {
        return $service->events->update('primary', $eventId, $event);
    }

    protected function dispatchDelete(GoogleCalendarService $service, string $eventId): void
    {
        $service->events->delete('primary', $eventId);
    }
}
