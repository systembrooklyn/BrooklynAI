<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ClearSheetDataInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsDataWriter;

final class ClearSheetDataAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsDataWriter $writer,
    ) {}

    public function execute(ClearSheetDataInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->writer->clear($resolved, $input->spreadsheetId, $input->range);
    }
}
