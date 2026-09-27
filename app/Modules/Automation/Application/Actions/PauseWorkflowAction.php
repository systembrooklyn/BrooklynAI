<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Exceptions\WorkflowNotRunnableException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use DateTimeImmutable;

final class PauseWorkflowAction
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

        $target = WorkflowStatus::Paused;

        if (! $workflow->status->canTransitionTo($target)) {
            throw WorkflowNotRunnableException::forTransition(
                (int) $workflow->id,
                $workflow->status,
                $target,
            );
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

        return WorkflowData::fromEntity($this->workflows->save($updated));
    }
}
