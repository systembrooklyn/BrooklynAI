<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Google\Service\Calendar as GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateCalendarEventActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedCalendar;

    private object $capturedGmail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedCalendar = new class extends GoogleCalendarClient
        {
            public array $usedCredentials = [];

            public ?GoogleCalendarEvent $eventToReturn = null;

            protected function createCalendarService(ResolvedGoogleCredentials $credentials): GoogleCalendarService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createCalendarService($credentials);
            }

            protected function dispatchInsert(GoogleCalendarService $service, GoogleCalendarEvent $event): GoogleCalendarEvent
            {
                return $this->eventToReturn ?? $event;
            }
        };

        $this->capturedCalendar->eventToReturn = $this->fakeCalendarEvent('evt-created');

        $this->capturedGmail = new class extends GmailEmailSender
        {
            public array $sentRawMessages = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
            {
                return parent::createService($credentials);
            }

            protected function dispatch(
                GoogleGmailService $service,
                GoogleGmailMessage $message,
            ): ?GoogleGmailMessage {
                $this->sentRawMessages[] = (string) $message->getRaw();

                return null;
            }
        };

        $this->app->instance(GoogleCalendarClient::class, $this->capturedCalendar);
        $this->app->instance(GmailEmailSender::class, $this->capturedGmail);
    }

    private function fakeCalendarEvent(string $id): GoogleCalendarEvent
    {
        $event = new GoogleCalendarEvent;
        $event->setId($id);
        $event->setSummary('Meeting');
        $event->setStatus('confirmed');
        $event->setStart(new \Google\Service\Calendar\EventDateTime([
            'dateTime' => '2026-09-20T10:00:00+02:00',
            'timeZone' => 'Africa/Cairo',
        ]));
        $event->setEnd(new \Google\Service\Calendar\EventDateTime([
            'dateTime' => '2026-09-20T11:00:00+02:00',
            'timeZone' => 'Africa/Cairo',
        ]));

        return $event;
    }

    private function decodeRaw(string $raw): string
    {
        $b = str_replace(['-', '_'], ['+', '/'], $raw);
        $pad = strlen($b) % 4;
        if ($pad > 0) {
            $b .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($b);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/calendar/events', [
            'title' => 'T', 'start' => '2026-09-20 10:00:00', 'end' => '2026-09-20 11:00:00',
        ])->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/calendar/events', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'start', 'end']);
    }

    public function test_legacy_create_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Event created successfully');
        $this->assertCount(1, $this->capturedCalendar->usedCredentials);
        $this->assertSame('legacy-access', $this->capturedCalendar->usedCredentials[0]->accessToken);
    }

    public function test_legacy_create_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(500);
        $response->assertJsonPath('error', 'Failed to create calendar event');
    }

    public function test_uses_explicit_connection_when_provided(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-a',
            'access_token' => 'conn-access',
            'refresh_token' => 'conn-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/calendar/events?connection_id='.$connection->id, [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertSame('conn-access', $this->capturedCalendar->usedCredentials[0]->accessToken);
    }

    public function test_returns_404_when_connection_not_owned(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create([
            'google_access_token' => 'attacker-legacy',
            'google_refresh_token' => 'attacker-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'external_account_id' => 'sub-owner',
            'access_token' => 'owner-access',
            'refresh_token' => 'owner-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);
        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/calendar/events?connection_id='.$connection->id, [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Connection not found');
        $this->assertCount(0, $this->capturedCalendar->usedCredentials);
    }

    public function test_with_connection_id_never_falls_back_to_legacy(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-empty',
            'access_token' => null,
            'refresh_token' => null,
            'scopes' => ['openid'],
            'status' => 'active',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/calendar/events?connection_id='.$connection->id, [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(500);
        $this->assertCount(0, $this->capturedCalendar->usedCredentials);
    }

    public function test_sends_plain_text_notification_with_legacy_mime_format(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
            'attendees' => ['bob@example.com', 'carol@example.com'],
            'email_notification' => [
                'send' => true,
                'subject' => 'Please join',
                'body' => 'Details here.',
            ],
        ]);

        $response->assertStatus(201);
        $this->assertCount(2, $this->capturedGmail->sentRawMessages);

        foreach ($this->capturedGmail->sentRawMessages as $index => $raw) {
            $decoded = $this->decodeRaw($raw);
            $this->assertStringContainsString('From: Alice <alice@example.com>', $decoded);
            $this->assertStringContainsString('Subject: Please join', $decoded);
            $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $decoded);
        }

        $first = $this->decodeRaw($this->capturedGmail->sentRawMessages[0]);
        $this->assertStringContainsString('To: bob@example.com', $first);
        $second = $this->decodeRaw($this->capturedGmail->sentRawMessages[1]);
        $this->assertStringContainsString('To: carol@example.com', $second);
    }

    public function test_email_notification_failure_does_not_fail_event_creation(): void
    {
        $user = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $this->capturedGmail = new class extends GmailEmailSender
        {
            protected function dispatch(
                GoogleGmailService $service,
                GoogleGmailMessage $message,
            ): ?GoogleGmailMessage {
                throw new \RuntimeException('Simulated Gmail failure');
            }
        };

        $this->app->instance(GmailEmailSender::class, $this->capturedGmail);

        $response = $this->postJson('/api/calendar/events', [
            'title' => 'Meeting',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
            'attendees' => ['bob@example.com'],
            'email_notification' => ['send' => true],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Event created successfully');
    }
}
