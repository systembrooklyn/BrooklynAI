<?php

namespace App\Modules\Integrations\Core\ValueObjects;

final class FieldDefinition
{
    /**
     * @param  array<int, array{value: string, label: string}>|null  $options
     * @param  array{operation_id: string, params?: array<string, string>}|null  $options_source
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type,
        public readonly bool $required = false,
        public readonly ?string $description = null,
        public readonly mixed $default = null,
        public readonly ?array $options = null,
        public readonly ?array $options_source = null,
        public readonly ?string $labelKey = null,
        public readonly ?string $descriptionKey = null,
    ) {}
}
