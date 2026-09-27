<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;

final class RestoreWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    public function execute(WorkflowIdInput $input): WorkflowData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId, includeTrashed: true);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $this->workflows->restore($workflow);

        $restored = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($restored === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        return WorkflowData::fromEntity($restored);
    }
}
