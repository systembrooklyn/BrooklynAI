<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Calendar\GoogleCalendarClient;
use Google\Service\Calendar as GoogleCalendarService;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\EventDateTime as GoogleCalendarEventDateTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UpdateCalendarEventActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedCalendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedCalendar = new class extends GoogleCalendarClient
        {
            public array $usedCredentials = [];

            protected function createCalendarService(ResolvedGoogleCredentials $credentials): GoogleCalendarService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createCalendarService($credentials);
            }

            protected function dispatchGet(GoogleCalendarService $service, string $eventId): GoogleCalendarEvent
            {
                return new GoogleCalendarEvent;
            }

            protected function dispatchUpdate(GoogleCalendarService $service, string $eventId, GoogleCalendarEvent $event): GoogleCalendarEvent
            {
                $event->setId($eventId);
                $event->setStatus('confirmed');
                $event->setStart(new GoogleCalendarEventDateTime([
                    'dateTime' => '2026-09-20T10:00:00+02:00',
                    'timeZone' => 'Africa/Cairo',
                ]));
                $event->setEnd(new GoogleCalendarEventDateTime([
                    'dateTime' => '2026-09-20T11:00:00+02:00',
                    'timeZone' => 'Africa/Cairo',
                ]));

                return $event;
            }
        };

        $this->app->instance(GoogleCalendarClient::class, $this->capturedCalendar);
    }

    public function test_requires_authentication(): void
    {
        $this->putJson('/api/calendar/events/evt-1', [
            'title' => 'T', 'start' => '2026-09-20 10:00:00', 'end' => '2026-09-20 11:00:00',
        ])->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/calendar/events/evt-1', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title', 'start', 'end']);
    }

    public function test_legacy_update_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/calendar/events/evt-1', [
            'title' => 'Updated',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Event updated successfully');
        $this->assertSame('legacy-access', $this->capturedCalendar->usedCredentials[0]->accessToken);
    }

    public function test_legacy_update_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/calendar/events/evt-1', [
            'title' => 'Updated',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(500);
    }

    public function test_uses_explicit_connection_when_provided(): void
    {
        $user = User::factory()->create([
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

        $response = $this->putJson('/api/calendar/events/evt-1?connection_id='.$connection->id, [
            'title' => 'Updated',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(200);
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

        $response = $this->putJson('/api/calendar/events/evt-1?connection_id='.$connection->id, [
            'title' => 'Updated',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Connection not found');
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

        $response = $this->putJson('/api/calendar/events/evt-1?connection_id='.$connection->id, [
            'title' => 'Updated',
            'start' => '2026-09-20 10:00:00',
            'end' => '2026-09-20 11:00:00',
        ]);

        $response->assertStatus(500);
        $this->assertCount(0, $this->capturedCalendar->usedCredentials);
    }
}
