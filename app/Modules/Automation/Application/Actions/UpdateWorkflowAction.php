<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\UpdateWorkflowInput;
use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use DateTimeImmutable;

final class UpdateWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    public function execute(UpdateWorkflowInput $input): WorkflowData
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $updated = new Workflow(
            id: $workflow->id,
            userId: $workflow->userId,
            name: $input->name,
            description: $input->description,
            status: $workflow->status,
            deletedAt: $workflow->deletedAt,
            createdAt: $workflow->createdAt,
            updatedAt: new DateTimeImmutable,
        );

        $saved = $this->workflows->save($updated);

        return WorkflowData::fromEntity($saved);
    }
}
