<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GenerateFromTemplateInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $name,
        public readonly string $service,
        public readonly string $sign,
        public readonly ?string $title = null,
        public readonly ?int $connectionId = null,
    ) {}
}
