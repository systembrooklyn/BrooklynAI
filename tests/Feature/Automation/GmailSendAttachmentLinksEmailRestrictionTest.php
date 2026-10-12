<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies that the production Gmail integration correctly declares
 * google.gmail.send_attachment_links_email as restricted to
 * google.gmail.new_email_received_with_attachment, and that the Batch 1
 * compatibility enforcement rejects incompatible pairings on every write
 * path plus at activation.
 *
 * This test class intentionally does NOT override IntegrationCatalog so it
 * exercises the real GmailIntegration definition exactly as it is bound in
 * production.
 */
final class GmailSendAttachmentLinksEmailRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private const CONNECTION_SCOPES = [
        'openid',
        'email',
        'profile',
        'https://www.googleapis.com/auth/gmail.readonly',
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/drive.file',
    ];

    public function test_action_is_accepted_with_attachment_trigger(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received_with_attachment',
            $connection->id,
        );

        $response = $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_attachment_links_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Attachments',
                    'attachments' => '{{ trigger.attachments }}',
                ],
            ],
        );

        $response->assertStatus(201);
        $response->assertJsonPath('data.action_key', 'send_attachment_links_email');
    }

    public function test_action_is_rejected_with_normal_trigger_on_step_add(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received',
            $connection->id,
        );

        $response = $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_attachment_links_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Attachments',
                    'attachments' => '{{ trigger.attachments }}',
                ],
            ],
        );

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);
        $response->assertJsonPath('context.action_key', 'google.gmail.send_attachment_links_email');
        $response->assertJsonPath('context.trigger_key', 'google.gmail.new_email_received');
        $response->assertJsonPath(
            'context.allowed_trigger_keys',
            ['google.gmail.new_email_received_with_attachment'],
        );
    }

    public function test_existing_send_email_remains_unrestricted_on_normal_trigger(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received',
            $connection->id,
        );

        $response = $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Subject',
                    'body' => '<p>Body</p>',
                ],
            ],
        );

        $response->assertStatus(201);
    }

    public function test_existing_send_email_remains_unrestricted_on_attachment_trigger(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received_with_attachment',
            $connection->id,
        );

        $response = $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Subject',
                    'body' => '<p>Body</p>',
                ],
            ],
        );

        $response->assertStatus(201);
    }

    public function test_step_update_is_rejected_when_switching_to_restricted_action(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received',
            $connection->id,
        );

        // Seed an existing unrestricted step directly.
        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 1,
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'connection_id' => $connection->id,
            'config' => [
                'to' => 'recipient@example.com',
                'subject' => 'Subject',
                'body' => '<p>Body</p>',
            ],
        ]);

        $response = $this->actingAs($user)->putJson(
            "/api/workflows/{$workflow->id}/steps/1",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_attachment_links_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Attachments',
                    'attachments' => '{{ trigger.attachments }}',
                ],
            ],
        );

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);
    }

    public function test_trigger_replacement_is_rejected_when_existing_step_uses_restricted_action(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received_with_attachment',
            $connection->id,
        );

        $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_attachment_links_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Attachments',
                    'attachments' => '{{ trigger.attachments }}',
                ],
            ],
        )->assertStatus(201);

        $response = $this->actingAs($user)->putJson(
            "/api/workflows/{$workflow->id}/trigger",
            [
                'integration_key' => 'google.gmail',
                'trigger_key' => 'new_email_received',
                'connection_id' => $connection->id,
            ],
        );

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);

        // The original trigger must remain in place.
        $this->assertDatabaseHas('workflow_triggers', [
            'workflow_id' => $workflow->id,
            'trigger_key' => 'new_email_received_with_attachment',
        ]);
    }

    public function test_activation_succeeds_with_compatible_pairing(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received_with_attachment',
            $connection->id,
        );

        $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_attachment_links_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Attachments',
                    'attachments' => '{{ trigger.attachments }}',
                ],
            ],
        )->assertStatus(201);

        $this->actingAs($user)
            ->postJson("/api/workflows/{$workflow->id}/activate")
            ->assertStatus(200);
    }

    public function test_activation_rejects_incompatible_pairing_even_when_write_paths_are_bypassed(): void
    {
        [$user, $workflow, $connection] = $this->fixture();

        $this->upsertTrigger(
            $user,
            $workflow->id,
            'new_email_received_with_attachment',
            $connection->id,
        );

        $this->actingAs($user)->postJson(
            "/api/workflows/{$workflow->id}/steps",
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_attachment_links_email',
                'connection_id' => $connection->id,
                'config' => [
                    'to' => 'recipient@example.com',
                    'subject' => 'Attachments',
                    'attachments' => '{{ trigger.attachments }}',
                ],
            ],
        )->assertStatus(201);

        // Simulate a direct DB edit that the API write paths would have blocked.
        WorkflowTriggerModel::where('workflow_id', $workflow->id)
            ->update(['trigger_key' => 'new_email_received']);

        $response = $this->actingAs($user)
            ->postJson("/api/workflows/{$workflow->id}/activate");

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);
    }

    // ---------------------------------------------------------------------

    /**
     * @return array{0: User, 1: WorkflowModel, 2: ConnectionModel}
     */
    private function fixture(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Attachment workflow',
            'description' => null,
            'status' => 'draft',
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'google-sub-'.uniqid('', true),
            'email' => 'owner@example.com',
            'display_name' => 'Owner',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'token_expires_at' => null,
            'scopes' => self::CONNECTION_SCOPES,
            'status' => 'active',
        ]);

        return [$user, $workflow, $connection];
    }

    private function upsertTrigger(
        User $user,
        int $workflowId,
        string $triggerKey,
        int $connectionId,
    ): void {
        $this->actingAs($user)->putJson(
            "/api/workflows/{$workflowId}/trigger",
            [
                'integration_key' => 'google.gmail',
                'trigger_key' => $triggerKey,
                'connection_id' => $connectionId,
            ],
        )->assertStatus(200);
    }
}
