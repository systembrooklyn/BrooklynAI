<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class GmailAttachmentTriggerActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_trigger_activation_succeeds_when_drive_scope_is_present(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection($user, withDriveScope: true);
        $workflow = $this->workflow($user);
        $this->trigger($workflow, $connection, 'new_email_received_with_attachment');

        Sanctum::actingAs($user);

        $this->postJson("/api/workflows/{$workflow->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'status' => 'active',
        ]);
    }

    public function test_attachment_trigger_activation_is_rejected_when_drive_scope_is_missing(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection($user, withDriveScope: false);
        $workflow = $this->workflow($user);
        $this->trigger($workflow, $connection, 'new_email_received_with_attachment');

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/workflows/{$workflow->id}/activate");

        $response->assertStatus(409);
        $response->assertJsonPath('error', 'missing_scopes');
        $response->assertJsonPath('context.location', 'trigger');
        $response->assertJsonPath('context.missing_scopes', [
            'https://www.googleapis.com/auth/drive.file',
        ]);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'status' => 'draft',
        ]);
    }

    public function test_normal_trigger_activation_does_not_require_drive_scope(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection($user, withDriveScope: false);
        $workflow = $this->workflow($user);
        $this->trigger($workflow, $connection, 'new_email_received');

        Sanctum::actingAs($user);

        $this->postJson("/api/workflows/{$workflow->id}/activate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_missing_scopes_response_does_not_leak_credentials(): void
    {
        $user = User::factory()->create();
        $connection = $this->connection($user, withDriveScope: false);
        $workflow = $this->workflow($user);
        $this->trigger($workflow, $connection, 'new_email_received_with_attachment');

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/workflows/{$workflow->id}/activate");
        $body = $response->getContent();

        $this->assertStringNotContainsString('access_token', $body);
        $this->assertStringNotContainsString('refresh_token', $body);
        $this->assertStringNotContainsString('client_secret', $body);
        $this->assertStringNotContainsString('Authorization', $body);
    }

    private function connection(User $user, bool $withDriveScope): ConnectionModel
    {
        $scopes = [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.readonly',
        ];

        if ($withDriveScope) {
            $scopes[] = 'https://www.googleapis.com/auth/drive.file';
        }

        return ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'google-sub-' . uniqid('', true),
            'email' => 'user@example.com',
            'scopes' => $scopes,
            'status' => 'active',
        ]);
    }

    private function workflow(User $user): WorkflowModel
    {
        return WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Activation test workflow',
            'description' => null,
            'status' => 'draft',
        ]);
    }

    private function trigger(
        WorkflowModel $workflow,
        ConnectionModel $connection,
        string $triggerKey,
    ): WorkflowTriggerModel {
        return WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => $triggerKey,
            'connection_id' => $connection->id,
            'strategy' => 'poll',
            'config' => [],
            'interval_minutes' => null,
        ]);
    }
}
