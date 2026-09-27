<?php

namespace App\Modules\Integrations\Core\Entities;

use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;

final class IntegrationDefinition
{
    /**
     * @param  ActionDefinition[]  $actions
     * @param  TriggerDefinition[]  $triggers
     */
    public function __construct(
        public readonly IntegrationKey $key,
        public readonly ProviderKey $providerKey,
        public readonly string $name,
        public readonly string $description,
        public readonly string $category,
        public readonly AuthDefinition $auth,
        public readonly array $actions = [],
        public readonly array $triggers = [],
    ) {}
}
