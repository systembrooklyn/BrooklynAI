<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowFailureTest extends TestCase
{
    use RefreshDatabase;

    private function draftWorkflow(User $user, int $stepCount = 1): int
    {
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        for ($i = 0; $i < $stepCount; $i++) {
            $this->postJson('/api/workflows/'.$id.'/steps', [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
                'config' => ['to' => 'r'.$i.'@example.com', 'subject' => 'S', 'body' => 'B'],
            ])->assertStatus(201);
        }

        return $id;
    }

    public function test_step_failure_marks_execution_failed(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user, 1);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw ActionInvocationFailed::forInvalidConfig('to');
        $this->app->instance(ActionInvoker::class, $fake);

        $response = $this->postJson('/api/workflows/'.$id.'/execute');

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'failed');
        $response->assertJsonPath('data.steps.0.status', 'failed');
    }

    public function test_step_failure_marks_remaining_steps_skipped(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user, 3);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw ActionInvocationFailed::forInvalidConfig('to');
        $this->app->instance(ActionInvoker::class, $fake);

        $response = $this->postJson('/api/workflows/'.$id.'/execute');
        $response->assertStatus(201);

        $response->assertJsonPath('data.steps.0.position', 1);
        $response->assertJsonPath('data.steps.0.status', 'failed');
        $response->assertJsonPath('data.steps.1.position', 2);
        $response->assertJsonPath('data.steps.1.status', 'skipped');
        $response->assertJsonPath('data.steps.2.position', 3);
        $response->assertJsonPath('data.steps.2.status', 'skipped');

        $execution = ExecutionModel::query()->where('workflow_id', $id)->firstOrFail();

        $this->assertDatabaseHas('execution_steps', [
            'execution_id' => $execution->id,
            'position' => 1,
            'status' => 'failed',
        ]);
        $this->assertDatabaseHas('execution_steps', [
            'execution_id' => $execution->id,
            'position' => 2,
            'status' => 'skipped',
        ]);
        $this->assertDatabaseHas('execution_steps', [
            'execution_id' => $execution->id,
            'position' => 3,
            'status' => 'skipped',
        ]);
    }

    public function test_workflow_remains_draft_after_failed_execution(): void
    {
        // Phase 1: renamed intent. The test now verifies that a DRAFT
        // workflow remains in draft status after a failed execution
        // (executions do not change workflow status). Uses send_email only.
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user, 1);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw ActionInvocationFailed::forInvalidConfig('to');
        $this->app->instance(ActionInvoker::class, $fake);

        $this->postJson('/api/workflows/'.$id.'/execute');

        $this->assertDatabaseHas('workflows', [
            'id' => $id,
            'status' => 'draft',
        ]);
    }

    public function test_step_failure_persists_truncated_error_message(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user, 1);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw new ActionInvocationFailed(str_repeat('x', 5000));
        $this->app->instance(ActionInvoker::class, $fake);

        $this->postJson('/api/workflows/'.$id.'/execute');

        $execution = ExecutionModel::query()->where('workflow_id', $id)->firstOrFail();
        $this->assertNotNull($execution->error_message);
        $this->assertLessThanOrEqual(2000, strlen($execution->error_message));
    }

    public function test_unexpected_exception_types_propagate_and_return_500(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user, 1);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw new \LogicException('unexpected');
        $this->app->instance(ActionInvoker::class, $fake);

        $this->withoutExceptionHandling();

        try {
            $this->postJson('/api/workflows/'.$id.'/execute');
            $this->fail('Expected LogicException to propagate.');
        } catch (\LogicException $e) {
            $this->assertSame('unexpected', $e->getMessage());
        }
    }
}
