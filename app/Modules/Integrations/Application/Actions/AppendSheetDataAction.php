<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\AppendSheetDataInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsDataWriter;

final class AppendSheetDataAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsDataWriter $writer,
    ) {}

    public function execute(AppendSheetDataInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->writer->append($resolved, $input->spreadsheetId, $input->range, $input->values);
    }
}
