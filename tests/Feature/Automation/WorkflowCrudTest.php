<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_requires_authentication(): void
    {
        $this->postJson('/api/workflows', ['name' => 'X'])->assertStatus(401);
    }

    public function test_create_requires_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workflows', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_create_returns_201_with_draft_workflow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/workflows', [
            'name' => 'My Flow',
            'description' => 'First flow',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Workflow created successfully');
        $response->assertJsonPath('data.name', 'My Flow');
        $response->assertJsonPath('data.description', 'First flow');
        $response->assertJsonPath('data.status', 'draft');
        $response->assertJsonPath('data.deleted_at', null);
        $response->assertJsonStructure(['data' => ['id', 'name', 'description', 'status', 'created_at', 'updated_at', 'deleted_at']]);
    }

    public function test_show_returns_workflow(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/workflows', ['name' => 'A'])->json('data');
        $response = $this->getJson('/api/workflows/'.$created['id']);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow retrieved successfully');
        $response->assertJsonPath('data.id', $created['id']);
        $response->assertJsonPath('data.name', 'A');
    }

    public function test_show_returns_404_for_nonexistent(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/workflows/99999');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Workflow not found');
    }

    public function test_update_changes_name_and_description(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/workflows', ['name' => 'Old'])->json('data');
        $response = $this->putJson('/api/workflows/'.$created['id'], [
            'name' => 'New',
            'description' => 'Updated',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow updated successfully');
        $response->assertJsonPath('data.name', 'New');
        $response->assertJsonPath('data.description', 'Updated');
    }

    public function test_update_requires_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/workflows', ['name' => 'Old'])->json('data');
        $response = $this->putJson('/api/workflows/'.$created['id'], []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['name']);
    }

    public function test_list_returns_only_own_workflows(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/workflows', ['name' => 'Mine 1']);
        $this->postJson('/api/workflows', ['name' => 'Mine 2']);

        Sanctum::actingAs($other);
        $this->postJson('/api/workflows', ['name' => 'Theirs']);

        Sanctum::actingAs($owner);
        $response = $this->getJson('/api/workflows');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflows retrieved successfully');
        $this->assertCount(2, $response->json('data'));
    }

    public function test_list_returns_empty_when_no_workflows(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/workflows');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }
}
