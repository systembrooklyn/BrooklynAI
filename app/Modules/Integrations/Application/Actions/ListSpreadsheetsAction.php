<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ListSpreadsheetsInput;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsLister;

final class ListSpreadsheetsAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleSheetsLister $lister,
    ) {}

    /**
     * @return array<int, array{id: string|null, name: string|null, lastModified: mixed, ownerEmail: string|null, url: string|null}>
     */
    public function execute(ListSpreadsheetsInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        return $this->lister->list($resolved);
    }
}
