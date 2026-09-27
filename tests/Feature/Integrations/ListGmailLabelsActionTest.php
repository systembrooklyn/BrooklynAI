<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailLabelReader;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeGmailLabelReader;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

class ListGmailLabelsActionTest extends TestCase
{
    use RefreshDatabase;

    private FakeGmailLabelReader $reader;

    private FakeGoogleCredentialsResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new FakeGmailLabelReader;
        $this->resolver = new FakeGoogleCredentialsResolver;

        $this->app->instance(GmailLabelReader::class, $this->reader);
        $this->app->instance(\App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver::class, $this->resolver);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/gmail/labels')->assertStatus(401);
    }

    public function test_returns_whitelisted_labels(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->reader->labels = [
            ['id' => 'INBOX', 'name' => 'INBOX', 'type' => 'system'],
            ['id' => 'Label_1', 'name' => 'Project', 'type' => 'user'],
        ];

        $response = $this->getJson('/api/gmail/labels');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Labels retrieved successfully.');
        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', 'INBOX');
        $response->assertJsonPath('data.0.name', 'INBOX');
        $response->assertJsonPath('data.0.type', 'system');
        $response->assertJsonPath('data.1.id', 'Label_1');
    }

    public function test_returns_500_when_credentials_unavailable(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->resolver->throws = GoogleCredentialsUnavailableException::forLegacyUser((int) $user->id);

        $this->getJson('/api/gmail/labels')->assertStatus(500);
    }

    public function test_returns_404_when_connection_not_owned(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->resolver->throws = ConnectionNotFoundException::forUser((int) $user->id, 999);

        $this->getJson('/api/gmail/labels?connection_id=999')->assertStatus(404);
    }

    public function test_returns_500_when_gmail_raises(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->reader->throws = new GoogleServiceException('Rate limited', 429);

        $response = $this->getJson('/api/gmail/labels');

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to retrieve Gmail labels');
    }
}
