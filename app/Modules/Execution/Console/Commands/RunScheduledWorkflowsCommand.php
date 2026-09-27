<?php

namespace App\Modules\Execution\Console\Commands;

use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Application\Services\DueScheduledWorkflowSelector;
use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Exceptions\ExecutionAlreadyRunningException;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use DateTimeImmutable;
use Illuminate\Console\Command;

final class RunScheduledWorkflowsCommand extends Command
{
    private const MIN_INTERVAL_MINUTES = 1;

    private const MAX_INTERVAL_MINUTES = 1440;

    protected $signature = 'workflows:run-scheduled';

    protected $description = 'Run due scheduled workflows across all users.';

    public function handle(
        WorkflowRepository $workflows,
        ExecutionRepository $executions,
        DueScheduledWorkflowSelector $selector,
        RunWorkflowAction $runWorkflow,
    ): int {
        $now = new DateTimeImmutable;

        $discovered = $workflows->listActiveWithTriggerStrategy('schedule');

        $processed = 0;
        $executed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($discovered as $pair) {
            $workflow = $pair['workflow'];
            $trigger = $pair['trigger'];
            $processed++;

            if (! $this->isValidInterval($trigger->intervalMinutes)) {
                $skipped++;
                $this->line(sprintf('Workflow %d: skipped (invalid interval)', $workflow->id));

                continue;
            }

            if ($executions->hasInProgressForWorkflow((int) $workflow->id)) {
                $skipped++;
                $this->line(sprintf('Workflow %d: skipped (execution in progress)', $workflow->id));

                continue;
            }

            $lastScheduled = $executions->findLastScheduledForWorkflow((int) $workflow->id);

            if (! $selector->isDue($lastScheduled, $trigger->intervalMinutes, $now)) {
                $skipped++;
                $this->line(sprintf('Workflow %d: skipped (not due)', $workflow->id));

                continue;
            }

            $idempotencyKey = $this->deriveIdempotencyKey(
                (int) $workflow->id,
                $lastScheduled,
                $trigger->intervalMinutes,
            );

            try {
                $runWorkflow->execute(new RunWorkflowInput(
                    userId: $workflow->userId,
                    workflowId: (int) $workflow->id,
                    triggerPayload: [],
                    idempotencyKey: $idempotencyKey,
                    triggerSource: 'schedule',
                ));

                $executed++;
                $this->line(sprintf(
                    'Workflow %d: executed (key=%s)',
                    $workflow->id,
                    $idempotencyKey,
                ));
            } catch (WorkflowNotFoundException|WorkflowNotExecutableException|ExecutionAlreadyRunningException $e) {
                $failed++;
                $this->line(sprintf(
                    'Workflow %d: expected failure (%s)',
                    $workflow->id,
                    $e->getMessage(),
                ));
                report($e);
            }
        }

        $this->info(sprintf(
            'Processed %d. Executed %d. Skipped %d. Failed %d.',
            $processed,
            $executed,
            $skipped,
            $failed,
        ));

        return self::SUCCESS;
    }

    private function isValidInterval(?int $intervalMinutes): bool
    {
        return $intervalMinutes !== null
            && $intervalMinutes >= self::MIN_INTERVAL_MINUTES
            && $intervalMinutes <= self::MAX_INTERVAL_MINUTES;
    }

    private function deriveIdempotencyKey(
        int $workflowId,
        ?Execution $lastScheduled,
        int $intervalMinutes,
    ): string {
        if ($lastScheduled === null) {
            return sprintf('schedule:%d:first', $workflowId);
        }

        $lastRunAt = $lastScheduled->startedAt ?? $lastScheduled->createdAt;
        $dueAt = $lastRunAt->modify('+'.$intervalMinutes.' minutes');

        return sprintf('schedule:%d:%d', $workflowId, $dueAt->getTimestamp());
    }
}
