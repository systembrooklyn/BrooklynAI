<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetSheetDataInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsDataReader;

final class GetSheetDataAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsDataReader $reader,
    ) {}

    public function execute(GetSheetDataInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->reader->read($resolved, $input->spreadsheetId, $input->range);
    }
}
