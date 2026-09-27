<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowTriggerPollCursorTest extends TestCase
{
    use RefreshDatabase;

    private function createWorkflow(User $user): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => 'F'])->json('data.id');
    }

    private function upsertTrigger(int $workflowId, array $extra = []): void
    {
        $this->putJson('/api/workflows/'.$workflowId.'/trigger', array_merge([
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
        ], $extra))->assertStatus(200);
    }

    public function test_new_trigger_has_null_poll_cursor(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->upsertTrigger($id);

        $this->assertNull(
            WorkflowTriggerModel::query()->where('workflow_id', $id)->value('poll_cursor')
        );
    }

    public function test_poll_cursor_persists_as_epoch_integer(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->upsertTrigger($id);

        WorkflowTriggerModel::query()
            ->where('workflow_id', $id)
            ->update(['poll_cursor' => 1700000000]);

        $stored = WorkflowTriggerModel::query()->where('workflow_id', $id)->value('poll_cursor');

        $this->assertSame(1700000000, (int) $stored);
    }

    public function test_trigger_entity_carries_poll_cursor(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->upsertTrigger($id);

        WorkflowTriggerModel::query()
            ->where('workflow_id', $id)
            ->update(['poll_cursor' => 1700000000]);

        /** @var \App\Modules\Automation\Core\Repositories\WorkflowRepository $repo */
        $repo = $this->app->make(\App\Modules\Automation\Core\Repositories\WorkflowRepository::class);

        $trigger = $repo->findTriggerForWorkflow($id);

        $this->assertNotNull($trigger);
        $this->assertSame(1700000000, $trigger->pollCursor);
    }

    public function test_trigger_entity_reports_null_poll_cursor_when_uninitialized(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->upsertTrigger($id);

        /** @var \App\Modules\Automation\Core\Repositories\WorkflowRepository $repo */
        $repo = $this->app->make(\App\Modules\Automation\Core\Repositories\WorkflowRepository::class);

        $trigger = $repo->findTriggerForWorkflow($id);

        $this->assertNotNull($trigger);
        $this->assertNull($trigger->pollCursor);
    }

    public function test_trigger_upsert_preserves_existing_poll_cursor(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->upsertTrigger($id);

        WorkflowTriggerModel::query()
            ->where('workflow_id', $id)
            ->update(['poll_cursor' => 1700000000]);

        // Re-upsert with different config.
        $this->upsertTrigger($id, ['interval_minutes' => 15]);

        $this->assertSame(
            1700000000,
            (int) WorkflowTriggerModel::query()->where('workflow_id', $id)->value('poll_cursor')
        );
    }

    public function test_delete_then_recreate_trigger_resets_poll_cursor(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);
        $this->upsertTrigger($id);

        WorkflowTriggerModel::query()
            ->where('workflow_id', $id)
            ->update(['poll_cursor' => 1700000000]);

        $this->deleteJson('/api/workflows/'.$id.'/trigger')->assertStatus(200);

        $this->upsertTrigger($id);

        $this->assertNull(
            WorkflowTriggerModel::query()->where('workflow_id', $id)->value('poll_cursor')
        );
    }

    public function test_existing_trigger_fields_are_unchanged_by_new_column(): void
    {
        $user = User::factory()->create();
        $id = $this->createWorkflow($user);

        $this->upsertTrigger($id, [
            'interval_minutes' => 5,
            'config' => ['labels' => ['INBOX']],
        ]);

        $row = WorkflowTriggerModel::query()->where('workflow_id', $id)->firstOrFail();

        $this->assertSame('google.gmail', $row->integration_key);
        $this->assertSame('new_email_received', $row->trigger_key);
        $this->assertSame('poll', $row->strategy);
        $this->assertSame(5, (int) $row->interval_minutes);
        $this->assertSame(['labels' => ['INBOX']], $row->config);
        $this->assertNull($row->poll_cursor);
    }
}
