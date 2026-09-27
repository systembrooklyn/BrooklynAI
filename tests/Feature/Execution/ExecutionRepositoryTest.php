<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Entities\ExecutionStep;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ExecutionRepository $repo;

    private WorkflowRepository $workflows;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(ExecutionRepository::class);
        $this->workflows = $this->app->make(WorkflowRepository::class);
    }

    private function createWorkflow(User $user, string $name = 'Test'): int
    {
        $now = new DateTimeImmutable;

        $workflow = new Workflow(
            id: null,
            userId: (int) $user->id,
            name: $name,
            description: null,
            status: WorkflowStatus::Draft,
            deletedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        return (int) $this->workflows->save($workflow)->id;
    }

    private function newExecution(User $user, int $workflowId): Execution
    {
        $now = new DateTimeImmutable;

        return new Execution(
            id: null,
            workflowId: $workflowId,
            userId: (int) $user->id,
            status: ExecutionStatus::Pending,
            triggerSource: 'test',
            triggerPayload: [],
            workflowSnapshot: [],
            idempotencyKey: null,
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function test_save_and_find_execution(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->createWorkflow($user);
        $saved = $this->repo->save($this->newExecution($user, $workflowId));

        $this->assertNotNull($saved->id);

        $found = $this->repo->findForUser((int) $user->id, (int) $saved->id);
        $this->assertNotNull($found);
        $this->assertSame(ExecutionStatus::Pending, $found->status);
        $this->assertSame($workflowId, $found->workflowId);
    }

    public function test_find_does_not_return_other_users_execution(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $workflowId = $this->createWorkflow($owner);
        $saved = $this->repo->save($this->newExecution($owner, $workflowId));

        $found = $this->repo->findForUser((int) $attacker->id, (int) $saved->id);
        $this->assertNull($found);
    }

    public function test_has_in_progress_for_workflow(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->createWorkflow($user);

        $this->assertFalse($this->repo->hasInProgressForWorkflow($workflowId));

        $this->repo->save($this->newExecution($user, $workflowId));

        $this->assertTrue($this->repo->hasInProgressForWorkflow($workflowId));
    }

    public function test_idempotency_key_is_unique_per_workflow(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->createWorkflow($user);
        $now = new DateTimeImmutable;

        $base = new Execution(
            id: null,
            workflowId: $workflowId,
            userId: (int) $user->id,
            status: ExecutionStatus::Pending,
            triggerSource: 'poll',
            triggerPayload: [],
            workflowSnapshot: [],
            idempotencyKey: 'event-1',
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->save($base);

        $this->expectException(QueryException::class);

        $dup = new Execution(
            id: null,
            workflowId: $workflowId,
            userId: (int) $user->id,
            status: ExecutionStatus::Pending,
            triggerSource: 'poll',
            triggerPayload: [],
            workflowSnapshot: [],
            idempotencyKey: 'event-1',
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->save($dup);
    }

    public function test_multiple_null_idempotency_keys_allowed(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->createWorkflow($user);

        $this->repo->save($this->newExecution($user, $workflowId));
        $this->repo->save($this->newExecution($user, $workflowId));

        $this->assertCount(2, $this->repo->listForWorkflow($workflowId));
    }

    public function test_execution_step_save(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->createWorkflow($user);
        $execution = $this->repo->save($this->newExecution($user, $workflowId));
        $now = new DateTimeImmutable;

        $step = new ExecutionStep(
            id: null,
            executionId: (int) $execution->id,
            position: 1,
            stepSnapshot: ['integration_key' => 'google.sheets'],
            status: ExecutionStepStatus::Pending,
            input: null,
            output: null,
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $saved = $this->repo->saveStep($step);
        $this->assertNotNull($saved->id);

        $list = $this->repo->listStepsForExecution((int) $execution->id);
        $this->assertCount(1, $list);
        $this->assertSame(1, $list[0]->position);
    }

    public function test_restrict_on_delete_prevents_force_deleting_workflow_with_executions(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->createWorkflow($user);
        $this->repo->save($this->newExecution($user, $workflowId));

        $this->expectException(QueryException::class);

        // Force delete the workflow, bypassing soft-delete
        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel::withTrashed()
            ->where('id', $workflowId)
            ->forceDelete();
    }
}
