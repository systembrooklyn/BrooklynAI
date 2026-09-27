<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\ListGmailLabelsInput;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailLabelReader;
use Google\Service\Exception as GoogleServiceException;

final class ListGmailLabelsAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailLabelReader $reader,
    ) {}

    /**
     * @return array<int, array{id: string, name: string, type: string}>
     *
     * @throws GmailProviderException
     */
    public function execute(ListGmailLabelsInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        try {
            return $this->reader->list($resolved);
        } catch (GoogleServiceException $e) {
            throw GmailProviderException::permanent((int) $e->getCode(), $e->getMessage());
        }
    }
}
