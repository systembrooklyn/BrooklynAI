<?php

namespace Tests\Unit\Execution;

use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Execution\Application\Services\WorkflowSnapshotBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class WorkflowSnapshotBuilderTest extends TestCase
{
    private function workflow(): Workflow
    {
        $now = new DateTimeImmutable;

        return new Workflow(
            id: 10,
            userId: 1,
            name: 'Snapshot',
            description: 'desc',
            status: WorkflowStatus::Active,
            deletedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public function test_builder_projects_only_whitelisted_workflow_fields(): void
    {
        $builder = new WorkflowSnapshotBuilder;

        $snapshot = $builder->build($this->workflow(), null, []);

        $this->assertSame(['id', 'name', 'description'], array_keys($snapshot['workflow']));
        $this->assertSame(10, $snapshot['workflow']['id']);
        $this->assertNull($snapshot['trigger']);
        $this->assertSame([], $snapshot['steps']);
    }

    public function test_builder_includes_trigger_when_present(): void
    {
        $builder = new WorkflowSnapshotBuilder;
        $now = new DateTimeImmutable;

        $trigger = new WorkflowTrigger(
            id: 1,
            workflowId: 10,
            integrationKey: 'google.gmail',
            triggerKey: 'new_email_received',
            connectionId: 5,
            strategy: 'poll',
            config: ['labels' => ['INBOX']],
            intervalMinutes: 5,
            createdAt: $now,
            updatedAt: $now,
        );

        $snapshot = $builder->build($this->workflow(), $trigger, []);

        $this->assertSame([
            'integration_key',
            'trigger_key',
            'connection_id',
            'strategy',
            'config',
            'interval_minutes',
        ], array_keys($snapshot['trigger']));
    }

    public function test_builder_projects_only_whitelisted_step_fields(): void
    {
        $builder = new WorkflowSnapshotBuilder;
        $now = new DateTimeImmutable;

        $step = new WorkflowStep(
            id: 7,
            workflowId: 10,
            position: 1,
            integrationKey: 'google.gmail',
            actionKey: 'send_email',
            connectionId: 5,
            config: ['to' => 'a@example.com'],
            createdAt: $now,
            updatedAt: $now,
        );

        $snapshot = $builder->build($this->workflow(), null, [$step]);

        $this->assertCount(1, $snapshot['steps']);
        $this->assertSame(
            ['position', 'integration_key', 'action_key', 'connection_id', 'config'],
            array_keys($snapshot['steps'][0]),
        );
    }
}
