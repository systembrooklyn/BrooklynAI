<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\CreateWorkflowInput;
use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use DateTimeImmutable;

final class CreateWorkflowAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    public function execute(CreateWorkflowInput $input): WorkflowData
    {
        $now = new DateTimeImmutable;

        $workflow = new Workflow(
            id: null,
            userId: $input->userId,
            name: $input->name,
            description: $input->description,
            status: WorkflowStatus::Draft,
            deletedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $saved = $this->workflows->save($workflow);

        return WorkflowData::fromEntity($saved);
    }
}
