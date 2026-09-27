<?php

namespace App\Modules\Execution\Console\Commands;

use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

final class RecoverFailedPollExecutionsCommand extends Command
{
    private const MAX_RETRIES = 3;

    private const GRACE_MINUTES = 10;

    private const BATCH_SIZE = 100;

    protected $signature = 'workflows:recover-failed-poll-executions';

    protected $description = 'Re-dispatch failed Gmail poll executions that have not yet exhausted retries.';

    public function handle(ExecutionRepository $executions): int
    {
        $olderThan = (new DateTimeImmutable)->modify('-'.self::GRACE_MINUTES.' minutes');

        $candidates = $executions->listFailedPollExecutionRoots(
            self::BATCH_SIZE,
            self::MAX_RETRIES,
            $olderThan,
        );

        $dispatched = 0;

        foreach ($candidates as $execution) {
            if ($execution->id === null || $execution->idempotencyKey === null) {
                continue;
            }

            $observed = $execution->retryAttempts;

            $claimed = $executions->claimFailedPollExecution(
                (int) $execution->id,
                $observed,
                self::MAX_RETRIES,
            );

            if (! $claimed) {
                continue;
            }

            $nextAttempt = $observed + 1;
            $retryKey = $execution->idempotencyKey.'#r'.$nextAttempt;

            RunWorkflowJob::dispatch(
                userId: $execution->userId,
                workflowId: $execution->workflowId,
                triggerPayload: $execution->triggerPayload ?? [],
                idempotencyKey: $retryKey,
                triggerSource: 'poll',
                retryOfId: (int) $execution->id,
            );

            Log::info('Recovered failed poll execution', [
                'original_execution_id' => $execution->id,
                'workflow_id' => $execution->workflowId,
                'retry_attempt' => $nextAttempt,
                'retry_key' => $retryKey,
            ]);

            $dispatched++;
        }

        $this->info(sprintf('Dispatched %d recovery job(s).', $dispatched));

        return self::SUCCESS;
    }
}
