<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Application\Services\WorkflowCapabilityValidator;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotRunnableException;
use App\Modules\Automation\Core\Exceptions\WorkflowRequiresTriggerException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use DateTimeImmutable;

final class ActivateWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly IntegrationCatalog $catalog,
        private readonly WorkflowCapabilityValidator $capabilities,
    ) {}

    public function execute(WorkflowIdInput $input): WorkflowData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $trigger = $this->workflows->findTriggerForWorkflow($input->workflowId);

        if ($trigger === null) {
            throw WorkflowRequiresTriggerException::forWorkflow($input->workflowId);
        }

        $target = WorkflowStatus::Active;

        if (! $workflow->status->canTransitionTo($target)) {
            throw WorkflowNotRunnableException::forTransition(
                (int) $workflow->id,
                $workflow->status,
                $target,
            );
        }

        // Phase 7.4b: validate trigger + steps BEFORE persisting the state change.
        $this->validateTrigger($trigger, $input->userId);

        foreach ($this->workflows->listStepsForWorkflow($input->workflowId) as $step) {
            $this->validateStep($step, $input->userId);
        }

        $updated = new Workflow(
            id: $workflow->id,
            userId: $workflow->userId,
            name: $workflow->name,
            description: $workflow->description,
            status: $target,
            deletedAt: $workflow->deletedAt,
            createdAt: $workflow->createdAt,
            updatedAt: new DateTimeImmutable,
        );

        $saved = $this->workflows->save($updated);

        // Reset the trigger's next_poll_at so the workflow is due on the very next
        // scheduler tick after activation. This guarantees prompt polling on resume
        // rather than waiting for a stale next_poll_at to expire.
        $currentTrigger = $this->workflows->findTriggerForWorkflow((int) $workflow->id);

        if ($currentTrigger !== null && $currentTrigger->nextPollAt !== null) {
            $resetTrigger = new WorkflowTrigger(
                id: $currentTrigger->id,
                workflowId: $currentTrigger->workflowId,
                integrationKey: $currentTrigger->integrationKey,
                triggerKey: $currentTrigger->triggerKey,
                connectionId: $currentTrigger->connectionId,
                strategy: $currentTrigger->strategy,
                config: $currentTrigger->config,
                intervalMinutes: $currentTrigger->intervalMinutes,
                createdAt: $currentTrigger->createdAt,
                updatedAt: new DateTimeImmutable,
                pollCursor: $currentTrigger->pollCursor,
                nextPollAt: null,
            );

            $this->workflows->saveTrigger($resetTrigger);
        }

        return WorkflowData::fromEntity($saved);
    }

    private function validateTrigger(WorkflowTrigger $trigger, int $userId): void
    {
        $integration = $this->catalog->find($trigger->integrationKey);

        if ($integration === null) {
            throw WorkflowCapabilityException::unknownIntegration(
                'trigger',
                $trigger->integrationKey,
            );
        }

        $definition = null;

        foreach ($integration->triggers as $candidate) {
            if ($candidate->key === $trigger->triggerKey) {
                $definition = $candidate;
                break;
            }
        }

        if ($definition === null) {
            throw WorkflowCapabilityException::unknownTrigger(
                'trigger',
                $trigger->integrationKey,
                $trigger->triggerKey,
            );
        }

        $this->capabilities->validate(
            location: 'trigger',
            capability: $definition->capability,
            requiredScopes: $this->scopeValues($definition->requiredScopes),
            connectionId: $trigger->connectionId,
            userId: $userId,
        );
    }

    private function validateStep(WorkflowStep $step, int $userId): void
    {
        $integration = $this->catalog->find($step->integrationKey);

        if ($integration === null) {
            throw WorkflowCapabilityException::unknownIntegration(
                'step:'.$step->position,
                $step->integrationKey,
            );
        }

        $definition = null;

        foreach ($integration->actions as $candidate) {
            if ($candidate->key === $step->actionKey) {
                $definition = $candidate;
                break;
            }
        }

        if ($definition === null) {
            throw WorkflowCapabilityException::unknownAction(
                'step:'.$step->position,
                $step->integrationKey,
                $step->actionKey,
            );
        }

        $this->capabilities->validate(
            location: 'step:'.$step->position,
            capability: $definition->capability,
            requiredScopes: $this->scopeValues($definition->requiredScopes),
            connectionId: $step->connectionId,
            userId: $userId,
        );
    }

    /**
     * @param  array<int, \App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier>  $scopes
     * @return array<int, string>
     */
    private function scopeValues(array $scopes): array
    {
        return array_map(static fn ($s) => $s->value, $scopes);
    }
}
