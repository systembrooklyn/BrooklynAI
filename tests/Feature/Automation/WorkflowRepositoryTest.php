<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowStep;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private WorkflowRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(WorkflowRepository::class);
    }

    private function newWorkflow(User $user, string $name = 'Test'): Workflow
    {
        $now = new DateTimeImmutable;

        return new Workflow(
            id: null,
            userId: (int) $user->id,
            name: $name,
            description: null,
            status: WorkflowStatus::Draft,
            deletedAt: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function makeActive(Workflow $workflow): Workflow
    {
        return new Workflow(
            id: $workflow->id,
            userId: $workflow->userId,
            name: $workflow->name,
            description: $workflow->description,
            status: WorkflowStatus::Active,
            deletedAt: null,
            createdAt: $workflow->createdAt,
            updatedAt: new DateTimeImmutable,
        );
    }

    public function test_save_and_find_workflow(): void
    {
        $user = User::factory()->create();
        $saved = $this->repo->save($this->newWorkflow($user));

        $this->assertNotNull($saved->id);

        $found = $this->repo->findForUser((int) $user->id, (int) $saved->id);
        $this->assertNotNull($found);
        $this->assertSame('Test', $found->name);
        $this->assertSame(WorkflowStatus::Draft, $found->status);
    }

    public function test_find_does_not_return_other_users_workflow(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $saved = $this->repo->save($this->newWorkflow($owner));

        $found = $this->repo->findForUser((int) $attacker->id, (int) $saved->id);
        $this->assertNull($found);
    }

    public function test_soft_delete_hides_workflow_by_default(): void
    {
        $user = User::factory()->create();
        $saved = $this->repo->save($this->newWorkflow($user));

        $this->repo->softDelete($saved);

        $this->assertNull($this->repo->findForUser((int) $user->id, (int) $saved->id));
        $this->assertSame([], $this->repo->listForUser((int) $user->id));
    }

    public function test_soft_delete_workflow_is_visible_when_requested(): void
    {
        $user = User::factory()->create();
        $saved = $this->repo->save($this->newWorkflow($user));
        $this->repo->softDelete($saved);

        $found = $this->repo->findForUser((int) $user->id, (int) $saved->id, includeTrashed: true);
        $this->assertNotNull($found);
        $this->assertTrue($found->isDeleted());

        $trashed = $this->repo->listForUser((int) $user->id, onlyTrashed: true);
        $this->assertCount(1, $trashed);
    }

    public function test_restore_brings_workflow_back(): void
    {
        $user = User::factory()->create();
        $saved = $this->repo->save($this->newWorkflow($user));
        $this->repo->softDelete($saved);
        $this->repo->restore($saved);

        $found = $this->repo->findForUser((int) $user->id, (int) $saved->id);
        $this->assertNotNull($found);
        $this->assertFalse($found->isDeleted());
    }

    public function test_trigger_is_unique_per_workflow(): void
    {
        $user = User::factory()->create();
        $workflow = $this->repo->save($this->newWorkflow($user));
        $now = new DateTimeImmutable;

        $trigger = new WorkflowTrigger(
            id: null,
            workflowId: (int) $workflow->id,
            integrationKey: 'google.gmail',
            triggerKey: 'new_email_received',
            connectionId: null,
            strategy: 'schedule',
            config: [],
            intervalMinutes: 5,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->saveTrigger($trigger);

        $this->expectException(QueryException::class);

        $second = new WorkflowTrigger(
            id: null,
            workflowId: (int) $workflow->id,
            integrationKey: 'google.sheets',
            triggerKey: 'rows_updated',
            connectionId: null,
            strategy: 'schedule',
            config: [],
            intervalMinutes: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->saveTrigger($second);
    }

    public function test_step_position_is_unique_per_workflow(): void
    {
        $user = User::factory()->create();
        $workflow = $this->repo->save($this->newWorkflow($user));
        $now = new DateTimeImmutable;

        $step = new WorkflowStep(
            id: null,
            workflowId: (int) $workflow->id,
            position: 1,
            integrationKey: 'google.sheets',
            actionKey: 'append_row_by_headers',
            connectionId: null,
            config: [],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->saveStep($step);

        $this->expectException(QueryException::class);

        $dup = new WorkflowStep(
            id: null,
            workflowId: (int) $workflow->id,
            position: 1,
            integrationKey: 'google.sheets',
            actionKey: 'clear_data',
            connectionId: null,
            config: [],
            createdAt: $now,
            updatedAt: $now,
        );

        $this->repo->saveStep($dup);
    }

    public function test_step_position_gaps_are_allowed(): void
    {
        $user = User::factory()->create();
        $workflow = $this->repo->save($this->newWorkflow($user));
        $now = new DateTimeImmutable;

        $this->repo->saveStep(new WorkflowStep(
            id: null, workflowId: (int) $workflow->id, position: 1,
            integrationKey: 'google.sheets', actionKey: 'clear_data',
            connectionId: null, config: [], createdAt: $now, updatedAt: $now,
        ));
        $this->repo->saveStep(new WorkflowStep(
            id: null, workflowId: (int) $workflow->id, position: 4,
            integrationKey: 'google.sheets', actionKey: 'clear_data',
            connectionId: null, config: [], createdAt: $now, updatedAt: $now,
        ));

        $steps = $this->repo->listStepsForWorkflow((int) $workflow->id);
        $this->assertCount(2, $steps);
        $this->assertSame(1, $steps[0]->position);
        $this->assertSame(4, $steps[1]->position);
    }

    public function test_list_active_with_trigger_filters_by_integration_and_trigger_keys(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $now = new DateTimeImmutable;

        $primary = $this->repo->save($this->newWorkflow($user, 'Primary'));
        $this->repo->save($this->makeActive($primary));

        $this->repo->saveTrigger(new WorkflowTrigger(
            id: null,
            workflowId: (int) $primary->id,
            integrationKey: 'google.gmail',
            triggerKey: 'new_email_received',
            connectionId: null,
            strategy: 'poll',
            config: [],
            intervalMinutes: null,
            createdAt: $now,
            updatedAt: $now,
        ));

        $other = $this->repo->save($this->newWorkflow($otherUser, 'Other'));
        $this->repo->save($this->makeActive($other));

        $this->repo->saveTrigger(new WorkflowTrigger(
            id: null,
            workflowId: (int) $other->id,
            integrationKey: 'google.gmail',
            triggerKey: 'not_new_email',
            connectionId: null,
            strategy: 'poll',
            config: [],
            intervalMinutes: null,
            createdAt: $now,
            updatedAt: $now,
        ));

        $found = $this->repo->listActiveWithTrigger('google.gmail', 'new_email_received');

        $this->assertCount(1, $found);
        $this->assertSame((int) $primary->id, (int) $found[0]['workflow']->id);
        $this->assertSame('new_email_received', $found[0]['trigger']->triggerKey);
    }

    public function test_list_active_with_trigger_excludes_draft_paused_and_soft_deleted(): void
    {
        $user = User::factory()->create();
        $now = new DateTimeImmutable;

        foreach (['draft', 'paused'] as $status) {
            $w = $this->repo->save(new Workflow(
                id: null,
                userId: (int) $user->id,
                name: 'W-'.$status,
                description: null,
                status: WorkflowStatus::from($status),
                deletedAt: null,
                createdAt: $now,
                updatedAt: $now,
            ));

            $this->repo->saveTrigger(new WorkflowTrigger(
                id: null,
                workflowId: (int) $w->id,
                integrationKey: 'google.gmail',
                triggerKey: 'new_email_received',
                connectionId: null,
                strategy: 'poll',
                config: [],
                intervalMinutes: null,
                createdAt: $now,
                updatedAt: $now,
            ));
        }

        $active = $this->repo->save(new Workflow(
            id: null,
            userId: (int) $user->id,
            name: 'W-deleted',
            description: null,
            status: WorkflowStatus::Active,
            deletedAt: null,
            createdAt: $now,
            updatedAt: $now,
        ));

        $this->repo->saveTrigger(new WorkflowTrigger(
            id: null,
            workflowId: (int) $active->id,
            integrationKey: 'google.gmail',
            triggerKey: 'new_email_received',
            connectionId: null,
            strategy: 'poll',
            config: [],
            intervalMinutes: null,
            createdAt: $now,
            updatedAt: $now,
        ));

        $this->repo->softDelete($active);

        $found = $this->repo->listActiveWithTrigger('google.gmail', 'new_email_received');

        $this->assertSame([], $found);
    }
}
