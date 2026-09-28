<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Execution\Application\Strategies\ScheduleStrategy;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleStrategyTest extends TestCase
{
    use RefreshDatabase;

    private function makeWorkflowWithScheduleTrigger(DateTimeImmutable $nextPollAt, int $interval = 5): array
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'schedule-wf-' . uniqid(),
            'status' => 'active',
        ]);

        $trigger = WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'scheduler',
            'trigger_key' => 'interval',
            'strategy' => 'schedule',
            'config' => [],
            'interval_minutes' => $interval,
            'next_poll_at' => $nextPollAt,
        ]);

        return [$user, $workflow, $trigger];
    }

    public function test_due_schedule_trigger_executes(): void
    {
        $now = new DateTimeImmutable;
        [$user, $workflow, $triggerModel] = $this->makeWorkflowWithScheduleTrigger(
            $now->modify('-1 minute'),
        );

        $repo = app(WorkflowRepository::class);
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);
        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);

        $this->assertNotNull($workflowEntity);
        $this->assertNotNull($triggerEntity);

        $result = app(ScheduleStrategy::class)->process($workflowEntity, $triggerEntity, $now);

        $this->assertTrue($result->executed);
        $this->assertSame(1, $result->executionCount);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $workflow->id,
            'trigger_source' => 'schedule',
            'status' => 'completed',
        ]);

        $this->assertSame(
            1,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
        );

        $triggerModel->refresh();
        $this->assertNotNull($triggerModel->next_poll_at);

        $advancedTs = strtotime((string) $triggerModel->next_poll_at);
        $this->assertGreaterThan($now->getTimestamp(), $advancedTs);
        $this->assertLessThanOrEqual(
            $now->modify('+6 minutes')->getTimestamp(),
            $advancedTs,
        );
    }

    public function test_non_due_schedule_trigger_does_not_execute(): void
    {
        $now = new DateTimeImmutable;
        [$user, $workflow] = $this->makeWorkflowWithScheduleTrigger(
            $now->modify('+1 hour'),
        );

        $repo = app(WorkflowRepository::class);
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);
        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);

        $result = app(ScheduleStrategy::class)->process($workflowEntity, $triggerEntity, $now);

        $this->assertFalse($result->executed);
        $this->assertSame(
            0,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
        );
    }

    public function test_schedule_idempotency_prevents_duplicate_execution(): void
    {
        $now = new DateTimeImmutable;
        $dueAt = $now->modify('-1 minute');

        [$user, $workflow, $triggerModel] = $this->makeWorkflowWithScheduleTrigger($dueAt);

        $repo = app(WorkflowRepository::class);
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);

        $strategy = app(ScheduleStrategy::class);

        // First process — creates the execution and advances next_poll_at.
        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);
        $strategy->process($workflowEntity, $triggerEntity, $now);

        $this->assertSame(
            1,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
            'First process should create exactly one execution.',
        );

        // Reset next_poll_at via the query builder, not the stale model instance.
        // The strategy mutated the row via a different model instance; the test's
        // $triggerModel still holds the original in-memory value, so calling
        // ->update() on it would be a no-op.
        WorkflowTriggerModel::query()
            ->where('id', $triggerModel->id)
            ->update(['next_poll_at' => $dueAt]);

        $triggerEntity2 = $repo->findTriggerForWorkflow((int) $workflow->id);
        $strategy->process($workflowEntity, $triggerEntity2, $now);

        $this->assertSame(
            1,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
            'Second process with the same next_poll_at must not create a duplicate execution.',
        );
    }
}
