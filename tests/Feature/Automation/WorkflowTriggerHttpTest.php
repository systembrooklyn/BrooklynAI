<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowTriggerHttpTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');
    }

    public function test_upsert_requires_authentication(): void
    {
        $this->putJson('/api/workflows/1/trigger', [])->assertStatus(401);
    }

    public function test_upsert_requires_integration_and_trigger_keys(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['integration_key', 'trigger_key']);
    }

    public function test_upsert_rejects_unknown_integration(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'unknown.integration',
            'trigger_key' => 'x',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['integration_key']);
    }

    public function test_upsert_rejects_unknown_trigger_for_known_integration(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'not_a_real_trigger',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trigger_key']);
    }

    public function test_upsert_rejects_non_owned_connection(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $connection = ConnectionModel::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'external_account_id' => 'sub-owner',
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        $id = $this->createWorkflow($attacker);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Connection not found');
    }

    public function test_upsert_rejects_invalid_template_syntax(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'config' => ['labels' => '{{ trigger. }}'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['config']]);
    }

    public function test_upsert_creates_trigger(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'interval_minutes' => 5,
            'config' => ['labels' => ['INBOX']],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow trigger saved successfully');
        $response->assertJsonPath('data.integration_key', 'google.gmail');
        $response->assertJsonPath('data.trigger_key', 'new_email_received');
        $response->assertJsonPath('data.strategy', 'poll');
        $response->assertJsonPath('data.interval_minutes', 5);
        $response->assertJsonPath('data.config.labels', ['INBOX']);
    }

    public function test_upsert_replaces_trigger_with_same_id(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $first = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'interval_minutes' => 5,
        ])->json('data');

        $second = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'interval_minutes' => 15,
        ])->json('data');

        $this->assertSame($first['id'], $second['id']);
        $this->assertSame(15, $second['interval_minutes']);
    }

    public function test_upsert_returns_404_for_nonexistent_workflow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/workflows/99999/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        $response->assertStatus(404);
    }

    public function test_upsert_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        $response->assertStatus(404);
    }

    public function test_delete_requires_authentication(): void
    {
        $this->deleteJson('/api/workflows/1/trigger')->assertStatus(401);
    }

    public function test_delete_removes_trigger(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        $response = $this->deleteJson('/api/workflows/'.$id.'/trigger');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow trigger removed successfully');

        $this->assertDatabaseMissing('workflow_triggers', ['workflow_id' => $id]);
    }

    public function test_delete_returns_404_when_no_trigger(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->deleteJson('/api/workflows/'.$id.'/trigger');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Workflow trigger not found');
    }

    public function test_delete_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($owner);
        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/workflows/'.$id.'/trigger')->assertStatus(404);
    }
}
