<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(User $user, string $name = 'Owned'): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => $name])->json('data.id');
    }

    public function test_show_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);
        $this->getJson('/api/workflows/'.$id)->assertStatus(404);
    }

    public function test_update_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);
        $this->putJson('/api/workflows/'.$id, ['name' => 'Hacked'])->assertStatus(404);

        // Verify nothing changed
        Sanctum::actingAs($owner);
        $this->getJson('/api/workflows/'.$id)->assertJsonPath('data.name', 'Owned');
    }

    public function test_delete_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);
        $this->deleteJson('/api/workflows/'.$id)->assertStatus(404);

        $this->assertNotSoftDeleted('workflows', ['id' => $id]);
    }

    public function test_restore_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($owner);
        $this->deleteJson('/api/workflows/'.$id);

        Sanctum::actingAs($attacker);
        $this->postJson('/api/workflows/'.$id.'/restore')->assertStatus(404);

        $this->assertSoftDeleted('workflows', ['id' => $id]);
    }

    public function test_activate_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($attacker);
        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(404);

        Sanctum::actingAs($owner);
        $this->getJson('/api/workflows/'.$id)->assertJsonPath('data.status', 'draft');
    }

    public function test_pause_returns_404_for_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $id = $this->createWorkflow($owner);

        Sanctum::actingAs($owner);
        $this->postJson('/api/workflows/'.$id.'/activate');

        Sanctum::actingAs($attacker);
        $this->postJson('/api/workflows/'.$id.'/pause')->assertStatus(404);
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/workflows')->assertStatus(401);
        $this->postJson('/api/workflows', ['name' => 'X'])->assertStatus(401);
        $this->getJson('/api/workflows/1')->assertStatus(401);
        $this->putJson('/api/workflows/1', ['name' => 'X'])->assertStatus(401);
        $this->deleteJson('/api/workflows/1')->assertStatus(401);
        $this->postJson('/api/workflows/1/restore')->assertStatus(401);
        $this->postJson('/api/workflows/1/activate')->assertStatus(401);
        $this->postJson('/api/workflows/1/pause')->assertStatus(401);
    }
}
