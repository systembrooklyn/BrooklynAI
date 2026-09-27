<?php

namespace App\Modules\Integrations\Application\DTOs;

final class GetSpreadsheetInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $spreadsheetId,
        public readonly ?int $connectionId = null,
    ) {}
}
