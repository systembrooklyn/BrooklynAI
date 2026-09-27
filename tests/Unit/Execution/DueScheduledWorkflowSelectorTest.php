<?php

namespace Tests\Unit\Execution;

use App\Modules\Execution\Application\Services\DueScheduledWorkflowSelector;
use App\Modules\Execution\Core\Entities\Execution;
use App\Modules\Execution\Core\ValueObjects\ExecutionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DueScheduledWorkflowSelectorTest extends TestCase
{
    private DueScheduledWorkflowSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new DueScheduledWorkflowSelector;
    }

    private function execution(DateTimeImmutable $startedAt, ?DateTimeImmutable $createdAt = null): Execution
    {
        return new Execution(
            id: 1,
            workflowId: 1,
            userId: 1,
            status: ExecutionStatus::Completed,
            triggerSource: 'schedule',
            triggerPayload: [],
            workflowSnapshot: [],
            idempotencyKey: 'schedule:1:first',
            errorMessage: null,
            startedAt: $startedAt,
            finishedAt: $startedAt->modify('+1 minute'),
            createdAt: $createdAt ?? $startedAt,
            updatedAt: $startedAt,
        );
    }

    public function test_first_run_is_due_when_no_prior_execution(): void
    {
        $this->assertTrue($this->selector->isDue(null, 30, new DateTimeImmutable));
    }

    public function test_not_due_when_last_run_plus_interval_in_future(): void
    {
        $now = new DateTimeImmutable('2026-09-20 10:00:00');
        $last = $this->execution($now->modify('-5 minutes'));

        $this->assertFalse($this->selector->isDue($last, 30, $now));
    }

    public function test_due_when_last_run_plus_interval_equals_now(): void
    {
        $now = new DateTimeImmutable('2026-09-20 10:00:00');
        $last = $this->execution($now->modify('-30 minutes'));

        $this->assertTrue($this->selector->isDue($last, 30, $now));
    }

    public function test_due_when_last_run_plus_interval_in_past(): void
    {
        $now = new DateTimeImmutable('2026-09-20 10:00:00');
        $last = $this->execution($now->modify('-45 minutes'));

        $this->assertTrue($this->selector->isDue($last, 30, $now));
    }

    public function test_null_interval_marks_not_due(): void
    {
        $this->assertFalse($this->selector->isDue(null, 0, new DateTimeImmutable));
    }

    public function test_zero_or_negative_interval_marks_not_due(): void
    {
        $this->assertFalse($this->selector->isDue(null, -1, new DateTimeImmutable));
    }

    public function test_fallback_to_created_at_when_started_at_is_null(): void
    {
        $now = new DateTimeImmutable('2026-09-20 10:00:00');

        $execution = new Execution(
            id: 1,
            workflowId: 1,
            userId: 1,
            status: ExecutionStatus::Pending,
            triggerSource: 'schedule',
            triggerPayload: [],
            workflowSnapshot: [],
            idempotencyKey: 'schedule:1:first',
            errorMessage: null,
            startedAt: null,
            finishedAt: null,
            createdAt: $now->modify('-45 minutes'),
            updatedAt: $now->modify('-45 minutes'),
        );

        $this->assertTrue($this->selector->isDue($execution, 30, $now));
    }

    public function test_due_at_returns_started_at_plus_interval(): void
    {
        $start = new DateTimeImmutable('2026-09-20 10:00:00');
        $last = $this->execution($start);

        $dueAt = $this->selector->dueAt($last, 30);

        $this->assertNotNull($dueAt);
        $this->assertSame('2026-09-20 10:30:00', $dueAt->format('Y-m-d H:i:s'));
    }

    public function test_due_at_returns_null_for_no_prior_execution(): void
    {
        $this->assertNull($this->selector->dueAt(null, 30));
    }

    public function test_due_at_returns_null_for_zero_interval(): void
    {
        $this->assertNull($this->selector->dueAt(null, 0));
    }
}
