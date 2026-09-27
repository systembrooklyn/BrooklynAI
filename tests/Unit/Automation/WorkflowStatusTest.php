<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use PHPUnit\Framework\TestCase;

class WorkflowStatusTest extends TestCase
{
    public function test_enum_has_expected_values(): void
    {
        $this->assertSame('draft', WorkflowStatus::Draft->value);
        $this->assertSame('active', WorkflowStatus::Active->value);
        $this->assertSame('paused', WorkflowStatus::Paused->value);
    }

    public function test_values_returns_all_case_values(): void
    {
        $this->assertSame(['draft', 'active', 'paused'], WorkflowStatus::values());
    }

    public function test_is_helpers(): void
    {
        $this->assertTrue(WorkflowStatus::Draft->isDraft());
        $this->assertTrue(WorkflowStatus::Active->isActive());
        $this->assertTrue(WorkflowStatus::Paused->isPaused());
    }

    public function test_valid_transitions(): void
    {
        $this->assertTrue(WorkflowStatus::Draft->canTransitionTo(WorkflowStatus::Active));
        $this->assertTrue(WorkflowStatus::Active->canTransitionTo(WorkflowStatus::Paused));
        $this->assertTrue(WorkflowStatus::Paused->canTransitionTo(WorkflowStatus::Active));
    }

    public function test_invalid_transitions(): void
    {
        $this->assertFalse(WorkflowStatus::Draft->canTransitionTo(WorkflowStatus::Paused));
        $this->assertFalse(WorkflowStatus::Active->canTransitionTo(WorkflowStatus::Active));
        $this->assertFalse(WorkflowStatus::Paused->canTransitionTo(WorkflowStatus::Draft));
    }
}
