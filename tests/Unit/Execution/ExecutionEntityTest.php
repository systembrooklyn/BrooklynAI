<?php

namespace Tests\Unit\Execution;

use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\Entities\ExecutionStep;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ExecutionEntityTest extends TestCase
{
    public function test_execution_defaults(): void
    {
        $now = new DateTimeImmutable;

        $execution = new Execution(
            id: null,
            workflowId: 1,
            userId: 1,
            status: ExecutionStatus::Pending,
            triggerSource: 'test',
            triggerPayload: [],
            workflowSnapshot: [],
            idempotencyKey: null,
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->assertNull($execution->id);
        $this->assertSame(ExecutionStatus::Pending, $execution->status);
        $this->assertNull($execution->startedAt);
    }

    public function test_execution_step_defaults(): void
    {
        $now = new DateTimeImmutable;

        $step = new ExecutionStep(
            id: null,
            executionId: 1,
            position: 1,
            stepSnapshot: [],
            status: ExecutionStepStatus::Pending,
            input: null,
            output: null,
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->assertNull($step->id);
        $this->assertSame(ExecutionStepStatus::Pending, $step->status);
    }
}
