<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunWorkflowValidationTest extends TestCase
{
    use RefreshDatabase;

    private function draftWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ]);

        return $id;
    }

    private function pausedWorkflow(User $user): int
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

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate');
        $this->postJson('/api/workflows/'.$id.'/pause');

        return $id;
    }

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

    public function test_execute_allows_draft_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->draftWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(201);
    }

    public function test_execute_returns_409_for_paused_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->pausedWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->postJson('/api/workflows/'.$id.'/execute')->assertStatus(409);
    }

    public function test_execute_validates_trigger_payload_must_be_array(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'trigger_payload' => 'not-an-array',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['trigger_payload']);
    }

    public function test_execute_validates_idempotency_key_max_length(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => str_repeat('a', 200),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }
}
