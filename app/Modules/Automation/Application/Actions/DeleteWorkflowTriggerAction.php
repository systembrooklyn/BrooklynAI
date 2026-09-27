<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Exceptions\TriggerNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;

final class DeleteWorkflowTriggerAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    public function execute(WorkflowIdInput $input): void
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $trigger = $this->workflows->findTriggerForWorkflow($input->workflowId);

        if ($trigger === null) {
            throw TriggerNotFoundException::forWorkflow($input->workflowId);
        }

        $this->workflows->deleteTrigger($trigger);
    }
}
