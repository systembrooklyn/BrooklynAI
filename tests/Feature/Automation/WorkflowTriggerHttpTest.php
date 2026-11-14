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

    public function test_delete_requires_authentication(): void
    {
        $this->deleteJson('/api/workflows/1/trigger')->assertStatus(401);
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

    /**
     * Phase 1 negative test — new_email_received is disabled.
     */
    public function test_upsert_rejects_new_email_received_because_trigger_is_disabled(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trigger_key']);
    }

    /**
     * Phase 1 negative test — attachment trigger is disabled.
     */
    public function test_upsert_rejects_attachment_trigger_because_it_is_disabled(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received_with_attachment',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trigger_key']);
    }

    /**
     * Phase 1: template syntax validation relocated to the step endpoint,
     * since no valid trigger key exists to test trigger config validation.
     */
    public function test_upsert_rejects_invalid_template_syntax_on_step(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['body' => '{{ trigger. }}'],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors' => ['config']]);
    }

    public function test_delete_returns_404_when_no_trigger(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->deleteJson('/api/workflows/'.$id.'/trigger');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Workflow trigger not found');
    }

    /**
     * Phase 1: RECOVERED. The 404 contract comes from WorkflowNotFoundException
     * (findForUser fails) BEFORE TriggerNotFoundException. No trigger creation
     * is needed because the ownership check rejects the request first.
     */
    public function test_delete_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/workflows/'.$id.'/trigger')->assertStatus(404);
    }

    // -------------------------------------------------------------------
    // Phase 1 skips — each with specific technical reason
    // -------------------------------------------------------------------

    public function test_upsert_rejects_non_owned_connection(): void
    {
        $this->markTestSkipped(
            'Phase 1: no integration currently exposes a trigger; FormRequest rejects trigger_key with 422 before the connection ownership check runs. '
            .'Contract is covered by WorkflowStepHttpTest::test_store_returns_404_for_other_users_workflow which uses the same connections->findForUser() check inside AddWorkflowStepAction.'
        );
    }

    public function test_upsert_returns_404_for_nonexistent_workflow(): void
    {
        $this->markTestSkipped(
            'Phase 1: no valid trigger key exists; FormRequest rejects trigger_key with 422 before the workflow lookup can return 404.'
        );
    }

    public function test_upsert_returns_404_for_other_users_workflow(): void
    {
        $this->markTestSkipped(
            'Phase 1: no valid trigger key exists; FormRequest rejects trigger_key with 422 before the workflow ownership check can return 404.'
        );
    }

    public function test_upsert_creates_trigger(): void
    {
        $this->markTestSkipped(
            'Phase 1: new_email_received trigger disabled; no valid trigger key exists to create.'
        );
    }

    public function test_upsert_replaces_trigger_with_same_id(): void
    {
        $this->markTestSkipped(
            'Phase 1: new_email_received trigger disabled; no valid trigger key exists to upsert.'
        );
    }

    public function test_delete_removes_trigger(): void
    {
        $this->markTestSkipped(
            'Phase 1: new_email_received trigger disabled; no valid trigger key exists to create before delete.'
        );
    }
}
