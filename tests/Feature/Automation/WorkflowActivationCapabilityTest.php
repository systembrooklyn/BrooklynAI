<?php

namespace Tests\Feature\Automation;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkflowActivationCapabilityTest extends TestCase
{
    use RefreshDatabase;

    private const SCOPE_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';

    private const SCOPE_SEND = 'https://www.googleapis.com/auth/gmail.send';

    private const SCOPE_COMPOSE = 'https://www.googleapis.com/auth/gmail.compose';

    private function connection(User $user, array $scopes): ConnectionModel
    {
        return ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'gmail-'.uniqid(),
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'scopes' => $scopes,
            'status' => 'active',
        ]);
    }

    private function workflow(User $user): int
    {
        Sanctum::actingAs($user);

        return (int) $this->postJson('/api/workflows', ['name' => 'Capability Flow'])->json('data.id');
    }

    /**
     * Definition-level test. Proves GmailIntegration's send_email
     * ActionDefinition declares only gmail.send. It does NOT exercise
     * WorkflowCapabilityValidator — that coverage is in
     * WorkflowCapabilityValidatorTest::test_send_email_with_only_gmail_send_scope_passes.
     */
    public function test_send_email_definition_declares_only_send_scope(): void
    {
        $definition = (new \App\Modules\Integrations\Infrastructure\Google\GmailIntegration)->definition();

        $sendEmail = null;
        foreach ($definition->actions as $action) {
            if ($action->key === 'send_email') {
                $sendEmail = $action;
                break;
            }
        }

        $this->assertNotNull($sendEmail);
        $this->assertSame('gmail', $sendEmail->capability);

        $scopes = array_map(fn ($s) => $s->value, $sendEmail->requiredScopes);

        $this->assertSame([self::SCOPE_SEND], $scopes);
        $this->assertNotContains(self::SCOPE_READONLY, $scopes);
        $this->assertNotContains(self::SCOPE_COMPOSE, $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/gmail.modify', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/gmail.labels', $scopes);
    }

    /**
     * Active. Uses direct WorkflowTriggerModel insert to bypass the
     * disabled trigger upsert API and exercise ActivateWorkflowAction's
     * catalog lookup for unknown integration on the trigger.
     */
    public function test_unknown_integration_on_trigger_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);

        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'unknown.integration',
            'trigger_key' => 'whatever',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_integration')
            ->assertJsonPath('context.integration_key', 'unknown.integration');
    }

    /**
     * Active. Uses direct WorkflowTriggerModel insert.
     */
    public function test_unknown_trigger_on_known_integration_fails(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);

        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'not_a_real_trigger',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_trigger')
            ->assertJsonPath('context.trigger_key', 'not_a_real_trigger');
    }

    /**
     * Active. Negative test proving new_email_received is no longer a
     * valid trigger key in the Phase 1 catalog.
     */
    public function test_new_email_received_trigger_rejected_on_activation(): void
    {
        $user = User::factory()->create();
        $id = $this->workflow($user);

        \App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel::create([
            'workflow_id' => $id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => [],
        ]);

        $this->postJson('/api/workflows/'.$id.'/activate')
            ->assertStatus(409)
            ->assertJsonPath('error', 'unknown_trigger');
    }

    // -------------------------------------------------------------------
    // Skips — no valid trigger exists in the Phase 1 catalog.
    // -------------------------------------------------------------------

    /**
     * Skipped: requires a Gmail trigger that currently exists in the
     * catalog. Phase 1 disables all Gmail triggers.
     */
    public function test_valid_gmail_trigger_activates(): void
    {
        $this->markTestSkipped('Phase 1: no Gmail trigger key exists in the active catalog.');
    }

    /**
     * Skipped: requires successful trigger upsert with new_email_received.
     */
    public function test_gmail_trigger_missing_connection_id_fails(): void
    {
        $this->markTestSkipped('Phase 1: requires successful Gmail trigger upsert (new_email_received disabled).');
    }

    /**
     * Skipped: activation flow requires a valid trigger before reaching
     * step validation. No valid trigger exists.
     */
    public function test_gmail_action_missing_connection_id_fails(): void
    {
        $this->markTestSkipped('Phase 1: ActivateWorkflowAction requires a valid trigger before step validation runs. No trigger key exists in the current catalog.');
    }

    /**
     * Skipped: same trigger requirement. Validator-level equivalent is
     * WorkflowCapabilityValidatorTest::test_connection_not_found_fails
     * (actively verifies the connection-not-found branch of the validator).
     */
    public function test_connection_does_not_exist_fails(): void
    {
        $this->markTestSkipped('Phase 1: requires valid trigger before connection validation runs. Validator equivalent: WorkflowCapabilityValidatorTest::test_connection_not_found_fails.');
    }

    /**
     * Skipped: same trigger requirement. Validator-level equivalent is
     * WorkflowCapabilityValidatorTest::test_connection_owned_by_another_user_fails.
     */
    public function test_connection_owned_by_another_user_fails(): void
    {
        $this->markTestSkipped('Phase 1: requires valid trigger before ownership validation runs. Validator equivalent: WorkflowCapabilityValidatorTest::test_connection_owned_by_another_user_fails.');
    }

    /**
     * Skipped: same trigger requirement. Validator-level equivalent is
     * WorkflowCapabilityValidatorTest::test_missing_required_scope_fails.
     */
    public function test_missing_required_scope_fails(): void
    {
        $this->markTestSkipped('Phase 1: requires valid trigger before scope validation runs. Validator equivalent: WorkflowCapabilityValidatorTest::test_missing_required_scope_fails.');
    }

    /**
     * Skipped: new_email_received trigger disabled.
     */
    public function test_new_email_received_only_requires_readonly_scope(): void
    {
        $this->markTestSkipped('Phase 1: new_email_received trigger disabled.');
    }

    /**
     * Skipped: create_draft action disabled.
     */
    public function test_create_draft_only_requires_compose_scope(): void
    {
        $this->markTestSkipped('Phase 1: create_draft action disabled.');
    }

    /**
     * Skipped: same trigger requirement. Validator-level equivalent is
     * WorkflowCapabilityValidatorTest::test_different_connections_across_two_actions_pass
     * (actively verifies the validator handles two different connections).
     */
    public function test_different_connections_in_one_workflow_pass(): void
    {
        $this->markTestSkipped('Phase 1: requires valid trigger. Validator equivalent: WorkflowCapabilityValidatorTest::test_different_connections_across_two_actions_pass.');
    }

    /**
     * Skipped: requires valid trigger + multiple steps + activation flow.
     * No unit test of ActivateWorkflowAction::execute() with mocked catalog
     * exists to cover this without a valid trigger.
     */
    public function test_multiple_steps_where_one_fails_does_not_activate(): void
    {
        $this->markTestSkipped('Phase 1: requires valid trigger; no equivalent unit test of ActivateWorkflowAction::execute() exists.');
    }

    /**
     * Skipped: requires valid trigger so activation reaches step validation.
     * Equivalent at validator level: WorkflowCapabilityValidatorTest::test_null_capability_is_out_of_scope_and_passes
     * (actively verifies capability:null passes the validator).
     */
    public function test_capability_null_action_is_not_blocked(): void
    {
        $this->markTestSkipped('Phase 1: requires valid trigger to reach step validation. Validator equivalent: WorkflowCapabilityValidatorTest::test_null_capability_is_out_of_scope_and_passes.');
    }

    /**
     * Skipped: catalog lookup for unknown integration on a step, which
     * only runs inside ActivateWorkflowAction::validateStep() after a
     * valid trigger passes. No valid trigger exists. No validator-level
     * equivalent (validator does not do catalog lookups).
     */
    public function test_unknown_integration_on_action_fails(): void
    {
        $this->markTestSkipped('Phase 1: catalog lookup for action-level unknown integration runs only after trigger validation passes. No trigger key exists. No validator equivalent (validator does not do catalog lookups).');
    }

    /**
     * Skipped: catalog lookup for unknown action on known integration,
     * same flow constraint as above.
     */
    public function test_unknown_action_on_known_integration_fails(): void
    {
        $this->markTestSkipped('Phase 1: catalog lookup for unknown action runs only after trigger validation passes. No trigger key exists. No validator equivalent.');
    }
}
