<?php

namespace App\Modules\Integrations\Core\Entities;

use App\Modules\Integrations\Core\ValueObjects\FieldDefinition;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;

final class ActionDefinition
{
    /**
     * @param  ScopeIdentifier[]  $requiredScopes
     * @param  FieldDefinition[]  $fields
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly array $requiredScopes = [],
        public readonly ?string $capability = null,
        public readonly array $fields = [],
        public readonly ?string $labelKey = null,
        public readonly ?string $descriptionKey = null,
    ) {}
}
