<?php

namespace App\Modules\Automation\Application\DTOs;

use App\Modules\Automation\Core\Entities\WorkflowStep;

final class StepData
{
    public function __construct(
        public readonly int $id,
        public readonly int $position,
        public readonly string $integrationKey,
        public readonly string $actionKey,
        public readonly ?int $connectionId,
        public readonly array $config,
    ) {}

    public static function fromEntity(WorkflowStep $step): self
    {
        return new self(
            id: (int) $step->id,
            position: $step->position,
            integrationKey: $step->integrationKey,
            actionKey: $step->actionKey,
            connectionId: $step->connectionId,
            config: $step->config,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'integration_key' => $this->integrationKey,
            'action_key' => $this->actionKey,
            'connection_id' => $this->connectionId,
            'config' => $this->config,
        ];
    }
}
