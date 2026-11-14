<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Application\Actions\ActivateWorkflowAction;
use App\Modules\Automation\Application\DTOs\WorkflowIdInput;
use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Integrations\TestRestrictedIntegration;
use Tests\TestCase;

/**
 * Direct invocation of ActivateWorkflowAction to cover generic step-validation
 * behavior that is unreachable through the HTTP endpoint in Phase 1 (because
 * no Gmail trigger exists in the current catalog).
 *
 * TestRestrictedIntegration provides a valid trigger key
 * ('test.restricted.allowed_trigger') so validateStep() runs, exercising the
 * real WorkflowCapabilityValidator, WorkflowRepository, and
 * WorkflowTriggerActionCompatibilityValidator — none of the behavior under
 * test is mocked away.
 */
final class ActivateWorkflowActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register only TestRestrictedIntegration; the tests below do not
        // reference Gmail and do not need the full integration catalog.
        $this->app->singleton(IntegrationCatalog::class, function () {
            return new IntegrationCatalog([
                new TestRestrictedIntegration,
            ]);
        });
    }

    private function makeDraftWorkflowWithValidTrigger(User $user): WorkflowModel
    {
        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Activation test',
            'description' => null,
            'status' => 'draft',
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'test.restricted',
            'trigger_key' => 'allowed_trigger',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        return $workflow;
    }

    public function test_activation_rejects_unknown_integration_on_an_action(): void
    {
        $user = User::factory()->create();
        $workflow = $this->makeDraftWorkflowWithValidTrigger($user);

        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 1,
            'integration_key' => 'unknown.integration',
            'action_key' => 'whatever',
            'connection_id' => null,
            'config' => [],
        ]);

        $action = $this->app->make(ActivateWorkflowAction::class);

        try {
            $action->execute(new WorkflowIdInput(
                userId: (int) $user->id,
                workflowId: (int) $workflow->id,
            ));
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_UNKNOWN_INTEGRATION,
                $e->errorCode(),
            );
            $this->assertSame('step:1', $e->context()['location']);
            $this->assertSame('unknown.integration', $e->context()['integration_key']);
        }

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'status' => 'draft',
        ]);
    }

    public function test_activation_rejects_unknown_action_on_known_integration(): void
    {
        $user = User::factory()->create();
        $workflow = $this->makeDraftWorkflowWithValidTrigger($user);

        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 1,
            'integration_key' => 'test.restricted',
            'action_key' => 'not_a_real_action',
            'connection_id' => null,
            'config' => [],
        ]);

        $action = $this->app->make(ActivateWorkflowAction::class);

        try {
            $action->execute(new WorkflowIdInput(
                userId: (int) $user->id,
                workflowId: (int) $workflow->id,
            ));
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_UNKNOWN_ACTION,
                $e->errorCode(),
            );
            $this->assertSame('step:1', $e->context()['location']);
            $this->assertSame('test.restricted', $e->context()['integration_key']);
            $this->assertSame('not_a_real_action', $e->context()['action_key']);
        }

        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'status' => 'draft',
        ]);
    }

    public function test_multi_step_activation_aborts_when_one_step_fails(): void
    {
        $user = User::factory()->create();
        $workflow = $this->makeDraftWorkflowWithValidTrigger($user);

        // Step 1: valid unrestricted action.
        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 1,
            'integration_key' => 'test.restricted',
            'action_key' => 'unrestricted_action',
            'connection_id' => null,
            'config' => [],
        ]);

        // Step 2: unknown action on a known integration. This step fails.
        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 2,
            'integration_key' => 'test.restricted',
            'action_key' => 'not_a_real_action',
            'connection_id' => null,
            'config' => [],
        ]);

        $action = $this->app->make(ActivateWorkflowAction::class);

        try {
            $action->execute(new WorkflowIdInput(
                userId: (int) $user->id,
                workflowId: (int) $workflow->id,
            ));
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_UNKNOWN_ACTION,
                $e->errorCode(),
            );
            // Failing step is position 2.
            $this->assertSame('step:2', $e->context()['location']);
        }

        // Activation was aborted before any state change.
        $this->assertDatabaseHas('workflows', [
            'id' => $workflow->id,
            'status' => 'draft',
        ]);
    }
}
