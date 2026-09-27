<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionStepModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowExecutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_execution_requires_authentication(): void
    {
        $this->getJson('/api/executions/1')->assertStatus(401);
    }

    public function test_show_execution_includes_steps_in_position_order(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $execution = ExecutionModel::create([
            'workflow_id' => $workflowId,
            'user_id' => $user->id,
            'status' => ExecutionStatus::Completed->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
        ]);

        ExecutionStepModel::create([
            'execution_id' => $execution->id,
            'position' => 1,
            'step_snapshot' => [],
            'status' => ExecutionStepStatus::Completed->value,
        ]);
        ExecutionStepModel::create([
            'execution_id' => $execution->id,
            'position' => 2,
            'step_snapshot' => [],
            'status' => ExecutionStepStatus::Skipped->value,
        ]);

        $response = $this->getJson('/api/executions/'.$execution->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', (int) $execution->id);
        $response->assertJsonPath('data.steps.0.position', 1);
        $response->assertJsonPath('data.steps.1.position', 2);
    }

    public function test_show_execution_returns_404_for_other_users_execution(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        Sanctum::actingAs($owner);
        $workflowId = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $execution = ExecutionModel::create([
            'workflow_id' => $workflowId,
            'user_id' => $owner->id,
            'status' => ExecutionStatus::Completed->value,
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
        ]);

        Sanctum::actingAs($attacker);

        $this->getJson('/api/executions/'.$execution->id)->assertStatus(404);
    }

    public function test_show_execution_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/executions/99999')->assertStatus(404);
    }
}
