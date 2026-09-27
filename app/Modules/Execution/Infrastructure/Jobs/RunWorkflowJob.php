<?php

namespace App\Modules\Execution\Infrastructure\Jobs;

use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\DTOs\RunWorkflowInput;
use App\Modules\Execution\Core\Exceptions\WorkflowNotExecutableException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class RunWorkflowJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Worker attempts per queued job.
     * This is NOT the total budget across recovery cycles.
     */
    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 600];

    /**
     * Cache lock TTL. Held while the job is queued or processing.
     * Prevents duplicate dispatch of the same logical Gmail event.
     */
    public int $uniqueFor = 3600;

    /**
     * @param  array<string, mixed>  $triggerPayload
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $workflowId,
        public readonly array $triggerPayload,
        public readonly ?string $idempotencyKey,
        public readonly string $triggerSource = 'poll',
        public readonly ?int $retryOfId = null,
    ) {}

    public function uniqueId(): string
    {
        if ($this->idempotencyKey !== null && $this->idempotencyKey !== '') {
            return 'run-workflow:'.$this->workflowId.':'.$this->idempotencyKey;
        }

        return 'run-workflow:'.$this->workflowId.':'.Str::uuid()->toString();
    }

    public function handle(RunWorkflowAction $action): void
    {
        try {
            $action->execute(new RunWorkflowInput(
                userId: $this->userId,
                workflowId: $this->workflowId,
                triggerPayload: $this->triggerPayload,
                idempotencyKey: $this->idempotencyKey,
                triggerSource: $this->triggerSource,
                retryOfId: $this->retryOfId,
            ));
        } catch (WorkflowNotFoundException|WorkflowNotExecutableException $e) {
            Log::warning('RunWorkflowJob: skipped permanently', [
                'workflow_id' => $this->workflowId,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        // ExecutionAlreadyRunningException is deliberately NOT caught.
        // It is thrown before an Execution row is created. Swallowing it
        // would lose the Gmail event. Letting it bubble allows the queue
        // to retry the job.
    }
}
