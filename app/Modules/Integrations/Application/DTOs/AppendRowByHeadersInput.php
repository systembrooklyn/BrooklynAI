<?php

namespace App\Modules\Integrations\Application\DTOs;

final class AppendRowByHeadersInput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $spreadsheetId,
        public readonly string $sheetName,
        public readonly array $data,
        public readonly ?int $connectionId = null,
    ) {}
}
