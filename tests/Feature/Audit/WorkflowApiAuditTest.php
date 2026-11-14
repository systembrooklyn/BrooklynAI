<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleAnalyticsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleCalendarIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDocsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDriveIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleSheetsIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\Support\Integrations\TestRestrictedIntegration;
use Tests\TestCase;

class WorkflowApiAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Phase 1: Gmail triggers are disabled (GmailIntegration declares zero
     * triggers). Register TestRestrictedIntegration on top of the standard
     * integrations so tests that exercise generic trigger behavior have at
     * least one valid trigger key. Gmail's own triggers remain absent, so
     * negative tests using 'new_email_received' still behave correctly.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(IntegrationCatalog::class, function () {
            return new IntegrationCatalog([
                new GmailIntegration,
                new GoogleCalendarIntegration,
                new GoogleSheetsIntegration,
                new GoogleDriveIntegration,
                new GoogleDocsIntegration,
                new GoogleAnalyticsIntegration,
                new TestRestrictedIntegration,
            ]);
        });
    }

    /**
     * Phase 1: default connection scopes are Gmail send-only, matching the
     * single supported Gmail action (send_email).
     */
    private function userWithConnection(?array $scopes = null): array
    {
        $scopes ??= ['https://www.googleapis.com/auth/gmail.send'];

        $user = User::factory()->create();

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'audit-'.uniqid(),
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'email' => $user->email,
            'scopes' => $scopes,
            'status' => 'active',
        ]);

        return [$user, $connection];
    }

    /**
     * @param  array<int, array{workflow: \App\Modules\Automation\Core\Entities\Workflow, trigger: mixed}>  $pairs
     * @return array<int, int>
     */
    private function pluckWorkflowIds(array $pairs): array
    {
        return array_map(static fn ($p) => (int) $p['workflow']->id, $pairs);
    }

    // -------------------------------------------------------------------
    // 1. Authenticated user endpoint
    // -------------------------------------------------------------------

    public function test_user_endpoint_returns_has_bot_access_as_boolean_not_integer(): void
    {
        $user = User::factory()->create(['has_bot_access' => true]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);

        $this->assertIsBool(
            $response->json('data.has_bot_access'),
            'GET /api/user must serialize has_bot_access as a JSON boolean, not an integer.',
        );
    }

    // -------------------------------------------------------------------
    // 2. Catalog contract — empty config shape
    // -------------------------------------------------------------------

    public function test_catalog_empty_action_config_serializes_as_json_object(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/catalog');
        $response->assertStatus(200);

        $raw = $response->getContent();

        $needle = '"action_key":"create_event"';
        $pos = strpos($raw, $needle);
        $this->assertNotFalse($pos);

        $window = substr($raw, $pos, 250);

        $this->assertStringContainsString('"config":{}', $window);
        $this->assertStringNotContainsString('"config":[]', $window);
    }

    public function test_trigger_config_serializes_as_json_object_when_empty(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'Trigger config shape'])
            ->json('data.id');

        // Uses TestRestrictedIntegration trigger key so upsert succeeds.
        $emptyResponse = $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
            'config' => [],
        ]);
        $emptyResponse->assertStatus(200);

        $emptyRaw = $emptyResponse->getContent();
        $this->assertStringContainsString('"config":{}', $emptyRaw);
        $this->assertStringNotContainsString('"config":[]', $emptyRaw);

        $populatedResponse = $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
            'config' => ['label_id' => 'INBOX'],
        ]);
        $populatedResponse->assertStatus(200);

        $populatedRaw = $populatedResponse->getContent();
        $this->assertStringContainsString('"config":{"label_id":"INBOX"}', $populatedRaw);
    }

    // -------------------------------------------------------------------
    // 3. Full workflow lifecycle through HTTP
    // -------------------------------------------------------------------

    public function test_full_workflow_lifecycle_through_http(): void
    {
        [$user, $connection] = $this->userWithConnection();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/workflows', ['name' => 'Audit lifecycle']);
        $create->assertStatus(201);
        $workflowId = $create->json('data.id');
        $this->assertIsInt($workflowId);

        $this->assertDatabaseHas('workflows', [
            'id' => $workflowId,
            'user_id' => $user->id,
            'status' => 'draft',
        ]);

        $show = $this->getJson('/api/workflows/'.$workflowId);
        $show->assertStatus(200);
        $this->assertSame('draft', $show->json('data.status'));
        $this->assertNull($show->json('data.trigger'));
        $this->assertSame([], $show->json('data.steps'));

        $update = $this->putJson('/api/workflows/'.$workflowId, [
            'name' => 'Audit lifecycle (renamed)',
            'description' => 'updated',
        ]);
        $update->assertStatus(200);
        $this->assertDatabaseHas('workflows', [
            'id' => $workflowId,
            'name' => 'Audit lifecycle (renamed)',
            'description' => 'updated',
        ]);

        // Uses TestRestrictedIntegration trigger key (Gmail triggers disabled).
        $trigger = $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
            'interval_minutes' => 5,
            'config' => [],
        ]);
        $trigger->assertStatus(200);
        $this->assertDatabaseHas('workflow_triggers', [
            'workflow_id' => $workflowId,
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
            'interval_minutes' => 5,
        ]);

        $step = $this->postJson('/api/workflows/'.$workflowId.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'connection_id' => $connection->id,
            'config' => [
                'to' => 'recipient@example.com',
                'subject' => 'Audit',
                'body' => 'Audit body',
            ],
        ]);
        $step->assertStatus(201);
        $this->assertSame(1, $step->json('data.position'));

        $activate = $this->postJson('/api/workflows/'.$workflowId.'/activate');
        $activate->assertStatus(200);
        $this->assertDatabaseHas('workflows', ['id' => $workflowId, 'status' => 'active']);

        $pause = $this->postJson('/api/workflows/'.$workflowId.'/pause');
        $pause->assertStatus(200);
        $this->assertDatabaseHas('workflows', ['id' => $workflowId, 'status' => 'paused']);

        $this->deleteJson('/api/workflows/'.$workflowId)->assertStatus(200);
        $this->assertSoftDeleted('workflows', ['id' => $workflowId]);

        $this->postJson('/api/workflows/'.$workflowId.'/restore')->assertStatus(200);
        $this->assertNotSoftDeleted('workflows', ['id' => $workflowId]);

        $afterPausedRestore = $this->getJson('/api/workflows/'.$workflowId);
        $this->assertSame(
            'paused',
            $afterPausedRestore->json('data.status'),
            'Restore must preserve the status that existed before delete.',
        );

        $resume = $this->postJson('/api/workflows/'.$workflowId.'/activate');
        $resume->assertStatus(200);
        $this->assertDatabaseHas('workflows', ['id' => $workflowId, 'status' => 'active']);

        $this->deleteJson('/api/workflows/'.$workflowId)->assertStatus(200);
        $this->assertSoftDeleted('workflows', ['id' => $workflowId]);

        $this->postJson('/api/workflows/'.$workflowId.'/restore')->assertStatus(200);
        $this->assertNotSoftDeleted('workflows', ['id' => $workflowId]);

        $afterActiveRestore = $this->getJson('/api/workflows/'.$workflowId);
        $this->assertSame('active', $afterActiveRestore->json('data.status'));
    }

    // -------------------------------------------------------------------
    // 4. Manual execution — payload / idempotency matrix
    // -------------------------------------------------------------------

    public function test_manual_execution_accepts_empty_body(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'Empty body'])->json('data.id');

        // Phase 1: draft workflows execute. No trigger needed.
        $this->postJson('/api/workflows/'.$workflowId.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['to' => 'x@example.com', 'subject' => 'S', 'body' => 'B'],
        ])->assertStatus(201);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $response = $this->postJson('/api/workflows/'.$workflowId.'/execute', []);

        $response->assertStatus(201);
        $this->assertIsArray($response->json());
    }

    public function test_manual_execution_idempotency_key_prevents_second_run(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'Idem'])->json('data.id');

        ExecutionModel::create([
            'workflow_id' => $workflowId,
            'user_id' => $user->id,
            'status' => 'completed',
            'trigger_source' => 'manual',
            'workflow_snapshot' => ['workflow' => [], 'trigger' => null, 'steps' => []],
            'idempotency_key' => 'audit-key-1',
        ]);

        $first = $this->postJson('/api/workflows/'.$workflowId.'/execute', [
            'idempotency_key' => 'audit-key-1',
        ]);
        $first->assertStatus(200);

        $count = ExecutionModel::where('workflow_id', $workflowId)
            ->where('idempotency_key', 'audit-key-1')
            ->count();

        $this->assertSame(1, $count, 'Idempotent replay must not create a second execution.');
    }

    public function test_manual_execution_rejects_reserved_idempotency_prefixes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'Reserved'])->json('data.id');

        foreach (['schedule:anything', 'gmail:8:m-abc'] as $bad) {
            $response = $this->postJson('/api/workflows/'.$workflowId.'/execute', [
                'idempotency_key' => $bad,
            ]);
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['idempotency_key']);
        }
    }

    // -------------------------------------------------------------------
    // 5. interval_minutes — full validation sweep
    // -------------------------------------------------------------------

    #[DataProvider('intervalMinutesProvider')]
    public function test_interval_minutes_validation(string $label, mixed $value, bool $shouldPass): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'Interval '.$label])->json('data.id');

        // Uses TestRestrictedIntegration trigger key so trigger_key validation
        // passes and interval_minutes validation is reachable.
        $body = [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
        ];
        if ($value !== '__OMIT__') {
            $body['interval_minutes'] = $value;
        }

        $response = $this->putJson('/api/workflows/'.$workflowId.'/trigger', $body);

        if ($shouldPass) {
            $response->assertStatus(200);
        } else {
            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['interval_minutes']);
        }
    }

    public static function intervalMinutesProvider(): array
    {
        return [
            'one minute' => ['1min', 1, true],
            'five minutes' => ['5min', 5, true],
            'ten minutes' => ['10min', 10, true],
            'max 1440' => ['max', 1440, true],
            'zero rejected' => ['zero', 0, false],
            'negative rejected' => ['neg', -1, false],
            'over max' => ['over', 1441, false],
            'decimal rejected' => ['decimal', 5.5, false],
            'string number accepted' => ['strnum', '5', true],
            'null accepted' => ['null', null, true],
            'omitted accepted' => ['omit', '__OMIT__', true],
        ];
    }

    // -------------------------------------------------------------------
    // 6. Scheduler semantics — public state via direct model
    // -------------------------------------------------------------------

    public function test_paused_workflow_is_excluded_from_due_lookup(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Paused',
            'status' => 'paused',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $repo = app(WorkflowRepository::class);
        $due = $repo->listActiveWithTriggerDue(
            'google.gmail',
            'new_email_received',
            new \DateTimeImmutable,
        );

        $this->assertNotContains(
            (int) $workflow->id,
            $this->pluckWorkflowIds($due),
            'A paused workflow must not appear in listActiveWithTriggerDue.',
        );
    }

    public function test_draft_workflow_excluded_from_due_lookup(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Draft',
            'status' => 'draft',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $repo = app(WorkflowRepository::class);
        $due = $repo->listActiveWithTriggerDue(
            'google.gmail',
            'new_email_received',
            new \DateTimeImmutable,
        );

        $this->assertNotContains(
            (int) $workflow->id,
            $this->pluckWorkflowIds($due),
            'A draft workflow must not appear in listActiveWithTriggerDue.',
        );
    }

    public function test_active_workflow_with_future_next_poll_at_not_due(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Future',
            'status' => 'active',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
            'next_poll_at' => (new \DateTimeImmutable)->modify('+1 hour'),
        ]);

        $repo = app(WorkflowRepository::class);
        $due = $repo->listActiveWithTriggerDue(
            'google.gmail',
            'new_email_received',
            new \DateTimeImmutable,
        );

        $this->assertNotContains(
            (int) $workflow->id,
            $this->pluckWorkflowIds($due),
            'An active workflow with a future next_poll_at must not be returned as due.',
        );
    }

    // -------------------------------------------------------------------
    // 7. Ownership — user B cannot touch user A's resources
    // -------------------------------------------------------------------

    public function test_cross_user_ownership_matrix(): void
    {
        [$owner, $connA] = $this->userWithConnection();
        [$attacker, $connB] = $this->userWithConnection();

        Sanctum::actingAs($owner);
        $workflowId = $this->postJson('/api/workflows', ['name' => 'Owned by A'])->json('data.id');

        // Uses TestRestrictedIntegration trigger key (upsert succeeds).
        $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
        ]);

        $this->postJson('/api/workflows/'.$workflowId.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'connection_id' => $connA->id,
            'config' => ['to' => 'x@example.com', 'subject' => 'S', 'body' => 'B'],
        ]);

        $execution = ExecutionModel::create([
            'workflow_id' => $workflowId,
            'user_id' => $owner->id,
            'status' => 'completed',
            'trigger_source' => 'manual',
            'workflow_snapshot' => ['workflow' => [], 'trigger' => null, 'steps' => []],
        ]);

        Sanctum::actingAs($attacker);

        $this->getJson('/api/workflows/'.$workflowId)->assertStatus(404);
        $this->putJson('/api/workflows/'.$workflowId, ['name' => 'Hijacked'])->assertStatus(404);
        $this->deleteJson('/api/workflows/'.$workflowId)->assertStatus(404);
        $this->postJson('/api/workflows/'.$workflowId.'/restore')->assertStatus(404);
        $this->postJson('/api/workflows/'.$workflowId.'/activate')->assertStatus(404);
        $this->postJson('/api/workflows/'.$workflowId.'/pause')->assertStatus(404);
        $this->postJson('/api/workflows/'.$workflowId.'/execute')->assertStatus(404);
        $this->getJson('/api/workflows/'.$workflowId.'/executions')->assertStatus(404);
        $this->getJson('/api/executions/'.$execution->id)->assertStatus(404);
        $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
        ])->assertStatus(404);
        $this->deleteJson('/api/workflows/'.$workflowId.'/trigger')->assertStatus(404);
        $this->postJson('/api/workflows/'.$workflowId.'/steps', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(404);
        $this->putJson('/api/workflows/'.$workflowId.'/steps/1', [
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
        ])->assertStatus(404);
        $this->deleteJson('/api/workflows/'.$workflowId.'/steps/1')->assertStatus(404);
    }

    public function test_unauthenticated_requests_return_401(): void
    {
        $this->getJson('/api/workflows')->assertStatus(401);
        $this->postJson('/api/workflows', ['name' => 'x'])->assertStatus(401);
        $this->getJson('/api/workflows/1')->assertStatus(401);
        $this->postJson('/api/workflows/1/execute')->assertStatus(401);
        $this->getJson('/api/workflows/1/executions')->assertStatus(401);
        $this->getJson('/api/executions/1')->assertStatus(401);
        $this->getJson('/api/catalog')->assertStatus(401);
        $this->getJson('/api/connections')->assertStatus(401);
    }

    // -------------------------------------------------------------------
    // 8. Negative testing — no partial DB mutations
    // -------------------------------------------------------------------

    public function test_invalid_workflow_create_does_not_mutate_database(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $before = WorkflowModel::count();

        $this->postJson('/api/workflows', [])->assertStatus(422);
        $this->postJson('/api/workflows', ['name' => str_repeat('a', 200)])->assertStatus(422);

        $this->assertSame($before, WorkflowModel::count());
    }

    public function test_invalid_trigger_upsert_does_not_mutate_database(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'x'])->json('data.id');

        $before = WorkflowTriggerModel::where('workflow_id', $workflowId)->count();

        $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'unknown.integration',
            'trigger_key' => 'whatever',
        ])->assertStatus(422);

        $this->putJson('/api/workflows/'.$workflowId.'/trigger', [
            'integration_key' => 'google.gmail',
            'trigger_key' => 'not_a_real_trigger',
        ])->assertStatus(422);

        $after = WorkflowTriggerModel::where('workflow_id', $workflowId)->count();
        $this->assertSame($before, $after);
    }

    public function test_activation_without_trigger_returns_409_and_leaves_draft(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflowId = $this->postJson('/api/workflows', ['name' => 'x'])->json('data.id');

        $this->postJson('/api/workflows/'.$workflowId.'/activate')->assertStatus(409);
        $this->assertDatabaseHas('workflows', ['id' => $workflowId, 'status' => 'draft']);
    }
}
