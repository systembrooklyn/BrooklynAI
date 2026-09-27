<?php

namespace App\Modules\Integrations\Application\DTOs;

final class UpdateSheetDataInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $spreadsheetId,
        public readonly string $range,
        public readonly array $values,
        public readonly ?int $connectionId = null,
    ) {}
}
