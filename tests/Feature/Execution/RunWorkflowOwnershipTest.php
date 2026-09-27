<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowOwnershipTest extends TestCase
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

    public function test_execute_requires_authentication(): void
    {
        $this->postJson('/api/workflows/1/execute')->assertStatus(401);
    }

    public function test_execute_returns_404_for_nonexistent_workflow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/99999/execute')->assertStatus(404);
    }

    public function test_execute_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $id = $this->activeWorkflow($owner);

        Sanctum::actingAs($attacker);
        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(404);
    }
}
