<?php

namespace App\Modules\Execution\Application\Strategies;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\Contracts\TriggerStrategy;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Application\DTOs\StrategyResult;
use App\Modules\Execution\Core\Exceptions\ExecutionAlreadyRunningException;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use DateTimeImmutable;

final class ScheduleStrategy implements TriggerStrategy
{
    private const STRATEGY_KEY = 'schedule';
    private const DEFAULT_INTERVAL_MINUTES = 5;
    private const MIN_INTERVAL_MINUTES = 1;
    private const MAX_INTERVAL_MINUTES = 1440;

    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ExecutionRepository $executions,
        private readonly RunWorkflowAction $runWorkflow,
    ) {}

    public function strategyKey(): string
    {
        return self::STRATEGY_KEY;
    }

    public function process(
        Workflow $workflow,
        WorkflowTrigger $trigger,
        DateTimeImmutable $now,
    ): StrategyResult {
        if ($workflow->id === null) {
            return StrategyResult::skipped('missing_workflow_id');
        }
        // The coordinator only selects due triggers, but enforce the invariant
        // here so the strategy is safe to call standalone.
        if ($trigger->nextPollAt !== null && $trigger->nextPollAt > $now) {
            return StrategyResult::skipped('not_due');
        }

        $interval = $trigger->intervalMinutes ?? self::DEFAULT_INTERVAL_MINUTES;

        if ($interval < self::MIN_INTERVAL_MINUTES || $interval > self::MAX_INTERVAL_MINUTES) {
            return StrategyResult::skipped('invalid_interval');
        }

        if ($this->executions->hasInProgressForWorkflow((int) $workflow->id)) {
            return StrategyResult::skipped('execution_in_progress');
        }

        $idempotencyKey = $trigger->nextPollAt !== null
            ? 'schedule:' . $workflow->id . ':' . $trigger->nextPollAt->getTimestamp()
            : 'schedule:' . $workflow->id . ':first';

        try {
            $this->runWorkflow->execute(new RunWorkflowInput(
                userId: $workflow->userId,
                workflowId: (int) $workflow->id,
                triggerPayload: [],
                idempotencyKey: $idempotencyKey,
                triggerSource: 'schedule',
            ));
        } catch (WorkflowNotFoundException | WorkflowNotExecutableException | ExecutionAlreadyRunningException) {
            $this->advanceNextPollAt($trigger, $now, $interval);

            return StrategyResult::skipped('expected_failure');
        }

        $this->advanceNextPollAt($trigger, $now, $interval);

        return StrategyResult::executed(1);
    }

    private function advanceNextPollAt(WorkflowTrigger $trigger, DateTimeImmutable $now, int $interval): void
    {
        $updated = new WorkflowTrigger(
            id: $trigger->id,
            workflowId: $trigger->workflowId,
            integrationKey: $trigger->integrationKey,
            triggerKey: $trigger->triggerKey,
            connectionId: $trigger->connectionId,
            strategy: $trigger->strategy,
            config: $trigger->config,
            intervalMinutes: $trigger->intervalMinutes,
            createdAt: $trigger->createdAt,
            updatedAt: new DateTimeImmutable,
            pollCursor: $trigger->pollCursor,
            nextPollAt: $now->modify('+' . $interval . ' minutes'),
        );

        $this->workflows->saveTrigger($updated);
    }
}
