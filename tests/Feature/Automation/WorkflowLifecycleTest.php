<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(User $user, string $name = 'Flow'): int
    {
        Sanctum::actingAs($user);

        $connection = \App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'gmail-'.uniqid(),
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'scopes' => ['https://www.googleapis.com/auth/gmail.readonly'],
            'status' => 'active',
        ]);

        $id = (int) $this->postJson('/api/workflows', ['name' => $name])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
        ])->assertStatus(200);

        return $id;
    }

    public function test_draft_can_be_activated(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/activate');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow activated successfully');
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_active_can_be_paused(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->postJson('/api/workflows/'.$id.'/activate');

        $response = $this->postJson('/api/workflows/'.$id.'/pause');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Workflow paused successfully');
        $response->assertJsonPath('data.status', 'paused');
    }

    public function test_paused_can_be_reactivated(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->postJson('/api/workflows/'.$id.'/activate');
        $this->postJson('/api/workflows/'.$id.'/pause');

        $response = $this->postJson('/api/workflows/'.$id.'/activate');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');
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
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->postJson('/api/workflows/'.$id.'/activate');

        $response = $this->postJson('/api/workflows/'.$id.'/activate');

        $response->assertStatus(409);
    }

    public function test_paused_cannot_be_paused_again(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->postJson('/api/workflows/'.$id.'/activate');
        $this->postJson('/api/workflows/'.$id.'/pause');

        $response = $this->postJson('/api/workflows/'.$id.'/pause');

        $response->assertStatus(409);
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
