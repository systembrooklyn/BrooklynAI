<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\StepData;
use App\Modules\Automation\Application\DTOs\TriggerData;
use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;

final class ShowWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    public function execute(WorkflowIdInput $input): WorkflowData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $trigger = $this->workflows->findTriggerForWorkflow($input->workflowId);
        $steps = $this->workflows->listStepsForWorkflow($input->workflowId);

        return WorkflowData::fromEntityWithDefinition(
            workflow: $workflow,
            trigger: $trigger !== null ? TriggerData::fromEntity($trigger) : null,
            steps: array_map(static fn ($step) => StepData::fromEntity($step), $steps),
        );
    }
}
