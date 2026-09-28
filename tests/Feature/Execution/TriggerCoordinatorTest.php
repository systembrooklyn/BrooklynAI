<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Execution\Application\Services\TriggerCoordinator;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TriggerCoordinatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_due_triggers_are_not_processed(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'w',
            'status' => 'active',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'scheduler',
            'trigger_key' => 'interval',
            'strategy' => 'schedule',
            'config' => [],
            'interval_minutes' => 5,
            'next_poll_at' => (new DateTimeImmutable)->modify('+1 hour'),
        ]);

        $result = app(TriggerCoordinator::class)->run(new DateTimeImmutable, 50);

        $this->assertSame(0, $result->processedCount);
    }

    public function test_due_trigger_with_unknown_strategy_is_skipped(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'w',
            'status' => 'active',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'unknown',
            'trigger_key' => 'unknown',
            'strategy' => 'no_such_strategy',
            'config' => [],
            'interval_minutes' => 5,
            'next_poll_at' => null,
        ]);

        $result = app(TriggerCoordinator::class)->run(new DateTimeImmutable, 50);

        $this->assertGreaterThanOrEqual(1, $result->skippedCount);
        $this->assertSame(0, $result->executedCount);
    }

    public function test_paused_workflows_are_excluded_by_due_query(): void
    {
        $user = User::factory()->create();

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'w',
            'status' => 'paused',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'scheduler',
            'trigger_key' => 'interval',
            'strategy' => 'schedule',
            'config' => [],
            'interval_minutes' => 5,
            'next_poll_at' => null,
        ]);

        $result = app(TriggerCoordinator::class)->run(new DateTimeImmutable, 50);

        $this->assertSame(0, $result->processedCount);
    }
}
