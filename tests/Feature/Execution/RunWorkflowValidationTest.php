<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowValidationTest extends TestCase
{
    use RefreshDatabase;

    private function draftWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');
    }

    /**
     * Phase 1: paused workflow created via direct model insert to avoid
     * needing an activatable trigger. Workflow status is the only thing
     * that matters for the "execute paused workflow returns 409" rule.
     */
    private function pausedWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Paused Flow',
            'status' => 'paused',
        ]);

        return (int) $workflow->id;
    }

    public function test_execute_allows_draft_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);
    }

    public function test_execute_returns_409_for_paused_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->pausedWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(409);
    }

    public function test_execute_validates_trigger_payload_must_be_array(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'trigger_payload' => 'not-an-array',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trigger_payload']);
    }

    public function test_execute_validates_idempotency_key_max_length(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => str_repeat('a', 200),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }
}
