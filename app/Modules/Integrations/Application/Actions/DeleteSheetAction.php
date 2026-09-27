<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\DeleteSheetInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsSheetManager;

final class DeleteSheetAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsSheetManager $manager,
    ) {}

    public function execute(DeleteSheetInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->manager->delete($resolved, $input->spreadsheetId, $input->sheetId);
    }
}
