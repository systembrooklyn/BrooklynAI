<?php

namespace App\Modules\Execution\Application\Contracts;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Execution\Application\DTOs\StrategyResult;
use DateTimeImmutable;

interface TriggerStrategy
{
    public function strategyKey(): string;

    public function process(
        Workflow $workflow,
        WorkflowTrigger $trigger,
        DateTimeImmutable $now,
    ): StrategyResult;
}
