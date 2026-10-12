<?php

namespace Tests\Support\Integrations;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\Entities\TriggerDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\TriggerStrategy;

final class TestRestrictedIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            key: IntegrationKey::fromString('test.restricted'),
            providerKey: ProviderKey::fromString('test'),
            name: 'Test Restricted',
            description: 'Test-only integration used to exercise trigger/action compatibility rules.',
            category: 'test',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('test'),
            ),
            actions: [
                new ActionDefinition(
                    key: 'allowed_action',
                    label: 'Allowed Action',
                    description: 'Only compatible with the allowed trigger.',
                    allowedTriggerKeys: ['test.restricted.allowed_trigger'],
                ),
                new ActionDefinition(
                    key: 'unrestricted_action',
                    label: 'Unrestricted Action',
                    description: 'Compatible with any trigger.',
                ),
            ],
            triggers: [
                new TriggerDefinition(
                    key: 'allowed_trigger',
                    label: 'Allowed Trigger',
                    description: 'Triggers compatible with allowed_action.',
                    strategy: TriggerStrategy::Poll,
                ),
                new TriggerDefinition(
                    key: 'other_trigger',
                    label: 'Other Trigger',
                    description: 'Triggers incompatible with allowed_action.',
                    strategy: TriggerStrategy::Poll,
                ),
            ],
        );
    }
}
