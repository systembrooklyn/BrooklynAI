<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\TestCase;

class RunScheduledWorkflowsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeScheduledWorkflow(
        User $user,
        string $status = WorkflowStatus::Active->value,
        ?int $intervalMinutes = 30,
        string $strategy = 'schedule',
        array $steps = [],
    ): int {
        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Scheduled '.uniqid(),
            'description' => null,
            'status' => $status,
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => $strategy,
            'config' => [],
            'interval_minutes' => $intervalMinutes,
        ]);

        $position = 1;
        foreach ($steps as $step) {
            WorkflowStepModel::create([
                'workflow_id' => $workflow->id,
                'position' => $position++,
                'integration_key' => $step['integration_key'],
                'action_key' => $step['action_key'],
                'connection_id' => null,
                'config' => $step['config'] ?? [],
            ]);
        }

        return (int) $workflow->id;
    }

    private function seedLastScheduledExecution(int $workflowId, User $user, string $startedAtIso, string $status = 'completed'): int
    {
        $execution = ExecutionModel::create([
            'workflow_id' => $workflowId,
            'user_id' => $user->id,
            'status' => $status,
            'trigger_source' => 'schedule',
            'trigger_payload' => [],
            'workflow_snapshot' => ['workflow' => [], 'trigger' => null, 'steps' => []],
            'idempotency_key' => 'schedule:'.$workflowId.':seed',
            'started_at' => $startedAtIso,
            'finished_at' => $startedAtIso,
        ]);

        return (int) $execution->id;
    }

    public function test_command_is_registered(): void
    {
        $this->assertTrue(
            array_key_exists('workflows:run-scheduled', \Illuminate\Support\Facades\Artisan::all())
        );
    }

    public function test_command_runs_with_no_workflows(): void
    {
        $this->artisan('workflows:run-scheduled')
            ->assertExitCode(0);
    }

    public function test_active_scheduled_workflow_is_executed_when_due(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 30);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $workflowId,
            'trigger_source' => 'schedule',
            'status' => 'completed',
        ]);
    }

    public function test_first_scheduled_execution_uses_first_idempotency_key(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $workflowId,
            'idempotency_key' => 'schedule:'.$workflowId.':first',
        ]);
    }

    public function test_draft_workflow_is_not_executed(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, status: WorkflowStatus::Draft->value);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(0, ExecutionModel::query()->where('workflow_id', $workflowId)->count());
    }

    public function test_paused_workflow_is_not_executed(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, status: WorkflowStatus::Paused->value);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(0, ExecutionModel::query()->where('workflow_id', $workflowId)->count());
    }

    public function test_non_schedule_strategy_is_ignored(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, strategy: 'webhook');

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(0, ExecutionModel::query()->where('workflow_id', $workflowId)->count());
    }

    public function test_null_interval_is_ignored(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: null);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(0, ExecutionModel::query()->where('workflow_id', $workflowId)->count());
    }

    public function test_interval_above_1440_is_ignored(): void
    {
        $user = User::factory()->create();

        // The DB column permits this; the scheduler must reject it.
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 2000);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(0, ExecutionModel::query()->where('workflow_id', $workflowId)->count());
    }

    public function test_soft_deleted_workflow_is_excluded(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user);
        WorkflowModel::query()->where('id', $workflowId)->delete();

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(0, ExecutionModel::query()->where('workflow_id', $workflowId)->count());
    }

    public function test_not_due_when_recent_execution_exists(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 30);

        $this->seedLastScheduledExecution($workflowId, $user, now()->subMinutes(5)->toDateTimeString());

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(
            1,
            ExecutionModel::query()->where('workflow_id', $workflowId)->count()
        );
    }

    public function test_due_after_interval_has_passed(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 30);

        $this->seedLastScheduledExecution($workflowId, $user, now()->subMinutes(45)->toDateTimeString());

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(
            2,
            ExecutionModel::query()->where('workflow_id', $workflowId)->count()
        );
    }

    public function test_workflow_with_running_execution_is_skipped(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 30);

        $this->seedLastScheduledExecution($workflowId, $user, now()->subMinutes(60)->toDateTimeString(), status: 'running');

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(
            1,
            ExecutionModel::query()->where('workflow_id', $workflowId)->count()
        );
    }

    public function test_repeated_invocation_is_noop_when_not_due(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 30);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);
        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        // Second run is a no-op because the first created a completed
        // execution whose due_at is still in the future.
        $this->assertSame(
            1,
            ExecutionModel::query()->where('workflow_id', $workflowId)->count()
        );
    }

    public function test_same_idempotency_key_yields_single_execution(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, intervalMinutes: 30);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $action = $this->app->make(RunWorkflowAction::class);

        $input = new RunWorkflowInput(
            userId: (int) $user->id,
            workflowId: $workflowId,
            triggerPayload: [],
            idempotencyKey: 'schedule:'.$workflowId.':first',
            triggerSource: 'schedule',
        );

        $action->execute($input);
        $action->execute($input);

        $this->assertSame(
            1,
            ExecutionModel::query()
                ->where('workflow_id', $workflowId)
                ->where('idempotency_key', 'schedule:'.$workflowId.':first')
                ->count(),
        );
    }

    public function test_multiple_users_due_workflows_all_execute_in_one_tick(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $a = $this->makeScheduledWorkflow($userA);
        $b = $this->makeScheduledWorkflow($userB);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertSame(1, ExecutionModel::query()->where('workflow_id', $a)->count());
        $this->assertSame(1, ExecutionModel::query()->where('workflow_id', $b)->count());
    }

    public function test_scheduler_uses_schedule_trigger_source(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $workflowId,
            'trigger_source' => 'schedule',
        ]);
    }

    public function test_zero_step_workflow_completes_on_scheduled_run(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, steps: []);

        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $workflowId,
            'status' => 'completed',
        ]);
    }

    public function test_unexpected_exception_is_propagated_and_command_fails(): void
    {
        $user = User::factory()->create();
        $workflowId = $this->makeScheduledWorkflow($user, steps: [
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B'],
            ],
        ]);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw new \LogicException('unexpected');
        $this->app->instance(ActionInvoker::class, $fake);

        $this->expectException(\LogicException::class);

        $this->artisan('workflows:run-scheduled');
    }

    public function test_expected_failure_does_not_stop_batch(): void
    {
        $user = User::factory()->create();

        $a = $this->makeScheduledWorkflow($user, steps: [
            [
                'integration_key' => 'google.gmail',
                'action_key' => 'send_email',
                'config' => ['to' => 'a@example.com', 'subject' => 'S', 'body' => 'B'],
            ],
        ]);

        $b = $this->makeScheduledWorkflow($user);

        $fake = new FakeActionInvoker;
        $fake->onInvoke = fn () => throw \App\Modules\Execution\Core\Exceptions\ActionInvocationFailed::forInvalidConfig('to');
        $this->app->instance(ActionInvoker::class, $fake);

        $this->artisan('workflows:run-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $a,
            'status' => 'failed',
        ]);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $b,
            'status' => 'completed',
        ]);
    }
}
