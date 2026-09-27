<?php

namespace App\Modules\Integrations\Core\Entities;

use App\Modules\Integrations\Core\ValueObjects\ProviderKey;

final class AuthDefinition
{
    public function __construct(
        public readonly string $type,
        public readonly ProviderKey $providerKey,
    ) {}
}
