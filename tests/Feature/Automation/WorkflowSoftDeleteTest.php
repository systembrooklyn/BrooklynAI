<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowSoftDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(User $user, string $name = 'Flow'): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => $name])->json('data.id');
    }

    public function test_delete_soft_deletes_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->deleteJson('/api/workflows/'.$id);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow deleted successfully');

        $this->assertDatabaseHas('workflows', ['id' => $id]);
        $this->assertSoftDeleted('workflows', ['id' => $id]);
    }

    public function test_deleted_workflow_is_hidden_from_default_list(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->deleteJson('/api/workflows/'.$id);

        $response = $this->getJson('/api/workflows');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_deleted_workflow_appears_in_trashed_list(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->deleteJson('/api/workflows/'.$id);

        $response = $this->getJson('/api/workflows?trashed=true');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $response->assertJsonPath('data.0.id', $id);
        $this->assertNotNull($response->json('data.0.deleted_at'));
    }

    public function test_deleted_workflow_show_returns_404(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->deleteJson('/api/workflows/'.$id);

        $this->getJson('/api/workflows/'.$id)->assertStatus(404);
    }

    public function test_restore_brings_workflow_back(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->deleteJson('/api/workflows/'.$id);

        $response = $this->postJson('/api/workflows/'.$id.'/restore');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow restored successfully');
        $response->assertJsonPath('data.id', $id);
        $response->assertJsonPath('data.deleted_at', null);

        $this->assertNotSoftDeleted('workflows', ['id' => $id]);

        // Should appear in default list now
        $list = $this->getJson('/api/workflows');
        $this->assertCount(1, $list->json('data'));
    }

    public function test_restore_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/workflows/99999/restore')->assertStatus(404);
    }

    public function test_restore_of_non_deleted_workflow_is_noop(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/restore');

        $response->assertStatus(200);
        $response->assertJsonPath('data.deleted_at', null);
        $this->assertNotSoftDeleted('workflows', ['id' => $id]);
    }

    public function test_delete_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->deleteJson('/api/workflows/99999')->assertStatus(404);
    }
}
