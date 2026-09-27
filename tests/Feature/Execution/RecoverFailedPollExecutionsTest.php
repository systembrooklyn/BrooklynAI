<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RecoverFailedPollExecutionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeFailedExecution(int $workflowId, int $userId, string $messageId, int $minutesAgo = 20): ExecutionModel
    {
        // `created_at` and `updated_at` are not in ExecutionModel::$fillable,
        // so `create()` would silently drop them. Use forceFill() to bypass
        // the fillable check so the timestamps actually persist — this is
        // what the recovery command's `updated_at < (now - grace)` filter
        // inspects.
        $execution = (new ExecutionModel)->forceFill([
            'workflow_id' => $workflowId,
            'user_id' => $userId,
            'status' => 'failed',
            'trigger_source' => 'poll',
            'trigger_payload' => ['message_id' => $messageId],
            'workflow_snapshot' => [],
            'idempotency_key' => 'gmail:'.$workflowId.':'.$messageId,
            'error_message' => 'transient',
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);

        $execution->save();

        return $execution;
    }

    public function test_failed_root_is_recovered(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'w', 'status' => WorkflowStatus::Active->value,
        ]);
        $original = $this->makeFailedExecution($workflow->id, $user->id, 'm1');

        $this->artisan('workflows:recover-failed-poll-executions')->assertSuccessful();

        Queue::assertPushed(RunWorkflowJob::class, function ($job) use ($workflow, $original) {
            return $job->idempotencyKey === 'gmail:'.$workflow->id.':m1#r1'
                && $job->retryOfId === (int) $original->id;
        });
    }

    public function test_completed_execution_is_ignored(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'w', 'status' => WorkflowStatus::Active->value,
        ]);
        ExecutionModel::create([
            'workflow_id' => $workflow->id, 'user_id' => $user->id,
            'status' => 'completed', 'trigger_source' => 'poll',
            'trigger_payload' => [], 'workflow_snapshot' => [],
            'idempotency_key' => 'gmail:'.$workflow->id.':m2',
            'created_at' => now()->subMinutes(20), 'updated_at' => now()->subMinutes(20),
        ]);

        $this->artisan('workflows:recover-failed-poll-executions')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_recent_failure_is_ignored(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'w', 'status' => WorkflowStatus::Active->value,
        ]);
        $this->makeFailedExecution($workflow->id, $user->id, 'm3', minutesAgo: 2);

        $this->artisan('workflows:recover-failed-poll-executions')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_exhausted_root_is_ignored(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'w', 'status' => WorkflowStatus::Active->value,
        ]);
        $execution = $this->makeFailedExecution($workflow->id, $user->id, 'm4');
        $execution->retry_attempts = 3;
        $execution->save();

        $this->artisan('workflows:recover-failed-poll-executions')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_cas_prevents_double_claim(): void
    {
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'w', 'status' => WorkflowStatus::Active->value,
        ]);
        $execution = $this->makeFailedExecution($workflow->id, $user->id, 'm5');

        $repo = app(ExecutionRepository::class);

        $first = $repo->claimFailedPollExecution((int) $execution->id, 0, 3);
        $second = $repo->claimFailedPollExecution((int) $execution->id, 0, 3);

        $this->assertTrue($first);
        $this->assertFalse($second);
    }
}




   