<?php

namespace App\Modules\Automation\Application\Actions;

use App\Modules\Automation\Application\DTOs\ListWorkflowsInput;
use App\Modules\Automation\Application\DTOs\WorkflowData;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;

final class ListWorkflowsAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
    ) {}

    /**
     * @return array<int, WorkflowData>
     */
    public function execute(ListWorkflowsInput $input): array
    {
        $items = $this->workflows->listForUser($input->userId, onlyTrashed: $input->onlyTrashed);

        return array_map(
            static fn ($workflow) => WorkflowData::fromEntity($workflow),
            $items,
        );
    }
}
