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

    private function activeWorkflow(User $user, array $steps = []): int
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
        ])->assertStatus(200);

        foreach ($steps as $step) {
            // Step config may or may not have a connection_id; add if missing.
            if (! array_key_exists('connection_id', $step)) {
                $step['connection_id'] = $connection->id;
            }
            $this->postJson('/api/workflows/'.$id.'/steps', $step)->assertStatus(201);
        }

        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(200);

        return $id;
    }

    public function test_execute_workflow_with_no_steps_succeeds(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

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
        $id = $this->activeWorkflow($user, [
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B'],
            ],
        ]);

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
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

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
