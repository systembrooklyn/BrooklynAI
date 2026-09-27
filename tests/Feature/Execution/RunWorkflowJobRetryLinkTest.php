<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RunWorkflowJobRetryLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_workflow_job_persists_retry_of_id_through_full_action_chain(): void
    {
        $user = User::factory()->create();
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'w', 'status' => WorkflowStatus::Active->value,
        ]);
        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 1,
            'integration_key' => 'google.gmail',
            'action_key' => 'send_email',
            'config' => ['to' => 'x@x.com', 'subject' => 's', 'body' => 'b'],
        ]);

        $original = ExecutionModel::create([
            'workflow_id' => $workflow->id, 'user_id' => $user->id,
            'status' => 'failed', 'trigger_source' => 'poll',
            'trigger_payload' => [], 'workflow_snapshot' => [],
            'idempotency_key' => 'gmail:'.$workflow->id.':m1',
        ]);

        // Stub the ActionInvoker so the workflow runs without hitting Gmail.
        $this->app->bind(ActionInvoker::class, function () {
            return new class implements ActionInvoker
            {
                public function invoke(
                    string $integrationKey,
                    string $actionKey,
                    int $userId,
                    ?int $connectionId,
                    array $resolvedConfig,
                ): array {
                    return ['sent' => true, 'message_id' => 'stub'];
                }
            };
        });

        $job = new RunWorkflowJob(
            userId: (int) $user->id,
            workflowId: (int) $workflow->id,
            triggerPayload: ['subject' => 'x', 'message_id' => 'm1'],
            idempotencyKey: 'gmail:'.$workflow->id.':m1#r1',
            triggerSource: 'poll',
            retryOfId: (int) $original->id,
        );

        $job->handle(app(RunWorkflowAction::class));

        $this->assertDatabaseHas('executions', [
            'idempotency_key' => 'gmail:'.$workflow->id.':m1#r1',
            'retry_of_id' => $original->id,
            'workflow_id' => $workflow->id,
        ]);
    }
}
