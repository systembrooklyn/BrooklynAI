<?php

namespace App\Modules\Execution\Application\DTOs;

use App\Modules\Execution\Core\Entities\ExecutionStep;
use DateTimeInterface;

final class ExecutionStepData
{
    /**
     * @param  array<string, mixed>  $stepSnapshot
     */
    public function __construct(
        public readonly int $id,
        public readonly int $position,
        public readonly array $stepSnapshot,
        public readonly string $status,
        public readonly ?array $input,
        public readonly ?array $output,
        public readonly ?string $errorMessage,
        public readonly ?string $startedAt,
        public readonly ?string $finishedAt,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}

    public static function fromEntity(ExecutionStep $step): self
    {
        return new self(
            id: (int) $step->id,
            position: $step->position,
            stepSnapshot: $step->stepSnapshot,
            status: $step->status->value,
            input: $step->input,
            output: $step->output,
            errorMessage: $step->errorMessage,
            startedAt: $step->startedAt?->format(DateTimeInterface::ATOM),
            finishedAt: $step->finishedAt?->format(DateTimeInterface::ATOM),
            createdAt: $step->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $step->updatedAt->format(DateTimeInterface::ATOM),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'step_snapshot' => $this->stepSnapshot,
            'status' => $this->status,
            'input' => $this->input,
            'output' => $this->output,
            'error_message' => $this->errorMessage,
            'started_at' => $this->startedAt,
            'finished_at' => $this->finishedAt,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
