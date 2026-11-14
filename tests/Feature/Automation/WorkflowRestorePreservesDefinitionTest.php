<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowRestorePreservesDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_preserves_trigger_and_steps(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        // Phase 1: Gmail triggers are disabled via upsert API validation.
        // Insert the trigger row directly via model to verify that
        // restore preserves the trigger definition (regardless of which
        // trigger key is stored). The persisted row is what restore must
        // preserve; the trigger's activity is enforced elsewhere.
        WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        // Two send_email steps: verifies that restore preserves all steps
        // regardless of which actions they reference. Only send_email is
        // active in Phase 1, so we use it for both.
        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(201);

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(201);

        $this->deleteJson('/api/workflows/'.$id)->assertStatus(200);

        // While trashed, trigger and steps must still exist in DB.
        $this->assertDatabaseHas('workflow_triggers', ['workflow_id' => $id]);
        $this->assertDatabaseHas('workflow_steps', ['workflow_id' => $id, 'position' => 1]);
        $this->assertDatabaseHas('workflow_steps', ['workflow_id' => $id, 'position' => 2]);

        $this->postJson('/api/workflows/'.$id.'/restore')->assertStatus(200);

        $response = $this->getJson('/api/workflows/'.$id);

        $response->assertJsonPath('data.trigger.integration_key', 'google.gmail');
        $response->assertJsonCount(2, 'data.steps');
        $response->assertJsonPath('data.steps.0.position', 1);
        $response->assertJsonPath('data.steps.1.position', 2);
    }
}
