<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\WorkflowStepPositionInput;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowStepNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;

final class DeleteWorkflowStepAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    public function execute(WorkflowStepPositionInput $input): void
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $existing = $this->findStepAtPosition($input->workflowId, $input->position);

        if ($existing === null) {
            throw WorkflowStepNotFoundException::forPosition($input->workflowId, $input->position);
        }

        $this->workflows->deleteStep($existing);
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
