<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\AddWorkflowStepInput;
use App\Modules\Automation\Application\DTOs\StepData;
use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use DateTimeImmutable;
use InvalidArgumentException;

final class AddWorkflowStepAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ConnectionRepository $connections,
        private readonly IntegrationCatalog $catalog,
        private readonly TemplateResolver $templates,
    ) {}

    public function execute(AddWorkflowStepInput $input): StepData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
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

        $steps = $this->workflows->listStepsForWorkflow($input->workflowId);

        $nextPosition = 1;

        if (! empty($steps)) {
            $positions = array_map(static fn (WorkflowStep $s) => $s->position, $steps);
            $nextPosition = max($positions) + 1;
        }

        $now = new DateTimeImmutable;

        $step = new WorkflowStep(
            id: null,
            workflowId: $input->workflowId,
            position: $nextPosition,
            integrationKey: $input->integrationKey,
            actionKey: $input->actionKey,
            connectionId: $input->connectionId,
            config: $input->config,
            createdAt: $now,
            updatedAt: $now,
        );

        $saved = $this->workflows->saveStep($step);

        return StepData::fromEntity($saved);
    }
}
