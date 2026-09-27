<?php

namespace App\Modules\Execution\Application\Actions;

use App\Modules\Execution\Application\DTOs\ExecutionData;
use App\Modules\Execution\Application\DTOs\ExecutionStepData;
use App\Modules\Execution\Application\DTOs\ShowExecutionInput;
use App\Modules\Execution\Core\Entities\ExecutionStep;
use App\Modules\Execution\Core\Exceptions\ExecutionNotFoundException;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;

final class ShowExecutionAction
{
    public function __construct(
        private readonly ExecutionRepository $executions,
    ) {}

    public function execute(ShowExecutionInput $input): ExecutionData
    {
        $execution = $this->executions->findForUser($input->userId, $input->executionId);

        if ($execution === null) {
            throw ExecutionNotFoundException::forUser($input->userId, $input->executionId);
        }

        $stepEntities = $this->executions->listStepsForExecution((int) $execution->id);

        $steps = array_map(
            static fn (ExecutionStep $s) => ExecutionStepData::fromEntity($s),
            $stepEntities,
        );

        return ExecutionData::fromEntity($execution, $steps);
    }
}
