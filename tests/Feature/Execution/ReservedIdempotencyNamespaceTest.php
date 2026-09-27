<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservedIdempotencyNamespaceTest extends TestCase
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

    public function test_manual_idempotency_key_with_schedule_prefix_is_rejected(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'schedule:'.$id.':first',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_manual_idempotency_key_with_schedule_prefix_is_rejected_anywhere(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'schedule:anything',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_manual_idempotency_key_without_schedule_prefix_still_works(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'my-custom-key-1',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('executions', [
            'workflow_id' => $id,
            'idempotency_key' => 'my-custom-key-1',
        ]);
    }

    public function test_manual_idempotency_key_with_gmail_prefix_is_rejected(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'gmail:'.$id.':msg-1',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }

    public function test_manual_idempotency_key_with_gmail_prefix_any_suffix_is_rejected(): void
    {
        $user = User::factory()->create();
        $id = $this->activeWorkflow($user);

        $response = $this->postJson('/api/workflows/'.$id.'/execute', [
            'idempotency_key' => 'gmail:anything-goes',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['idempotency_key']);
    }
}
