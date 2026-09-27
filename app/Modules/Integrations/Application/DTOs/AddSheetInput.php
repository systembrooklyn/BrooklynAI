<?php

namespace App\Modules\Integrations\Application\DTOs;

final class AddSheetInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $spreadsheetId,
        public readonly string $title,
        public readonly ?int $connectionId = null,
    ) {}
}
