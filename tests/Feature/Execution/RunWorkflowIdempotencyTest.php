<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function activeWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate');

        return $id;
    }

    public function test_execute_with_idempotency_key_returns_existing_execution_200(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $existing = ExecutionModel::create([
            'workflow_id' => $id,
            'user_id' => $user->id,
            'status' => ExecutionStatus::Completed->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
            'idempotency_key' => 'k-1',
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'k-1',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (int) $existing->id);
    }

    public function test_execute_with_different_idempotency_key_creates_new_execution(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        ExecutionModel::create([
            'workflow_id' => $id,
            'user_id' => $user->id,
            'status' => ExecutionStatus::Completed->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
            'idempotency_key' => 'k-1',
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'k-2',
        ]);

        $response->assertStatus(201);
    }

    public function test_execute_without_idempotency_key_creates_new_execution_each_call(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);
        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);
        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);

        $this->assertSame(
            3,
            ExecutionModel::query()->where('workflow_id', $id)->count(),
        );
    }
}
