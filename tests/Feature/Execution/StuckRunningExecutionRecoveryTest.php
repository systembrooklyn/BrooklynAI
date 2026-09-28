<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Execution\Application\Services\SchedulerTickService;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StuckRunningExecutionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeRunningExecution(int $minutesOld): ExecutionModel
    {
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'stuck-'.uniqid(),
            'status' => 'active',
        ]);

        $startedAt = (new DateTimeImmutable)->modify("-{$minutesOld} minutes");

        return ExecutionModel::create([
            'workflow_id' => $workflow->id,
            'user_id' => $user->id,
            'status' => 'running',
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);
    }

    public function test_stale_running_execution_is_marked_failed(): void
    {
        $execution = $this->makeRunningExecution(10);

        $repo = app(ExecutionRepository::class);
        $repo->markStaleRunningAsFailed(
            (new DateTimeImmutable)->modify('-5 minutes'),
        );

        $execution->refresh();
        $this->assertSame('failed', $execution->status);
        $this->assertNotNull($execution->finished_at);
        $this->assertNotNull($execution->error_message);
    }

    public function test_fresh_running_execution_is_not_touched(): void
    {
        $execution = $this->makeRunningExecution(1);

        $repo = app(ExecutionRepository::class);
        $affected = $repo->markStaleRunningAsFailed(
            (new DateTimeImmutable)->modify('-5 minutes'),
        );

        $this->assertSame(0, $affected);
        $execution->refresh();
        $this->assertSame('running', $execution->status);
    }

    public function test_completed_execution_is_not_touched(): void
    {
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'done-'.uniqid(),
            'status' => 'active',
        ]);

        $execution = ExecutionModel::create([
            'workflow_id' => $workflow->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'trigger_source' => 'manual',
            'workflow_snapshot' => [],
            'started_at' => (new DateTimeImmutable)->modify('-1 hour'),
            'finished_at' => (new DateTimeImmutable)->modify('-1 hour'),
        ]);

        $repo = app(ExecutionRepository::class);
        $repo->markStaleRunningAsFailed((new DateTimeImmutable)->modify('-5 minutes'));

        $execution->refresh();
        $this->assertSame('completed', $execution->status);
    }

    public function test_recovery_is_idempotent(): void
    {
        $this->makeRunningExecution(10);

        $repo = app(ExecutionRepository::class);
        $threshold = (new DateTimeImmutable)->modify('-5 minutes');

        $first = $repo->markStaleRunningAsFailed($threshold);
        $second = $repo->markStaleRunningAsFailed($threshold);

        $this->assertSame(1, $first);
        $this->assertSame(0, $second, 'Second sweep must not re-update already-failed rows.');
    }

    public function test_scheduler_tick_marks_stale_running_before_dispatching(): void
    {
        Cache::flush();

        $staleExecution = $this->makeRunningExecution(10);

        $tick = app(SchedulerTickService::class);
        $result = $tick->tick();

        $staleExecution->refresh();
        $this->assertSame('failed', $staleExecution->status);
        $this->assertFalse($result->lockHeld);
    }
}
