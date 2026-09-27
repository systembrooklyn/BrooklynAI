<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowActivationCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private const SCOPE_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';

    private const SCOPE_SEND = 'https://www.googleapis.com/auth/gmail.send';

    private const SCOPE_COMPOSE = 'https://www.googleapis.com/auth/gmail.compose';

    private function connection(User $user, array $scopes): ConnectionModel
    {
        return ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'gmail-'.uniqid(),
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'scopes' => $scopes,
            'status' => 'active',
        ]);
    }

    private function workflow(User $user): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => 'Capability Flow'])->json('data.id');
    }

    private function upsertTrigger(int $id, string $integration, string $trigger, ?int $connectionId): void
    {
        $this->putJson('/api/workflows/'.$id.'/trigger', [
            'integration_key' => $integration,
            'trigger_key' => $trigger,
            'connection_id' => $connectionId,
        ])->assertStatus(200);
    }

    private function addStep(int $id, string $integration, string $action, ?int $connectionId): void
    {
        $this->postJson('/api/workflows/'.$id.'/steps', [
            'integration_key' => $integration,
            'action_key' => $action,
            'connection_id' => $connectionId,
        ])->assertStatus(201);
    }

    // --- A ---
    public function test_valid_gmail_trigger_activates(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');
    }

    // --- B ---
    public function test_gmail_trigger_missing_connection_id_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger($id, 'google.gmail', 'new_email_received', null);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'missing_connection');

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'draft']);
    }

    // --- C ---
    public function test_gmail_action_missing_connection_id_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        $this->addStep($id, 'google.gmail', 'send_email', null);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'missing_connection');

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'draft']);
    }

    // --- D ---
    public function test_connection_does_not_exist_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);

        // Use a real connection so the FK is satisfied at upsert time.
        $connection = $this->connection($user, [self::SCOPE_READONLY]);
        $this->upsertTrigger($id, 'google.gmail', 'new_email_received', $connection->id);

        // The schema's nullOnDelete prevents a dangling FK from existing in
        // the database, so the only way this state can occur in production is
        // a concurrent delete between upsert and activation. Simulate it by
        // swapping the repository before the controller dispatches.
        $this->app->bind(
            \App\Modules\Connections\Core\Repositories\ConnectionRepository::class,
            static fn () => new class implements \App\Modules\Connections\Core\Repositories\ConnectionRepository
            {
                public function findByUserAndExternalAccount(int $userId, string $provider, string $externalAccountId): ?\App\Modules\Connections\Core\Entities\Connection
                {
                    return null;
                }

                public function findForUser(int $userId, int $connectionId): ?\App\Modules\Connections\Core\Entities\Connection
                {
                    return null;
                }

                /** @return array<int, \App\Modules\Connections\Core\Entities\Connection> */
                public function listForUser(int $userId): array
                {
                    return [];
                }

                public function save(\App\Modules\Connections\Core\Entities\Connection $connection): \App\Modules\Connections\Core\Entities\Connection
                {
                    return $connection;
                }

                public function delete(\App\Modules\Connections\Core\Entities\Connection $connection): void {}
            }
        );

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'connection_not_found_or_not_owned');

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'draft']);
    }

    // --- E ---
    public function test_connection_owned_by_another_user_fails(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $foreign = $this->connection($owner, [self::SCOPE_READONLY]);

        $id = $this->workflow($attacker);

        // Insert the trigger directly with the foreign user's connection_id.
        // The FK passes because the connection exists; activation must reject it.
        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $foreign->id,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'connection_not_found_or_not_owned');

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'draft']);
    }

    // --- F ---
    public function test_missing_required_scope_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        // send_email requires gmail.send, but we give it gmail.readonly only.
        $this->addStep(
            $id,
            'google.gmail',
            'send_email',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'missing_scopes')
            ->assertJsonPath('context.missing_scopes.0', self::SCOPE_SEND);

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'draft']);
    }

    // --- G1 ---
    public function test_send_email_only_requires_send_scope(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        $this->addStep(
            $id,
            'google.gmail',
            'send_email',
            $this->connection($user, [self::SCOPE_SEND])->id
        );

        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(200);
    }

    // --- G2 ---
    public function test_new_email_received_only_requires_readonly_scope(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );

        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(200);
    }

    // --- G3 ---
    public function test_create_draft_only_requires_compose_scope(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        $this->addStep(
            $id,
            'google.gmail',
            'create_draft',
            $this->connection($user, [self::SCOPE_COMPOSE])->id
        );

        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(200);
    }

    // --- H ---
    public function test_different_connections_in_one_workflow_pass(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        $this->addStep(
            $id,
            'google.gmail',
            'send_email',
            $this->connection($user, [self::SCOPE_SEND])->id
        );

        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(200);
    }

    // --- I ---
    public function test_multiple_steps_where_one_fails_does_not_activate(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        $this->addStep(
            $id,
            'google.gmail',
            'send_email',
            $this->connection($user, [self::SCOPE_SEND])->id
        );
        $this->addStep($id, 'google.gmail', 'send_email', null);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'missing_connection');

        $this->assertDatabaseHas('workflows', ['id' => $id, 'status' => 'draft']);
    }

    // --- J ---
    public function test_capability_null_action_is_not_blocked(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );
        // google.calendar.create_event declares requiredScopes but capability:null
        $this->addStep($id, 'google.calendar', 'create_event', null);

        $this->postJson('/api/workflows/'.$id.'/activate')->assertStatus(200);
    }

    // --- Unknown integration on trigger ---
    public function test_unknown_integration_on_trigger_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);

        // Bypass the request-validation catalog check by writing the trigger row directly.
        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'unknown.integration',
            'trigger_key' => 'whatever',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_integration')
            ->assertJsonPath('context.integration_key', 'unknown.integration');
    }

    // --- Unknown trigger on known integration ---
    public function test_unknown_trigger_on_known_integration_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);

        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'not_a_real_trigger',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_trigger')
            ->assertJsonPath('context.trigger_key', 'not_a_real_trigger');
    }

    // --- Unknown integration on action ---
    public function test_unknown_integration_on_action_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );

        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel::create([
            'workflow_id' => $id,
            'position' => 1,
            'integration_key' => 'unknown.integration',
            'action_key' => 'whatever',
            'connection_id' => null,
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_integration')
            ->assertJsonPath('context.integration_key', 'unknown.integration');
    }

    // --- Unknown action on known integration ---
    public function test_unknown_action_on_known_integration_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);
        $this->upsertTrigger(
            $id,
            'google.gmail',
            'new_email_received',
            $this->connection($user, [self::SCOPE_READONLY])->id
        );

        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel::create([
            'workflow_id' => $id,
            'position' => 1,
            'integration_key' => 'google.gmail',
            'action_key' => 'not_a_real_action',
            'connection_id' => null,
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_action')
            ->assertJsonPath('context.action_key', 'not_a_real_action');
    }
}
