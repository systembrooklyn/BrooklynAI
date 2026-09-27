<?php

namespace App\Modules\Execution\Application\DTOs;

use App\Modules\Execution\Core\Entities\Execution;
use DateTimeInterface;

final class ExecutionData
{
    /**
     * @param  array<string, mixed>  $workflowSnapshot
     * @param  array<int, ExecutionStepData>  $steps
     */
    public function __construct(
        public readonly int $id,
        public readonly int $workflowId,
        public readonly string $status,
        public readonly string $triggerSource,
        public readonly ?array $triggerPayload,
        public readonly array $workflowSnapshot,
        public readonly ?string $idempotencyKey,
        public readonly ?string $errorMessage,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly array $steps = [],
    ) {}

    /**
     * @param  array<int, ExecutionStepData>  $steps
     */
    public static function fromEntity(Execution $execution, array $steps = []): self
    {
        return new self(
            id: (int) $execution->id,
            workflowId: $execution->workflowId,
            status: $execution->status->value,
            triggerSource: $execution->triggerSource,
            triggerPayload: $execution->triggerPayload,
            workflowSnapshot: $execution->workflowSnapshot,
            idempotencyKey: $execution->idempotencyKey,
            errorMessage: $execution->errorMessage,
            startedAt: $execution->startedAt?->format(DateTimeInterface::ATOM),
            finishedAt: $execution->finishedAt?->format(DateTimeInterface::ATOM),
            createdAt: $execution->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $execution->updatedAt->format(DateTimeInterface::ATOM),
            steps: $steps,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflowId,
            'status' => $this->status,
            'trigger_source' => $this->triggerSource,
            'trigger_payload' => $this->triggerPayload,
            'idempotency_key' => $this->idempotencyKey,
            'error_message' => $this->errorMessage,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'steps' => array_map(static fn (ExecutionStepData $s) => $s->toArray(), $this->steps),
        ];
    }
}
