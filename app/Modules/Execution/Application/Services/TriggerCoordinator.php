<?php

namespace App\Modules\Execution\Application\Services;

use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Execution\Application\DTOs\TickResult;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TriggerCoordinator
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly TriggerStrategyRegistry $strategies,
    ) {}

    public function run(DateTimeImmutable $now, int $batchSize = 50): TickResult
    {
        $due = $this->workflows->listAllActiveWithTriggerDue($now, $batchSize);

        $processed = 0;
        $executed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($due as $pair) {
            $workflow = $pair['workflow'];
            $trigger = $pair['trigger'];

            $strategy = $this->strategies->resolve($trigger->strategy);

            if ($strategy === null) {
                $skipped++;
                continue;
            }

            $processed++;

            try {
                $result = $strategy->process($workflow, $trigger, $now);

                if ($result->executed) {
                    $executed += $result->executionCount;
                } else {
                    $skipped++;
                }
            } catch (Throwable $e) {
                $failed++;

                Log::warning('Trigger processing failed', [
                    'workflow_id' => $workflow->id,
                    'strategy' => $trigger->strategy,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return new TickResult(
            processedCount: $processed,
            executedCount: $executed,
            skippedCount: $skipped,
            failedCount: $failed,
        );
    }
}
