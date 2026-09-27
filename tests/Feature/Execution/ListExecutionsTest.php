<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListExecutionsTest extends TestCase
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

    public function test_list_executions_requires_authentication(): void
    {
        $this->getJson('/api/workflows/1/executions')->assertStatus(401);
    }

    public function test_list_executions_returns_empty_array_for_new_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $response = $this->getJson('/api/workflows/'.$id.'/executions');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_list_executions_respects_limit_parameter(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        foreach (range(1, 5) as $i) {
            ExecutionModel::create([
                'workflow_id' => $id,
                'user_id' => $user->id,
                'status' => ExecutionStatus::Completed->value,
                'trigger_source' => 'manual',
                'workflow_snapshot' => [],
            ]);
        }

        $response = $this->getJson('/api/workflows/'.$id.'/executions?limit=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_executions_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $id = $this->activeWorkflow($owner);

        Sanctum::actingAs($attacker);

        $this->getJson('/api/workflows/'.$id.'/executions')->assertStatus(404);
    }
}
