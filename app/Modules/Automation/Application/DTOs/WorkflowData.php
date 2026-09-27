<?php

namespace App\Modules\Automation\Application\DTOs;

use App\Modules\Automation\Core\Entities\Workflow;
use DateTimeInterface;

final class WorkflowData
{
    /**
     * @param  array<int, StepData>  $steps
     */
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $status,
        public readonly string $createdAt,
        public readonly string $updatedAt,
        public readonly ?string $deletedAt,
        public readonly ?TriggerData $trigger = null,
        public readonly array $steps = [],
    ) {}

    public static function fromEntity(Workflow $workflow): self
    {
        return new self(
            id: (int) $workflow->id,
            name: $workflow->name,
            description: $workflow->description,
            status: $workflow->status->value,
            createdAt: $workflow->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $workflow->updatedAt->format(DateTimeInterface::ATOM),
            deletedAt: $workflow->deletedAt?->format(DateTimeInterface::ATOM),
        );
    }

    /**
     * @param  array<int, StepData>  $steps
     */
    public static function fromEntityWithDefinition(
        Workflow $workflow,
        ?TriggerData $trigger,
        array $steps,
    ): self {
        return new self(
            id: (int) $workflow->id,
            name: $workflow->name,
            description: $workflow->description,
            status: $workflow->status->value,
            createdAt: $workflow->createdAt->format(DateTimeInterface::ATOM),
            updatedAt: $workflow->updatedAt->format(DateTimeInterface::ATOM),
            deletedAt: $workflow->deletedAt?->format(DateTimeInterface::ATOM),
            trigger: $trigger,
            steps: $steps,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'deleted_at' => $this->deletedAt,
            'trigger' => $this->trigger?->toArray(),
            'steps' => array_map(static fn (StepData $s) => $s->toArray(), $this->steps),
        ];
    }
}
