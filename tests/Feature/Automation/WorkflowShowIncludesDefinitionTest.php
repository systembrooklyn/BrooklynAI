<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowShowIncludesDefinitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_workflow_has_null_trigger_and_empty_steps(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $response = $this->getJson('/api/workflows/'.$id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.trigger', null);
        $response->assertJsonPath('data.steps', []);
    }

    public function test_show_returns_trigger_after_upsert(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'interval_minutes' => 5,
        ]);

        $response = $this->getJson('/api/workflows/'.$id);

        $response->assertJsonPath('data.trigger.integration_key', 'google.gmail');
        $response->assertJsonPath('data.trigger.trigger_key', 'new_email_received');
        $response->assertJsonPath('data.trigger.interval_minutes', 5);
        $response->assertJsonPath('data.trigger.strategy', 'poll');
    }

    public function test_show_returns_steps_after_creation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ]);
        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'create_draft',
        ]);

        $response = $this->getJson('/api/workflows/'.$id);

        $response->assertJsonCount(2, 'data.steps');
        $response->assertJsonPath('data.steps.0.position', 1);
        $response->assertJsonPath('data.steps.0.action_key', 'send_email');
        $response->assertJsonPath('data.steps.1.position', 2);
        $response->assertJsonPath('data.steps.1.action_key', 'create_draft');
    }
}
