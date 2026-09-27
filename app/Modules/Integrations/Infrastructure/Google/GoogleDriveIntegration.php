<?php

namespace App\Modules\Integrations\Infrastructure\Google;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;

final class GoogleDriveIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.drive'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Google Drive',
            description: 'Manage files and folders in Google Drive.',
            category: 'storage',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            actions: [],
            triggers: [],
        );
    }
}
