<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\TriggerData;
use App\Modules\Automation\Application\DTOs\UpsertWorkflowTriggerInput;
use App\Modules\Automation\Application\Services\WorkflowTriggerActionCompatibilityValidator;
use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use DateTimeImmutable;
use InvalidArgumentException;

final class UpsertWorkflowTriggerAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ConnectionRepository $connections,
        private readonly IntegrationCatalog $catalog,
        private readonly TemplateResolver $templates,
        private readonly WorkflowTriggerActionCompatibilityValidator $compatibility,
    ) {}

    public function execute(UpsertWorkflowTriggerInput $input): TriggerData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $integration = $this->catalog->find($input->integrationKey);

        if ($integration === null) {
            throw new InvalidArgumentException('Unknown integration.');
        }

        $triggerDef = null;

        foreach ($integration->triggers as $candidate) {
            if ($candidate->key === $input->triggerKey) {
                $triggerDef = $candidate;
                break;
            }
        }

        if ($triggerDef === null) {
            throw new InvalidArgumentException('Unknown trigger for this integration.');
        }

        // Trigger/action compatibility: every existing step must remain
        // valid under the new trigger. Checked before persisting so a failed
        // replacement leaves the previous trigger intact.
        foreach ($this->workflows->listStepsForWorkflow($input->workflowId) as $step) {
            $this->compatibility->validate(
                location: 'step:'.$step->position,
                actionIntegrationKey: $step->integrationKey,
                actionKey: $step->actionKey,
                triggerIntegrationKey: $input->integrationKey,
                triggerKey: $input->triggerKey,
            );
        }

        if ($input->connectionId !== null) {
            $connection = $this->connections->findForUser($input->userId, $input->connectionId);

            if ($connection === null) {
                throw ConnectionNotFoundException::forUser($input->userId, $input->connectionId);
            }
        }

        $this->templates->validateSyntax($input->config);

        $existing = $this->workflows->findTriggerForWorkflow($input->workflowId);
        $now = new DateTimeImmutable;

        $trigger = new WorkflowTrigger(
            id: $existing?->id,
            workflowId: $input->workflowId,
            integrationKey: $input->integrationKey,
            triggerKey: $input->triggerKey,
            connectionId: $input->connectionId,
            strategy: $triggerDef->strategy->value,
            config: $input->config,
            intervalMinutes: $input->intervalMinutes,
            createdAt: $existing?->createdAt ?? $now,
            updatedAt: $now,
            pollCursor: $existing?->pollCursor,
            nextPollAt: $existing?->nextPollAt,
        );

        $saved = $this->workflows->saveTrigger($trigger);

        return TriggerData::fromEntity($saved);
    }
}
