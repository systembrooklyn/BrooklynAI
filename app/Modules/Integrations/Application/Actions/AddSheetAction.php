<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\AddSheetInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsSheetManager;

final class AddSheetAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsSheetManager $manager,
    ) {}

    public function execute(AddSheetInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->manager->add($resolved, $input->spreadsheetId, $input->title);
    }
}
