<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowStepModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleAnalyticsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleCalendarIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDocsIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleDriveIntegration;
use App\Modules\Integrations\Infrastructure\Google\GoogleSheetsIntegration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Integrations\TestRestrictedIntegration;
use Tests\TestCase;

final class WorkflowTriggerActionCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(IntegrationCatalog::class, function () {
            return new IntegrationCatalog([
                new GmailIntegration,
                new GoogleCalendarIntegration,
                new GoogleSheetsIntegration,
                new GoogleDriveIntegration,
                new GoogleDocsIntegration,
                new GoogleAnalyticsIntegration,
                new TestRestrictedIntegration,
            ]);
        });
    }

    public function test_unrestricted_action_is_accepted_with_either_trigger(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'other_trigger');

        $response = $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/steps", [
            'integration_key' => 'test.restricted',
            'action_key' => 'unrestricted_action',
            'config' => [],
        ]);

        $response->assertStatus(201);
    }

    public function test_restricted_action_is_accepted_with_its_allowed_trigger(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'allowed_trigger');

        $response = $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/steps", [
            'integration_key' => 'test.restricted',
            'action_key' => 'allowed_action',
            'config' => [],
        ]);

        $response->assertStatus(201);
    }

    public function test_restricted_action_is_rejected_with_a_different_trigger_on_step_add(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'other_trigger');

        $response = $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/steps", [
            'integration_key' => 'test.restricted',
            'action_key' => 'allowed_action',
            'config' => [],
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'error' => 'incompatible_trigger',
        ]);
        $response->assertJsonPath('context.action_key', 'test.restricted.allowed_action');
        $response->assertJsonPath('context.trigger_key', 'test.restricted.other_trigger');
    }

    public function test_restricted_action_is_rejected_on_step_update_with_a_different_trigger(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'other_trigger');

        // Seed an unrestricted step directly so the update is the only change.
        WorkflowStepModel::create([
            'workflow_id' => $workflow->id,
            'position' => 1,
            'integration_key' => 'test.restricted',
            'action_key' => 'unrestricted_action',
            'connection_id' => null,
            'config' => [],
        ]);

        $response = $this->actingAs($user)->putJson("/api/workflows/{$workflow->id}/steps/1", [
            'integration_key' => 'test.restricted',
            'action_key' => 'allowed_action',
            'config' => [],
        ]);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);
    }

    public function test_trigger_replacement_is_rejected_when_existing_step_becomes_incompatible(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'allowed_trigger');

        $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/steps", [
            'integration_key' => 'test.restricted',
            'action_key' => 'allowed_action',
            'config' => [],
        ])->assertStatus(201);

        // Attempt to replace the trigger with an incompatible one.
        $response = $this->actingAs($user)->putJson("/api/workflows/{$workflow->id}/trigger", [
            'integration_key' => 'test.restricted',
            'trigger_key' => 'other_trigger',
        ]);

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);

        // The original trigger must remain intact.
        $this->assertDatabaseHas('workflow_triggers', [
            'workflow_id' => $workflow->id,
            'trigger_key' => 'allowed_trigger',
        ]);
    }

    public function test_activation_rejects_incompatible_pairing(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'allowed_trigger');

        $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/steps", [
            'integration_key' => 'test.restricted',
            'action_key' => 'allowed_action',
            'config' => [],
        ])->assertStatus(201);

        // Bypass the write-path guard by writing the incompatible trigger directly.
        WorkflowTriggerModel::where('workflow_id', $workflow->id)
            ->update(['trigger_key' => 'other_trigger']);

        $response = $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/activate");

        $response->assertStatus(409);
        $response->assertJson(['error' => 'incompatible_trigger']);
    }

    public function test_activation_succeeds_with_a_compatible_pairing(): void
    {
        [$user, $workflow] = $this->userAndWorkflow();

        $this->upsertTrigger($user, $workflow->id, 'test.restricted', 'allowed_trigger');

        $this->actingAs($user)->postJson("/api/workflows/{$workflow->id}/steps", [
            'integration_key' => 'test.restricted',
            'action_key' => 'allowed_action',
            'config' => [],
        ])->assertStatus(201);

        $this->actingAs($user)
            ->postJson("/api/workflows/{$workflow->id}/activate")
            ->assertStatus(200);
    }

    // ---------------------------------------------------------------------

    private function userAndWorkflow(): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'Test',
            'description' => null,
            'status' => 'draft',
        ]);

        return [$user, $workflow];
    }

    private function upsertTrigger(User $user, int $workflowId, string $integrationKey, string $triggerKey): void
    {
        $this->actingAs($user)->putJson("/api/workflows/{$workflowId}/trigger", [
            'integration_key' => $integrationKey,
            'trigger_key' => $triggerKey,
        ])->assertStatus(200);
    }
}
