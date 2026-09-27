<?php

namespace App\Modules\Integrations\Application\DTOs;

final class DeleteSheetInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $spreadsheetId,
        public readonly int $sheetId,
        public readonly ?int $connectionId = null,
    ) {}
}
