<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Automation\Infrastructure\Repositories\EloquentWorkflowRepository;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\Strategies\GoogleDriveChangePollStrategy;
use App\Modules\Execution\Infrastructure\Repositories\EloquentExecutionRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class GoogleDriveChangePollStrategyTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_call_initializes_page_token(): void
    {
        [$strategy, $trigger] = $this->makeStrategy(
            triggerKey: 'file_created',
            initialRuntimeState: null,
            startTokenReturn: 'start-token-1',
            changesResponses: [],
        );

        $result = $strategy->process($this->loadWorkflow($trigger), $this->loadTrigger($trigger), new DateTimeImmutable);

        $this->assertFalse($result->executed);
        $this->assertSame('page_token_initialized', $result->reason);

        $refreshed = WorkflowTriggerModel::query()->where('id', $trigger->id)->first();
        $this->assertSame('start-token-1', $refreshed->runtime_state['page_token'] ?? null);
    }

    public function test_subsequent_call_fetches_changes_and_persists_new_token(): void
    {
        [$strategy, $trigger] = $this->makeStrategy(
            triggerKey: 'file_created',
            initialRuntimeState: ['page_token' => 'token-1'],
            changesResponses: [
                [
                    'changes' => [
                        [
                            'file_id' => 'f1',
                            'removed' => false,
                            'file' => ['id' => 'f1', 'name' => 'doc.pdf', 'modified_time' => '2026-01-01T00:00:00Z'],
                            'change_type' => 'file',
                        ],
                    ],
                    'new_start_page_token' => 'token-2',
                    'next_page_token' => null,
                ],
            ],
        );

        $result = $strategy->process($this->loadWorkflow($trigger), $this->loadTrigger($trigger), new DateTimeImmutable);

        $this->assertTrue($result->executed);
        $this->assertSame(1, $result->executionCount);

        $this->assertDatabaseCount('executions', 1);

        $refreshed = WorkflowTriggerModel::query()->where('id', $trigger->id)->first();
        $this->assertSame('token-2', $refreshed->runtime_state['page_token'] ?? null);
    }

    public function test_removed_change_only_fires_for_removed_trigger(): void
    {
        [$strategy, $trigger] = $this->makeStrategy(
            triggerKey: 'file_removed_from_access',
            initialRuntimeState: ['page_token' => 'token-1'],
            changesResponses: [
                [
                    'changes' => [
                        [
                            'file_id' => 'f1',
                            'removed' => true,
                            'file' => null,
                            'change_type' => 'file',
                        ],
                    ],
                    'new_start_page_token' => 'token-2',
                    'next_page_token' => null,
                ],
            ],
        );

        $result = $strategy->process($this->loadWorkflow($trigger), $this->loadTrigger($trigger), new DateTimeImmutable);

        $this->assertTrue($result->executed);
        $this->assertSame(1, $result->executionCount);
    }

    public function test_pagination_processes_all_pages(): void
    {
        [$strategy, $trigger] = $this->makeStrategy(
            triggerKey: 'file_created',
            initialRuntimeState: ['page_token' => 'page-1'],
            changesResponses: [
                [
                    'changes' => [
                        [
                            'file_id' => 'f1',
                            'removed' => false,
                            'file' => ['id' => 'f1', 'modified_time' => '1'],
                            'change_type' => 'file',
                        ],
                    ],
                    'new_start_page_token' => null,
                    'next_page_token' => 'page-2',
                ],
                [
                    'changes' => [
                        [
                            'file_id' => 'f2',
                            'removed' => false,
                            'file' => ['id' => 'f2', 'modified_time' => '2'],
                            'change_type' => 'file',
                        ],
                    ],
                    'new_start_page_token' => 'final-token',
                    'next_page_token' => null,
                ],
            ],
        );

        $result = $strategy->process($this->loadWorkflow($trigger), $this->loadTrigger($trigger), new DateTimeImmutable);

        $this->assertTrue($result->executed);
        $this->assertSame(2, $result->executionCount);

        $this->assertDatabaseCount('executions', 2);

        $refreshed = WorkflowTriggerModel::query()->where('id', $trigger->id)->first();
        $this->assertSame('final-token', $refreshed->runtime_state['page_token'] ?? null);
    }

    /**
     * Phase 3 P0: verifies that when the polling loop hits MAX_PAGES_PER_TICK
     * without reaching the final page, the current next-page token is
     * persisted so the next tick resumes from page N+1 instead of restarting
     * from page 1. Also verifies idempotency prevents duplicate executions
     * across ticks and that new_start_page_token is persisted only after the
     * final page.
     */
    public function test_pagination_resumes_next_tick_when_max_pages_reached(): void
    {
        $this->assertSame(10, (new \ReflectionClassConstant(
            GoogleDriveChangePollStrategy::class,
            'MAX_PAGES_PER_TICK'
        ))->getValue(), 'This test assumes MAX_PAGES_PER_TICK = 10. Update the test if the constant changes.');

        // 15 total pages of changes, 1 change per page.
        // Page 15 is the final page (new_start_page_token set, next_page_token null).
        $changesResponses = [];
        for ($i = 1; $i <= 15; $i++) {
            $changesResponses[] = [
                'changes' => [
                    [
                        'file_id' => 'file-'.$i,
                        'removed' => false,
                        'file' => ['id' => 'file-'.$i, 'name' => 'doc-'.$i, 'modified_time' => (string) $i],
                        'change_type' => 'file',
                    ],
                ],
                'new_start_page_token' => $i === 15 ? 'final-token' : null,
                'next_page_token' => $i < 15 ? 'page-'.($i + 1) : null,
            ];
        }

        [$strategy, $trigger] = $this->makeStrategy(
            triggerKey: 'file_created',
            initialRuntimeState: ['page_token' => 'page-1'],
            changesResponses: $changesResponses,
        );

        $drive = $this->lastDrive;
        $this->assertNotNull($drive, 'FakeGoogleDriveClient must be retained for call assertions.');

        // --- Tick 1 ---
        $result1 = $strategy->process(
            $this->loadWorkflow($trigger),
            $this->loadTrigger($trigger),
            new DateTimeImmutable,
        );

        $this->assertTrue($result1->executed, 'Tick 1 must be executed.');
        $this->assertSame(10, $result1->executionCount, 'Tick 1 must process exactly MAX_PAGES_PER_TICK (10) pages.');

        $this->assertCount(10, $drive->listChangesCalls, 'Tick 1 must issue exactly 10 listChanges calls.');
        $this->assertSame('page-1', $drive->listChangesCalls[0]['page_token']);
        $this->assertSame('page-10', $drive->listChangesCalls[9]['page_token']);

        $refreshed1 = WorkflowTriggerModel::query()->where('id', $trigger->id)->first();
        $this->assertSame(
            'page-11',
            $refreshed1->runtime_state['page_token'] ?? null,
            'Tick 1 must persist the current position (page-11) when MAX_PAGES_PER_TICK is reached.'
        );

        $this->assertDatabaseCount('executions', 10);

        // --- Tick 2 ---
        $result2 = $strategy->process(
            $this->loadWorkflow($trigger),
            $this->loadTrigger($trigger),
            new DateTimeImmutable,
        );

        $this->assertTrue($result2->executed, 'Tick 2 must be executed.');
        $this->assertSame(5, $result2->executionCount, 'Tick 2 must process the remaining 5 pages.');

        $this->assertCount(15, $drive->listChangesCalls, 'Tick 2 must issue exactly 5 additional listChanges calls.');
        $this->assertSame(
            'page-11',
            $drive->listChangesCalls[10]['page_token'],
            'Tick 2 must resume from page-11, not from page-1.'
        );

        $refreshed2 = WorkflowTriggerModel::query()->where('id', $trigger->id)->first();
        $this->assertSame(
            'final-token',
            $refreshed2->runtime_state['page_token'] ?? null,
            'Tick 2 must persist new_start_page_token after reaching the final page.'
        );

        $this->assertDatabaseCount('executions', 15);

        // Verify no duplicate idempotency keys across the 15 executions.
        $keys = \App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel::query()
            ->where('workflow_id', $trigger->workflow_id)
            ->pluck('idempotency_key')
            ->all();

        $this->assertCount(15, $keys);
        $this->assertCount(15, array_unique($keys), 'No duplicate idempotency keys across ticks.');
    }

    public function test_unsupported_integration_is_skipped(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'test',
            'status' => 'active',
        ]);

        $trigger = WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
            'interval_minutes' => 5,
            'runtime_state' => ['page_token' => 'token-1'],
        ]);

        $repo = new EloquentWorkflowRepository;
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);
        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);

        $strategy = new GoogleDriveChangePollStrategy(
            $repo,
            app(ConnectionRepository::class),
            new EloquentExecutionRepository,
            new FakeGoogleCredentialsResolver,
            new FakeGoogleDriveClient,
            $this->app->make(RunWorkflowAction::class),
        );

        $result = $strategy->process($workflowEntity, $triggerEntity, new DateTimeImmutable);

        $this->assertFalse($result->executed);
        $this->assertSame('unsupported_integration', $result->reason);
        $this->assertDatabaseCount('executions', 0);
    }

    // ---------------------------------------------------------------------

    /**
     * Retained per-test between calls within a single test method so the
     * pagination test can assert on the FakeGoogleDriveClient's call log
     * after two invocations of process().
     */
    private ?FakeGoogleDriveClient $lastDrive = null;

    private function makeStrategy(
        string $triggerKey,
        ?array $initialRuntimeState,
        array $changesResponses,
        string $startTokenReturn = 'start-token',
    ): array {
        $user = User::factory()->create();

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-'.uniqid(),
            'access_token' => 'fake',
            'refresh_token' => 'fake',
            'scopes' => ['https://www.googleapis.com/auth/drive.file'],
            'status' => 'active',
        ]);

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'test',
            'status' => 'active',
        ]);

        $trigger = WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.drive',
            'trigger_key' => $triggerKey,
            'connection_id' => $connection->id,
            'strategy' => 'poll',
            'config' => [],
            'interval_minutes' => 5,
            'runtime_state' => $initialRuntimeState,
        ]);

        $drive = new FakeGoogleDriveClient;
        $drive->listChangesQueue = $changesResponses;
        $drive->nextStartPageTokenReturn = $startTokenReturn;

        $this->lastDrive = $drive;

        $strategy = new GoogleDriveChangePollStrategy(
            new EloquentWorkflowRepository,
            app(ConnectionRepository::class),
            new EloquentExecutionRepository,
            new FakeGoogleCredentialsResolver,
            $drive,
            $this->app->make(RunWorkflowAction::class),
        );

        return [$strategy, $trigger];
    }

    private function loadWorkflow(WorkflowTriggerModel $trigger): \App\Modules\Automation\Core\Entities\Workflow
    {
        $repo = new EloquentWorkflowRepository;
        $workflow = $repo->findForUser((int) $trigger->workflow_id, (int) $trigger->workflow_id);

        if ($workflow === null) {
            $workflowModel = WorkflowModel::findOrFail($trigger->workflow_id);
            $workflow = $repo->findForUser((int) $workflowModel->user_id, (int) $workflowModel->id);
        }

        $this->assertNotNull($workflow, 'Workflow could not be loaded for trigger '.$trigger->id);

        return $workflow;
    }

    private function loadTrigger(WorkflowTriggerModel $trigger): \App\Modules\Automation\Core\Entities\WorkflowTrigger
    {
        $repo = new EloquentWorkflowRepository;
        $entity = $repo->findTriggerForWorkflow((int) $trigger->workflow_id);

        $this->assertNotNull($entity, 'Trigger entity could not be loaded for trigger '.$trigger->id);

        return $entity;
    }
}
