<?php

namespace App\Modules\Execution\Application\Actions;

use App\Modules\Automation\Core\Exceptions\WorkflowNotFoundException;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Execution\Application\DTOs\ExecutionData;
use App\Modules\Execution\Application\DTOs\ListExecutionsInput;
use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;

final class ListExecutionsAction
{
    public function __construct(
        private readonly WorkflowRepository $workflows,
        private readonly ExecutionRepository $executions,
    ) {}

    /**
     * @return array<int, ExecutionData>
     */
    public function execute(ListExecutionsInput $input): array
    {
        $workflow = $this->workflows->findForUser($input->userId, $input->workflowId);

        if ($workflow === null) {
            throw WorkflowNotFoundException::forUser($input->userId, $input->workflowId);
        }

        $items = $this->executions->listForWorkflow($input->workflowId, $input->limit);

        return array_map(
            static fn (Execution $e) => ExecutionData::fromEntity($e),
            $items,
        );
    }
}
