<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class WorkflowEntityTest extends TestCase
{
    public function test_new_workflow_has_null_id_and_null_deleted_at(): void
    {
        $workflow = new Workflow(
            id: null,
            userId: 1,
            name: 'Test',
            description: null,
            status: WorkflowStatus::Draft,
            deletedAt: null,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );

        $this->assertNull($workflow->id);
        $this->assertFalse($workflow->isDeleted());
    }

    public function test_deleted_workflow_reports_as_deleted(): void
    {
        $now = new DateTimeImmutable;

        $workflow = new Workflow(
            id: 5,
            userId: 1,
            name: 'Test',
            description: null,
            status: WorkflowStatus::Active,
            deletedAt: $now,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->assertTrue($workflow->isDeleted());
    }
}
