<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\GetSpreadsheetInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsGetter;

final class GetSpreadsheetAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsGetter $getter,
    ) {}

    public function execute(GetSpreadsheetInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->getter->get($resolved, $input->spreadsheetId);
    }
}
