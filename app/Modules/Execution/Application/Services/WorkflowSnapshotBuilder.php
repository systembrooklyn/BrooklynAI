<?php

namespace App\Modules\Execution\Application\Services;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;

final class WorkflowSnapshotBuilder
{
    /**
     * Explicit whitelist projection. No casts, no reflection,
     * no iteration over entity fields. Every key is written by hand.
     *
     * @param  array<int, WorkflowStep>  $steps
     * @return array<string, mixed>
     */
    public function build(Workflow $workflow, ?WorkflowTrigger $trigger, array $steps): array
    {
        return [
            'workflow' => [
                'id' => (int) $workflow->id,
                'name' => $workflow->name,
                'description' => $workflow->description,
            ],
            'trigger' => $trigger !== null ? $this->triggerSnapshot($trigger) : null,
            'steps' => array_map(
                static fn (WorkflowStep $step) => [
                    'position' => $step->position,
                    'integration_key' => $step->integrationKey,
                    'action_key' => $step->actionKey,
                    'connection_id' => $step->connectionId,
                    'config' => $step->config,
                ],
                array_values($steps),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stepSnapshot(WorkflowStep $step): array
    {
        return [
            'position' => $step->position,
            'integration_key' => $step->integrationKey,
            'action_key' => $step->actionKey,
            'connection_id' => $step->connectionId,
            'config' => $step->config,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function triggerSnapshot(WorkflowTrigger $trigger): array
    {
        return [
            'integration_key' => $trigger->integrationKey,
            'trigger_key' => $trigger->triggerKey,
            'connection_id' => $trigger->connectionId,
            'strategy' => $trigger->strategy,
            'config' => $trigger->config,
            'interval_minutes' => $trigger->intervalMinutes,
        ];
    }
}
