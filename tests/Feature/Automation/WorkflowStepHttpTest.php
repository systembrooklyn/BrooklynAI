<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowStepHttpTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/workflows/1/steps', [])->assertStatus(401);
    }

    public function test_store_requires_keys(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/steps', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['integration_key', 'action_key']);
    }

    public function test_store_rejects_unknown_action(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'unknown_action',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['action_key']);
    }

    public function test_store_rejects_invalid_template_syntax(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['subject' => '{{ trigger. }}'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['errors' => ['config']]);
    }

    public function test_store_assigns_position_1_for_empty_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['to' => 'x@example.com'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.position', 1);
    }

    public function test_store_assigns_max_plus_one(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(201);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(201);

        $response = $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        $response->assertJsonPath('data.position', 3);
    }

    public function test_store_uses_max_after_gap(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertJsonPath('data.position', 1);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertJsonPath('data.position', 2);

        // Delete step at position 1
        $this->deleteJson('/api/workflows/'.$id.'/steps/1')->assertStatus(200);

        // Now steps: [2]. New step should be position 3 (max+1), not 2.
        $response = $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        $response->assertJsonPath('data.position', 3);
    }

    public function test_update_requires_authentication(): void
    {
        $this->putJson('/api/workflows/1/steps/1', [])->assertStatus(401);
    }

    public function test_update_replaces_step_in_place(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        $response = $this->putJson('/api/workflows/'.$id.'/steps/1', [
            'integration_key' => 'google.sheets',
            'action_key' => 'append_row_by_headers',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.position', 1);
        $response->assertJsonPath('data.integration_key', 'google.sheets');
        $response->assertJsonPath('data.action_key', 'append_row_by_headers');
    }

    public function test_update_returns_404_for_missing_step(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/steps/9', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Workflow step not found');
    }

    public function test_delete_removes_step(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        $response = $this->deleteJson('/api/workflows/'.$id.'/steps/1');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow step removed successfully');

        $this->assertDatabaseMissing('workflow_steps', ['workflow_id' => $id, 'position' => 1]);
    }

    public function test_delete_does_not_reindex_remaining_positions(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/workflows/'.$id.'/steps', [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
            ]);
        }

        $this->deleteJson('/api/workflows/'.$id.'/steps/2')->assertStatus(200);

        $this->assertDatabaseHas('workflow_steps', ['workflow_id' => $id, 'position' => 1]);
        $this->assertDatabaseMissing('workflow_steps', ['workflow_id' => $id, 'position' => 2]);
        $this->assertDatabaseHas('workflow_steps', ['workflow_id' => $id, 'position' => 3]);
    }

    public function test_store_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(404);
    }

    public function test_update_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($owner);
        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        Sanctum::actingAs($attacker);
        $this->putJson('/api/workflows/'.$id.'/steps/1', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(404);
    }

    public function test_delete_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($owner);
        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);

        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/workflows/'.$id.'/steps/1')->assertStatus(404);
    }
}
