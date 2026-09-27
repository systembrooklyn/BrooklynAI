<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_catalog_requires_authentication(): void
    {
        $this->getJson('/api/catalog')->assertUnauthorized();
    }

    public function test_catalog_returns_success_envelope(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/catalog');

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'data' => ['integrations'],
        ]);
    }

    public function test_catalog_does_not_use_generic_key_property(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();
        $json = json_encode($body);

        $this->assertStringNotContainsString('"key":', $json);
    }

    public function test_catalog_does_not_use_fields_array(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();
        $json = json_encode($body);

        $this->assertStringNotContainsString('"fields"', $json);
    }

    public function test_catalog_does_not_expose_required_scopes(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();
        $json = json_encode($body);

        $this->assertStringNotContainsString('required_scopes', $json);
    }

    public function test_catalog_does_not_expose_provider_key_inside_auth(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        foreach ($body['data']['integrations'] as $integration) {
            $this->assertArrayNotHasKey('provider_key', $integration['auth']);
        }
    }

    public function test_gmail_integration_uses_canonical_keys(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        $gmail = collect($body['data']['integrations'])
            ->firstWhere('integration_key', 'google.gmail');

        $this->assertNotNull($gmail);
        $this->assertSame('google', $gmail['provider_key']);
        $this->assertSame('Gmail', $gmail['name']);
        $this->assertSame('email', $gmail['category']);
        $this->assertSame(['type' => 'oauth2'], $gmail['auth']);
        $this->assertArrayNotHasKey('key', $gmail);
    }

    public function test_gmail_trigger_uses_canonical_keys_and_config_map(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        $gmail = collect($body['data']['integrations'])
            ->firstWhere('integration_key', 'google.gmail');

        $trigger = $gmail['triggers'][0];

        $this->assertSame('new_email_received', $trigger['trigger_key']);
        $this->assertSame('New Email Received', $trigger['label']);
        $this->assertSame('poll', $trigger['strategy']);
        $this->assertSame('gmail', $trigger['capability']);
        $this->assertArrayNotHasKey('key', $trigger);
        $this->assertArrayNotHasKey('fields', $trigger);
        $this->assertArrayNotHasKey('required_scopes', $trigger);

        $this->assertArrayHasKey('config', $trigger);
        $this->assertArrayHasKey('label_id', $trigger['config']);
    }

    public function test_gmail_label_id_exposes_dynamic_options_source(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        $gmail = collect($body['data']['integrations'])
            ->firstWhere('integration_key', 'google.gmail');

        $labelId = $gmail['triggers'][0]['config']['label_id'];

        $this->assertSame('Label', $labelId['label']);
        $this->assertSame('select', $labelId['type']);
        $this->assertFalse($labelId['required']);

        $this->assertArrayHasKey('options_source', $labelId);
        $this->assertSame('gmail.labels', $labelId['options_source']['operation_id']);
        $this->assertSame(
            ['connection_id' => '{{connection_id}}'],
            $labelId['options_source']['params'],
        );
    }

    public function test_gmail_send_email_action_exposes_config_map(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        $gmail = collect($body['data']['integrations'])
            ->firstWhere('integration_key', 'google.gmail');

        $action = collect($gmail['actions'])->firstWhere('action_key', 'send_email');

        $this->assertNotNull($action);
        $this->assertSame('Send Email', $action['label']);
        $this->assertSame('gmail', $action['capability']);
        $this->assertArrayNotHasKey('key', $action);
        $this->assertArrayNotHasKey('fields', $action);
        $this->assertArrayNotHasKey('required_scopes', $action);

        $this->assertSame(['to', 'subject', 'body'], array_keys($action['config']));

        $this->assertSame('To', $action['config']['to']['label']);
        $this->assertSame('email', $action['config']['to']['type']);
        $this->assertTrue($action['config']['to']['required']);

        $this->assertSame('string', $action['config']['subject']['type']);
        $this->assertTrue($action['config']['subject']['required']);

        $this->assertSame('text', $action['config']['body']['type']);
        $this->assertTrue($action['config']['body']['required']);
    }

    public function test_non_gmail_actions_expose_empty_config_object(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        $calendar = collect($body['data']['integrations'])
            ->firstWhere('integration_key', 'google.calendar');

        $this->assertNotNull($calendar);

        foreach ($calendar['actions'] as $action) {
            $this->assertIsArray($action['config']);
            $this->assertSame([], $action['config']);
        }
    }

    public function test_catalog_contains_expected_integrations(): void
    {
        $this->actingAsUser();

        $body = $this->getJson('/api/catalog')->json();

        $keys = collect($body['data']['integrations'])->pluck('integration_key')->all();

        $this->assertContains('google.gmail', $keys);
        $this->assertContains('google.calendar', $keys);
        $this->assertContains('google.sheets', $keys);
        $this->assertContains('google.docs', $keys);
        $this->assertContains('google.analytics', $keys);
        $this->assertContains('google.drive', $keys);
    }

    public function test_catalog_response_is_deterministic(): void
    {
        $this->actingAsUser();

        $first = $this->getJson('/api/catalog')->json();
        $second = $this->getJson('/api/catalog')->json();

        $this->assertSame($first, $second);
    }
}
