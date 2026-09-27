<?php

namespace Tests\Unit\Execution;

use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use App\Modules\Execution\Core\ValueObjects\ExecutionStepStatus;
use PHPUnit\Framework\TestCase;

class ExecutionStatusTest extends TestCase
{
    public function test_execution_status_values(): void
    {
        $this->assertSame('pending', ExecutionStatus::Pending->value);
        $this->assertSame('running', ExecutionStatus::Running->value);
        $this->assertSame('completed', ExecutionStatus::Completed->value);
        $this->assertSame('failed', ExecutionStatus::Failed->value);
    }

    public function test_is_terminal(): void
    {
        $this->assertFalse(ExecutionStatus::Pending->isTerminal());
        $this->assertFalse(ExecutionStatus::Running->isTerminal());
        $this->assertTrue(ExecutionStatus::Completed->isTerminal());
        $this->assertTrue(ExecutionStatus::Failed->isTerminal());
    }

    public function test_is_in_progress(): void
    {
        $this->assertTrue(ExecutionStatus::Pending->isInProgress());
        $this->assertTrue(ExecutionStatus::Running->isInProgress());
        $this->assertFalse(ExecutionStatus::Completed->isInProgress());
        $this->assertFalse(ExecutionStatus::Failed->isInProgress());
    }

    public function test_step_status_values(): void
    {
        $this->assertSame('pending', ExecutionStepStatus::Pending->value);
        $this->assertSame('running', ExecutionStepStatus::Running->value);
        $this->assertSame('completed', ExecutionStepStatus::Completed->value);
        $this->assertSame('failed', ExecutionStepStatus::Failed->value);
        $this->assertSame('skipped', ExecutionStepStatus::Skipped->value);
    }

    public function test_step_status_is_terminal(): void
    {
        $this->assertFalse(ExecutionStepStatus::Pending->isTerminal());
        $this->assertFalse(ExecutionStepStatus::Running->isTerminal());
        $this->assertTrue(ExecutionStepStatus::Completed->isTerminal());
        $this->assertTrue(ExecutionStepStatus::Failed->isTerminal());
        $this->assertTrue(ExecutionStepStatus::Skipped->isTerminal());
    }
}
