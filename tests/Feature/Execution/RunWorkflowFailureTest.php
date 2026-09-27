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

    private function activeWorkflow(User $user, array $steps): int
    {
        Sanctum::actingAs($user);

        $connection = \App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'gmail-'.uniqid(),
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'scopes' => [
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/gmail.send',
            ],
            'status' => 'active',
        ]);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
        ]);

        foreach ($steps as $step) {
            if (! array_key_exists('connection_id', $step)) {
                $step['connection_id'] = $connection->id;
            }
            $this->postJson('/api/workflows/'.$id.'/steps', $step)->assertStatus(201);
        }

        $this->postJson('/api/workflows/'.$id.'/activate');

        return $id;
    }

    public function test_step_failure_marks_execution_failed(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

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
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'b@example.com', 'subject' => 'S', 'body' => 'B']],
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'c@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

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

    public function test_workflow_remains_active_after_failed_execution(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw ActionInvocationFailed::forInvalidConfig('to');
        $this->app->instance(ActionInvoker::class, $fake);

        $this->postJson('/api/workflows/'.$id.'/execute');

        $this->assertDatabaseHas('workflows', [
            'id' => $id,
            'status' => 'active',
        ]);
    }

    public function test_step_failure_persists_truncated_error_message(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

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
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

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
