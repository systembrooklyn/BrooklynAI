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

class RunWorkflowOverlapTest extends TestCase
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

    public function test_execute_returns_409_when_another_execution_is_running(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        ExecutionModel::create([
            'workflow_id' => $id,
            'user_id' => $user->id,
            'status' => ExecutionStatus::Running->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(409);
    }

    public function test_execute_allows_new_execution_when_previous_completed(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        ExecutionModel::create([
            'workflow_id' => $id,
            'user_id' => $user->id,
            'status' => ExecutionStatus::Completed->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);
    }

    public function test_execute_allows_new_execution_when_previous_failed(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        ExecutionModel::create([
            'workflow_id' => $id,
            'user_id' => $user->id,
            'status' => ExecutionStatus::Failed->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
        ]);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);
    }
}
