<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Application\Services\WorkflowCapabilityValidator;
use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ConnectionStatus;
use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Entities\TriggerDefinition;
use App\Modules\Integrations\Infrastructure\Google\GmailIntegration;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\FakeConnectionRepository;

class WorkflowCapabilityValidatorTest extends TestCase
{
    private const SCOPE_READONLY = 'https://www.googleapis.com/auth/gmail.readonly';

    private const SCOPE_SEND = 'https://www.googleapis.com/auth/gmail.send';

    private const SCOPE_COMPOSE = 'https://www.googleapis.com/auth/gmail.compose';

    private FakeConnectionRepository $connections;

    private WorkflowCapabilityValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connections = new FakeConnectionRepository;

        $this->validator = new WorkflowCapabilityValidator(
            $this->connections,
            new CapabilityScopeMap,
        );
    }

    private function gmailDefinition(): IntegrationDefinition
    {
        return (new GmailIntegration)->definition();
    }

    private function findAction(IntegrationDefinition $integration, string $key): ActionDefinition
    {
        foreach ($integration->actions as $action) {
            if ($action->key === $key) {
                return $action;
            }
        }

        throw new \RuntimeException("Action {$key} not found.");
    }

    private function findTrigger(IntegrationDefinition $integration, string $key): TriggerDefinition
    {
        foreach ($integration->triggers as $trigger) {
            if ($trigger->key === $key) {
                return $trigger;
            }
        }

        throw new \RuntimeException("Trigger {$key} not found.");
    }

    /**
     * @param  array<int, \App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier>  $scopes
     * @return array<int, string>
     */
    private function scopeValues(array $scopes): array
    {
        return array_map(static fn ($s) => $s->value, $scopes);
    }

    /**
     * @param  array<int, string>  $scopes
     */
    private function makeConnection(int $id, int $userId, array $scopes): Connection
    {
        $now = new DateTimeImmutable;

        return new Connection(
            id: $id,
            userId: $userId,
            provider: 'google',
            externalAccountId: 'sub-'.$id,
            email: 'user'.$userId.'@example.com',
            displayName: 'User '.$userId,
            credentials: new ConnectionCredentials('access-'.$id, 'refresh-'.$id, null),
            scopes: $scopes,
            status: ConnectionStatus::Active,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    // -------------------------------------------------------------------
    // Gmail definition metadata: capabilities + required scopes
    // -------------------------------------------------------------------

    public function test_send_email_action_carries_gmail_capability_and_only_send_scope(): void
    {
        $action = $this->findAction($this->gmailDefinition(), 'send_email');

        $this->assertSame('gmail', $action->capability);
        $this->assertSame([self::SCOPE_SEND], $this->scopeValues($action->requiredScopes));
    }

    public function test_new_email_received_trigger_carries_gmail_capability_and_only_readonly_scope(): void
    {
        $trigger = $this->findTrigger($this->gmailDefinition(), 'new_email_received');

        $this->assertSame('gmail', $trigger->capability);
        $this->assertSame([self::SCOPE_READONLY], $this->scopeValues($trigger->requiredScopes));
    }

    public function test_create_draft_action_carries_gmail_capability_and_only_compose_scope(): void
    {
        $action = $this->findAction($this->gmailDefinition(), 'create_draft');

        $this->assertSame('gmail', $action->capability);
        $this->assertSame([self::SCOPE_COMPOSE], $this->scopeValues($action->requiredScopes));
    }

    // -------------------------------------------------------------------
    // Definition-level scope requirements drive validation
    // -------------------------------------------------------------------

    public function test_send_email_with_only_gmail_send_scope_passes(): void
    {
        $action = $this->findAction($this->gmailDefinition(), 'send_email');

        // Connection has only gmail.send.
        $this->connections->addConnection($this->makeConnection(1, 10, [self::SCOPE_SEND]));

        $this->validator->validate(
            location: 'step:1',
            capability: $action->capability,
            requiredScopes: $this->scopeValues($action->requiredScopes),
            connectionId: 1,
            userId: 10,
        );

        $this->addToAssertionCount(1);
    }

    public function test_new_email_received_with_only_gmail_readonly_scope_passes(): void
    {
        $trigger = $this->findTrigger($this->gmailDefinition(), 'new_email_received');

        $this->connections->addConnection($this->makeConnection(1, 10, [self::SCOPE_READONLY]));

        $this->validator->validate(
            location: 'trigger',
            capability: $trigger->capability,
            requiredScopes: $this->scopeValues($trigger->requiredScopes),
            connectionId: 1,
            userId: 10,
        );

        $this->addToAssertionCount(1);
    }

    public function test_create_draft_with_only_gmail_compose_scope_passes(): void
    {
        $action = $this->findAction($this->gmailDefinition(), 'create_draft');

        $this->connections->addConnection($this->makeConnection(1, 10, [self::SCOPE_COMPOSE]));

        $this->validator->validate(
            location: 'step:1',
            capability: $action->capability,
            requiredScopes: $this->scopeValues($action->requiredScopes),
            connectionId: 1,
            userId: 10,
        );

        $this->addToAssertionCount(1);
    }

    public function test_gmail_step_does_not_require_unrelated_gmail_scopes(): void
    {
        // A send_email step must NOT require gmail.readonly or gmail.compose.
        // If the validator regressed to using CapabilityScopeMap::resolve('gmail'),
        // this would throw for missing gmail.readonly.
        $action = $this->findAction($this->gmailDefinition(), 'send_email');

        $this->connections->addConnection($this->makeConnection(1, 10, [self::SCOPE_SEND]));

        $this->validator->validate(
            'step:1',
            $action->capability,
            $this->scopeValues($action->requiredScopes),
            1,
            10,
        );

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------
    // Failure cases
    // -------------------------------------------------------------------

    public function test_missing_connection_id_fails(): void
    {
        $trigger = $this->findTrigger($this->gmailDefinition(), 'new_email_received');

        try {
            $this->validator->validate(
                'trigger',
                $trigger->capability,
                $this->scopeValues($trigger->requiredScopes),
                null,
                10,
            );
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_MISSING_CONNECTION,
                $e->errorCode(),
            );
        }
    }

    public function test_connection_not_found_fails(): void
    {
        $trigger = $this->findTrigger($this->gmailDefinition(), 'new_email_received');

        try {
            $this->validator->validate(
                'trigger',
                $trigger->capability,
                $this->scopeValues($trigger->requiredScopes),
                999,
                10,
            );
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_CONNECTION_NOT_FOUND,
                $e->errorCode(),
            );
        }
    }

    public function test_connection_owned_by_another_user_fails(): void
    {
        // Connection belongs to user 20; validator invoked for user 10.
        $this->connections->addConnection($this->makeConnection(1, 20, [
            self::SCOPE_READONLY,
            self::SCOPE_SEND,
        ]));

        $trigger = $this->findTrigger($this->gmailDefinition(), 'new_email_received');

        try {
            $this->validator->validate(
                'trigger',
                $trigger->capability,
                $this->scopeValues($trigger->requiredScopes),
                1,
                10,
            );
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_CONNECTION_NOT_FOUND,
                $e->errorCode(),
            );
        }
    }

    public function test_missing_required_scope_fails(): void
    {
        // Connection has readonly only; send_email requires send.
        $this->connections->addConnection($this->makeConnection(1, 10, [self::SCOPE_READONLY]));

        $action = $this->findAction($this->gmailDefinition(), 'send_email');

        try {
            $this->validator->validate(
                'step:1',
                $action->capability,
                $this->scopeValues($action->requiredScopes),
                1,
                10,
            );
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_MISSING_SCOPES,
                $e->errorCode(),
            );

            $context = $e->context();
            $this->assertArrayHasKey('missing_scopes', $context);
            $this->assertContains(self::SCOPE_SEND, $context['missing_scopes']);
        }
    }

    public function test_unsupported_capability_fails(): void
    {
        try {
            $this->validator->validate(
                'trigger',
                'not_a_real_capability',
                [self::SCOPE_READONLY],
                1,
                10,
            );
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(
                WorkflowCapabilityException::ERROR_UNSUPPORTED_CAPABILITY,
                $e->errorCode(),
            );

            $this->assertSame(
                'not_a_real_capability',
                $e->context()['capability'],
            );
        }
    }

    // -------------------------------------------------------------------
    // Different connections across trigger and action
    // -------------------------------------------------------------------

    public function test_different_connections_across_trigger_and_action_pass(): void
    {
        $this->connections->addConnection($this->makeConnection(1, 10, [self::SCOPE_READONLY]));
        $this->connections->addConnection($this->makeConnection(2, 10, [self::SCOPE_SEND]));

        $trigger = $this->findTrigger($this->gmailDefinition(), 'new_email_received');
        $action = $this->findAction($this->gmailDefinition(), 'send_email');

        $this->validator->validate(
            'trigger',
            $trigger->capability,
            $this->scopeValues($trigger->requiredScopes),
            1,
            10,
        );

        $this->validator->validate(
            'step:1',
            $action->capability,
            $this->scopeValues($action->requiredScopes),
            2,
            10,
        );

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------
    // capability: null behavior
    // -------------------------------------------------------------------

    public function test_null_capability_is_out_of_scope_and_passes(): void
    {
        $this->validator->validate('trigger', null, [], null, 10);

        $this->addToAssertionCount(1);
    }
}
