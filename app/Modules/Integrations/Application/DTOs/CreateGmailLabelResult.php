<?php

namespace App\Modules\Integrations\Application\DTOs;

final class CreateGmailLabelResult
{
    public function __construct(
        public readonly bool $created,
        public readonly ?string $labelId = null,
        public readonly ?string $name = null,
    ) {}
}
