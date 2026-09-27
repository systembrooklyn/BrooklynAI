<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowActivationRequiresTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_activate_without_trigger_returns_409(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $id = (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');

        $response = $this->postJson('/api/workflows/'.$id.'/activate');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Workflow cannot be activated without a trigger');
    }

    public function test_activate_after_setting_trigger_succeeds(): void
    {
        $user = User::factory()->create();
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
        ])->assertStatus(200);

        $response = $this->postJson('/api/workflows/'.$id.'/activate');

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'active');
    }
}
