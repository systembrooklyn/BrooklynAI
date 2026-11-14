<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 1: tests run against DRAFT workflows with a send_email step.
     * Draft workflows are executable per RunWorkflowAction, so no active
     * trigger is required. Only the send_email action is exercised.
     */
    private function draftWorkflowWithSendEmailStep(User $user): int
    {
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B'],
        ])->assertStatus(201);

        return $id;
    }

    public function test_execute_workflow_with_no_steps_succeeds(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $fake = new FakeActionInvoker;
        $this->app->instance(ActionInvoker::class, $fake);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'trigger_payload' => [],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.steps', []);
        $this->assertCount(0, $fake->invocations);
    }

    public function test_execute_workflow_with_one_step_succeeds(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflowWithSendEmailStep($user);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => ['sent' => true, 'message_id' => 'm-1'];
        $this->app->instance(ActionInvoker::class, $fake);

        $response = $this->postJson('/api/workflows/'.$id.'/execute');

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'completed');
        $response->assertJsonPath('data.steps.0.status', 'completed');
        $response->assertJsonPath('data.steps.0.output.message_id', 'm-1');

        $this->assertCount(1, $fake->invocations);
        $this->assertSame('google.gmail', $fake->invocations[0]['integration_key']);
        $this->assertSame('send_email', $fake->invocations[0]['action_key']);
    }

    public function test_execute_persists_execution_and_step_rows(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflowWithSendEmailStep($user);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => ['sent' => true, 'message_id' => 'm-2'];
        $this->app->instance(ActionInvoker::class, $fake);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $id,
            'status' => 'completed',
            'trigger_source' => 'manual',
        ]);

        $this->assertDatabaseHas('execution_steps', [
            'position' => 1,
            'status' => 'completed',
        ]);
    }
}
