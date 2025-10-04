<?php

namespace App\Services;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\EventDateTime as GoogleCalendarEventDateTime;
use Illuminate\Support\Facades\Log;
use Google\Service\Gmail as Google_Service_Gmail;
use Google\Service\Gmail\Message as Google_Service_Gmail_Message;

class GoogleCalendarService
{
    protected $client;
    protected $service;
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;

        $this->client = new Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setAccessType('offline');

        // Convert expiration to seconds until expiry
        $expiresAt = strtotime($user->google_token_expires_at);
        $expiresIn = max(0, $expiresAt - time());

        $this->client->setAccessToken(json_encode([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $expiresIn,
        ]));

        // Refresh token if expired
        if ($this->client->isAccessTokenExpired()) {
            if ($user->google_refresh_token) {
                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
                    $user->update([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                    ]);
                    $this->client->setAccessToken($newToken);
                } catch (\Exception $e) {
                    Log::error('Failed to refresh Google token: ' . $e->getMessage());
                    throw new \Exception('Google authentication failed. Please log in again.');
                }
            } else {
                throw new \Exception('No refresh token available. Please log in again.');
            }
        }

        $this->service = new Calendar($this->client);
    }

    /**
     * List upcoming calendar events
     */
    public function listEvents($maxResults = 50, $timeMin = null, $timeMax = null)
    {
        $service = new Calendar($this->client);

        $timeMin = $timeMin ?? now()->subDays(1)->toISOString();
        $timeMax = $timeMax ?? now()->addYears(1)->toISOString();

        $optParams = [
            'maxResults' => min($maxResults, 250),
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $timeMin,
            'timeMax' => $timeMax,
        ];

        try {
            return $service->events->listEvents('primary', $optParams);
        } catch (\Exception $e) {
            Log::error('Google Calendar List Error: ' . $e->getMessage(), [
                'user_email' => auth()->user()?->email ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Create a new calendar event
     */
    public function createEvent($title, $description, $startDateTime, $endDateTime, $attendees = [])
    {
        // Use valid IANA timezone from config, fallback to Africa/Cairo
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

        if (!empty($attendees)) {
            $event->attendees = array_map(function ($email) {
                return ['email' => $email];
            }, $attendees);
        }

        return $this->service->events->insert('primary', $event);
    }


    public function getService()
    {
        return $this->service; // which is \Google_Service_Calendar
    }
    









    public function sendEmailToAttendees($attendees, $subject, $body)
{
    try {
        $fromName = $this->user->name ?? 'Team';
        $fromEmail = $this->user->email;
        $sender = "$fromName <$fromEmail>";

        // Initialize Gmail service
        $gmailService = new Google_Service_Gmail($this->client);

        foreach ($attendees as $to) {
            // Build headers
            $headers = [
                'From' => $sender,
                'To' => $to,
                'Subject' => $subject,
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Transfer-Encoding' => 'quoted-printable'
            ];

            $headerLines = '';
            foreach ($headers as $key => $value) {
                $headerLines .= "$key: $value\r\n";
            }
            $headerLines .= "\r\n"; // End headers

            $encodedBody = quoted_printable_encode($body);
            $rawMessage = $headerLines . $encodedBody;

            // Base64 encode and make URL-safe
            $encoded = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($rawMessage));

            // Create Gmail message object
            $msg = new Google_Service_Gmail_Message();
            $msg->setRaw($encoded);

            // Send email
            $gmailService->users_messages->send('me', $msg);
        }

        return true;
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Gmail send failed: ' . $e->getMessage());
        throw $e;
    }
}
}
