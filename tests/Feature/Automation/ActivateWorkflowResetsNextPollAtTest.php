<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivateWorkflowResetsNextPollAtTest extends TestCase
{
    use RefreshDatabase;

    private const SCOPE_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';

    public function test_activation_resets_next_poll_at_to_null(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'gmail-'.uniqid(),
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'scopes' => [self::SCOPE_READONLY],
            'status' => 'active',
        ]);

        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'wf', 'status' => 'paused',
        ]);

        $trigger = WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
            'strategy' => 'poll',
            'config' => ['label_id' => 'INBOX'],
            'interval_minutes' => 5,
            'poll_cursor' => time() - 60,
            'next_poll_at' => (new \DateTimeImmutable)->modify('+30 minutes'),
        ]);

        $this->postJson('/api/workflows/'.$workflow->id.'/activate')->assertStatus(200);

        $trigger->refresh();
        $this->assertNull($trigger->next_poll_at);
    }
}
