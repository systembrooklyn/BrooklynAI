<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowLifecycleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Creates a draft workflow without any trigger. Used by tests that
     * do not require activation. No Gmail trigger needed.
     */
    private function createWorkflow(User $user, string $name = 'Flow'): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => $name])->json('data.id');
    }

    public function test_draft_can_be_activated(): void
    {
        $this->markTestSkipped('Phase 1: no Gmail trigger is available to activate; trigger needed for activation.');
    }

    public function test_active_can_be_paused(): void
    {
        $this->markTestSkipped('Phase 1: activation requires a trigger; Gmail triggers disabled.');
    }

    public function test_paused_can_be_reactivated(): void
    {
        $this->markTestSkipped('Phase 1: activation requires a trigger; Gmail triggers disabled.');
    }

    public function test_draft_cannot_be_paused(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/pause');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Invalid workflow state transition');
    }

    public function test_active_cannot_be_activated_again(): void
    {
        $this->markTestSkipped('Phase 1: activation requires a trigger; Gmail triggers disabled.');
    }

    public function test_paused_cannot_be_paused_again(): void
    {
        $this->markTestSkipped('Phase 1: activation requires a trigger; Gmail triggers disabled.');
    }

    public function test_activate_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workflows/99999/activate');

        $response->assertStatus(404);
    }

    public function test_pause_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workflows/99999/pause');

        $response->assertStatus(404);
    }
}
