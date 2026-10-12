<?php

namespace Tests\Unit\Automation;

use App\Modules\Automation\Application\Services\WorkflowTriggerActionCompatibilityValidator;
use App\Modules\Automation\Core\Exceptions\WorkflowCapabilityException;
use App\Modules\Integrations\Application\Services\IntegrationCatalog;
use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Entities\TriggerDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\TriggerStrategy;
use PHPUnit\Framework\TestCase;

final class WorkflowTriggerActionCompatibilityValidatorTest extends TestCase
{
    public function test_unrestricted_action_is_compatible_with_any_trigger(): void
    {
        $validator = $this->validator();

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'test.restricted',
            actionKey: 'unrestricted_action',
            triggerIntegrationKey: 'test.restricted',
            triggerKey: 'other_trigger',
        );

        $this->assertTrue(true);
    }

    public function test_unrestricted_action_is_compatible_when_no_trigger_is_present(): void
    {
        $validator = $this->validator();

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'test.restricted',
            actionKey: 'unrestricted_action',
            triggerIntegrationKey: null,
            triggerKey: null,
        );

        $this->assertTrue(true);
    }

    public function test_restricted_action_is_compatible_with_its_allowed_trigger(): void
    {
        $validator = $this->validator();

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'test.restricted',
            actionKey: 'allowed_action',
            triggerIntegrationKey: 'test.restricted',
            triggerKey: 'allowed_trigger',
        );

        $this->assertTrue(true);
    }

    public function test_restricted_action_is_rejected_with_a_different_trigger(): void
    {
        $validator = $this->validator();

        try {
            $validator->validate(
                location: 'step',
                actionIntegrationKey: 'test.restricted',
                actionKey: 'allowed_action',
                triggerIntegrationKey: 'test.restricted',
                triggerKey: 'other_trigger',
            );
            $this->fail('Expected WorkflowCapabilityException.');
        } catch (WorkflowCapabilityException $e) {
            $this->assertSame(WorkflowCapabilityException::ERROR_INCOMPATIBLE_TRIGGER, $e->errorCode());
            $this->assertSame('step', $e->context()['location']);
            $this->assertSame('test.restricted.allowed_action', $e->context()['action_key']);
            $this->assertSame('test.restricted.other_trigger', $e->context()['trigger_key']);
            $this->assertSame(['test.restricted.allowed_trigger'], $e->context()['allowed_trigger_keys']);
        }
    }

    public function test_restricted_action_is_rejected_when_no_trigger_is_present(): void
    {
        $validator = $this->validator();

        $this->expectException(WorkflowCapabilityException::class);

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'test.restricted',
            actionKey: 'allowed_action',
            triggerIntegrationKey: null,
            triggerKey: null,
        );
    }

    public function test_unknown_action_does_not_throw(): void
    {
        $validator = $this->validator();

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'test.restricted',
            actionKey: 'not_a_real_action',
            triggerIntegrationKey: 'test.restricted',
            triggerKey: 'allowed_trigger',
        );

        $this->assertTrue(true);
    }

    public function test_unknown_integration_does_not_throw(): void
    {
        $validator = $this->validator();

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'not.a_real_integration',
            actionKey: 'whatever',
            triggerIntegrationKey: 'test.restricted',
            triggerKey: 'allowed_trigger',
        );

        $this->assertTrue(true);
    }

    public function test_empty_allowed_trigger_keys_is_treated_as_unrestricted(): void
    {
        $provider = new class implements IntegrationProvider
        {
            public function definition(): IntegrationDefinition
            {
                return new IntegrationDefinition(
                    key: IntegrationKey::fromString('test.empty'),
                    providerKey: ProviderKey::fromString('test'),
                    name: 'Test Empty',
                    description: 'Empty allowedTriggerKeys.',
                    category: 'test',
                    auth: new AuthDefinition(type: 'oauth2', providerKey: ProviderKey::fromString('test')),
                    actions: [
                        new ActionDefinition(
                            key: 'any',
                            label: 'Any',
                            description: 'Empty restriction list.',
                            allowedTriggerKeys: [],
                        ),
                    ],
                    triggers: [
                        new TriggerDefinition(
                            key: 't',
                            label: 'T',
                            description: '',
                            strategy: TriggerStrategy::Poll,
                        ),
                    ],
                );
            }
        };

        $catalog = new IntegrationCatalog([$provider]);
        $validator = new WorkflowTriggerActionCompatibilityValidator($catalog);

        $validator->validate(
            location: 'step',
            actionIntegrationKey: 'test.empty',
            actionKey: 'any',
            triggerIntegrationKey: 'test.empty',
            triggerKey: 't',
        );

        $this->assertTrue(true);
    }

    private function validator(): WorkflowTriggerActionCompatibilityValidator
    {
        return new WorkflowTriggerActionCompatibilityValidator(
            new IntegrationCatalog([new TestRestrictedProvider]),
        );
    }
}

final class TestRestrictedProvider implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        return (new \Tests\Support\Integrations\TestRestrictedIntegration)->definition();
    }
}
