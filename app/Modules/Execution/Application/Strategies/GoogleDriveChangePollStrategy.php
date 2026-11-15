<?php

namespace App\Modules\Execution\Application\Strategies;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\Contracts\TriggerStrategy;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Application\DTOs\StrategyResult;
use App\Modules\Execution\Core\Exceptions\ExecutionAlreadyRunningException;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;

final class GoogleDriveChangePollStrategy implements TriggerStrategy
{
    private const STRATEGY_KEY = 'poll';
    private const INTEGRATION_KEY = 'google.drive';

    private const TRIGGER_CREATED = 'file_created';
    private const TRIGGER_UPDATED = 'file_updated';
    private const TRIGGER_REMOVED = 'file_removed_from_access';

    private const DEFAULT_PAGE_SIZE = 100;
    private const MAX_PAGES_PER_TICK = 10;

    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly \App\Modules\Connections\Core\Repositories\ConnectionRepository $connections,
        private readonly ExecutionRepository $executions,
        private readonly GoogleCredentialsResolver $credentialsResolver,
        private readonly GoogleDriveClient $driveClient,
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

        if ($trigger->integrationKey !== self::INTEGRATION_KEY) {
            return StrategyResult::skipped('unsupported_integration');
        }

        if (! in_array($trigger->triggerKey, [
            self::TRIGGER_CREATED,
            self::TRIGGER_UPDATED,
            self::TRIGGER_REMOVED,
        ], true)) {
            return StrategyResult::skipped('unsupported_trigger');
        }

        if ($this->executions->hasInProgressForWorkflow((int) $workflow->id)) {
            return StrategyResult::skipped('execution_in_progress');
        }

        try {
            $credentials = $this->credentialsResolver->resolve($workflow->userId, $trigger->connectionId);
        } catch (ConnectionNotFoundException | GoogleCredentialsUnavailableException) {
            return StrategyResult::skipped('credentials_unavailable');
        }

        $runtimeState = $trigger->runtimeState ?? [];
        $pageToken = $runtimeState['page_token'] ?? null;

        if ($pageToken === null) {
            try {
                $pageToken = $this->driveClient->getStartPageToken($credentials);
            } catch (\Throwable $e) {
                Log::warning('Drive poll: start page token failed', [
                    'workflow_id' => $workflow->id,
                    'error' => $e->getMessage(),
                ]);

                return StrategyResult::skipped('start_page_token_failed');
            }

            $this->persistState($trigger, $now, ['page_token' => $pageToken], null);

            return StrategyResult::skipped('page_token_initialized');
        }

        $pagesProcessed = 0;
        $matched = [];
        $finalNewStartToken = null;

        while ($pagesProcessed < self::MAX_PAGES_PER_TICK) {
            try {
                $result = $this->driveClient->listChanges($credentials, $pageToken, self::DEFAULT_PAGE_SIZE);
            } catch (\Throwable $e) {
                Log::warning('Drive poll: changes list failed', [
                    'workflow_id' => $workflow->id,
                    'error' => $e->getMessage(),
                ]);

                return StrategyResult::skipped('changes_fetch_failed');
            }

            foreach ($result['changes'] as $change) {
                if ($this->matchesTrigger($trigger->triggerKey, $change)) {
                    $matched[] = $change;
                }
            }

            if ($result['new_start_page_token'] !== null) {
                $finalNewStartToken = $result['new_start_page_token'];
            }

            if ($result['next_page_token'] === null) {
                break;
            }

            $pageToken = $result['next_page_token'];
            $pagesProcessed++;
        }

        $executed = 0;
        foreach ($matched as $change) {
            $changeKey = $workflow->id.':'.$change['file_id'].':'.($change['removed'] ? 'r' : 'm').':'.($change['file']['modified_time'] ?? '');
            $idempotencyKey = 'drive:'.$changeKey;

            if ($this->executions->findByIdempotencyKey((int) $workflow->id, $idempotencyKey) !== null) {
                continue;
            }

            try {
                $this->runWorkflow->execute(new RunWorkflowInput(
                    userId: $workflow->userId,
                    workflowId: (int) $workflow->id,
                    triggerPayload: [
                        'file_id' => $change['file_id'],
                        'removed' => $change['removed'],
                        'file' => $change['file'],
                        'change_type' => $change['change_type'],
                    ],
                    idempotencyKey: $idempotencyKey,
                    triggerSource: 'poll',
                ));
                $executed++;
            } catch (WorkflowNotFoundException | WorkflowNotExecutableException) {
                continue;
            } catch (ExecutionAlreadyRunningException) {
                break;
            }
        }

        /*
         * Phase 3 P0 pagination fix:
         *
         * If $finalNewStartToken is set, the loop reached the final page and
         * we advance to the new cycle's start token. If it is null, the loop
         * exited because MAX_PAGES_PER_TICK was reached — persist the current
         * $pageToken so the next tick resumes from the next page instead of
         * restarting from page 1.
         */
        if ($finalNewStartToken !== null) {
            $this->persistState($trigger, $now, ['page_token' => $finalNewStartToken], null);
        } else {
            $this->persistState($trigger, $now, ['page_token' => $pageToken], null);
        }

        return StrategyResult::executed($executed);
    }

    private function matchesTrigger(string $triggerKey, array $change): bool
    {
        $removed = (bool) ($change['removed'] ?? false);

        return match ($triggerKey) {
            self::TRIGGER_REMOVED => $removed === true,
            self::TRIGGER_CREATED => $removed === false && ($change['change_type'] ?? 'file') === 'file',
            self::TRIGGER_UPDATED => $removed === false && ($change['change_type'] ?? 'file') === 'file',
            default => false,
        };
    }

    private function persistState(WorkflowTrigger $trigger, DateTimeImmutable $now, array $runtimeState, ?DateTimeImmutable $nextPollAt): void
    {
        $interval = $trigger->intervalMinutes ?? 5;

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
            nextPollAt: $nextPollAt ?? $now->modify('+' . $interval . ' minutes'),
            runtimeState: $runtimeState,
        );

        $this->workflows->saveTrigger($updated);
    }
}
