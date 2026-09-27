<?php

namespace App\Modules\Automation\Application\DTOs;

use App\Modules\Automation\Core\Entities\WorkflowTrigger;

final class TriggerData
{
    public function __construct(
        public readonly int $id,
        public readonly string $integrationKey,
        public readonly string $triggerKey,
        public readonly ?int $connectionId,
        public readonly string $strategy,
        public readonly array $config,
        public readonly ?int $intervalMinutes,
    ) {}

    public static function fromEntity(WorkflowTrigger $trigger): self
    {
        return new self(
            id: (int) $trigger->id,
            integrationKey: $trigger->integrationKey,
            triggerKey: $trigger->triggerKey,
            connectionId: $trigger->connectionId,
            strategy: $trigger->strategy,
            config: $trigger->config,
            intervalMinutes: $trigger->intervalMinutes,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'integration_key' => $this->integrationKey,
            'trigger_key' => $this->triggerKey,
            'connection_id' => $this->connectionId,
            'strategy' => $this->strategy,
            // Empty trigger config must serialize as JSON `{}`, not `[]`,
            // to match the mobile contract §5.7 and the Catalog response shape.
            'config' => $this->config === [] ? (object) [] : $this->config,
            'interval_minutes' => $this->intervalMinutes,
        ];
    }
}
