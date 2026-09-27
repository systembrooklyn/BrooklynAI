<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function activeWorkflow(User $user, array $steps = []): int
    {
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'SnapshotFlow'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        foreach ($steps as $step) {
            $this->postJson('/api/workflows/'.$id.'/steps', $step);
        }

        $this->postJson('/api/workflows/'.$id.'/activate');

        return $id;
    }

    public function test_workflow_snapshot_contains_expected_keys(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute');

        $execution = ExecutionModel::query()->where('workflow_id', $id)->firstOrFail();
        $snapshot = $execution->workflow_snapshot;

        $this->assertIsArray($snapshot);
        $this->assertArrayHasKey('workflow', $snapshot);
        $this->assertArrayHasKey('trigger', $snapshot);
        $this->assertArrayHasKey('steps', $snapshot);

        $this->assertSame(['id', 'name', 'description'], array_keys($snapshot['workflow']));
        $this->assertSame(
            ['position', 'integration_key', 'action_key', 'connection_id', 'config'],
            array_keys($snapshot['steps'][0]),
        );

        $this->assertSame(
            ['integration_key', 'trigger_key', 'connection_id', 'strategy', 'config', 'interval_minutes'],
            array_keys($snapshot['trigger']),
        );
    }

    public function test_workflow_snapshot_uses_definition_at_execution_time(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user, [
            ['integration_key' => 'google.gmail', 'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B']],
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute');

        // Mutate the workflow after execution.
        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['to' => 'c@example.com', 'subject' => 'S', 'body' => 'B'],
        ]);

        $execution = ExecutionModel::query()->where('workflow_id', $id)->firstOrFail();

        // Snapshot still reflects one step (the original).
        $this->assertCount(1, $execution->workflow_snapshot['steps']);
    }
}
