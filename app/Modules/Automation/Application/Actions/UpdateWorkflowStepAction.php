<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\StepData;
use App\Modules\Automation\Application\DTOs\UpdateWorkflowStepInput;
use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowStepNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use DateTimeImmutable;
use InvalidArgumentException;

final class UpdateWorkflowStepAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ConnectionRepository $connections,
        private readonly IntegrationCatalog $catalog,
        private readonly TemplateResolver $templates,
    ) {}

    public function execute(UpdateWorkflowStepInput $input): StepData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $existing = $this->findStepAtPosition($input->workflowId, $input->position);

        if ($existing === null) {
            throw WorkflowStepNotFoundException::forPosition($input->workflowId, $input->position);
        }

        $integration = $this->catalog->find($input->integrationKey);

        if ($integration === null) {
            throw new InvalidArgumentException('Unknown integration.');
        }

        $actionExists = false;

        foreach ($integration->actions as $candidate) {
            if ($candidate->key === $input->actionKey) {
                $actionExists = true;
                break;
            }
        }

        if (! $actionExists) {
            throw new InvalidArgumentException('Unknown action for this integration.');
        }

        if ($input->connectionId !== null) {
            $connection = $this->connections->findForUser($input->userId, $input->connectionId);

            if ($connection === null) {
                throw ConnectionNotFoundException::forUser($input->userId, $input->connectionId);
            }
        }

        $this->templates->validateSyntax($input->config);

        $updated = new WorkflowStep(
            id: $existing->id,
            workflowId: $existing->workflowId,
            position: $existing->position,
            integrationKey: $input->integrationKey,
            actionKey: $input->actionKey,
            connectionId: $input->connectionId,
            config: $input->config,
            createdAt: $existing->createdAt,
            updatedAt: new DateTimeImmutable,
        );

        $saved = $this->workflows->saveStep($updated);

        return StepData::fromEntity($saved);
    }

    private function findStepAtPosition(int $workflowId, int $position): ?WorkflowStep
    {
        foreach ($this->workflows->listStepsForWorkflow($workflowId) as $step) {
            if ($step->position === $position) {
                return $step;
            }
        }

        return null;
    }
}
