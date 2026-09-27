<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\UpdateSheetDataInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsDataWriter;

final class UpdateSheetDataAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsDataWriter $writer,
    ) {}

    public function execute(UpdateSheetDataInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->writer->update($resolved, $input->spreadsheetId, $input->range, $input->values);
    }
}
