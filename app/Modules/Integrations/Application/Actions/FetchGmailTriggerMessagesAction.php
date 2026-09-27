<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\FetchGmailTriggerMessagesInput;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Exception as GoogleServiceException;

final class FetchGmailTriggerMessagesAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailMessageReader $reader,
        private readonly GmailMessagePayloadBuilder $payloads,
    ) {}

    /**
     * Fetch and normalize Gmail messages for trigger processing.
     *
     * Returns the canonical 14-key trigger payloads (see Batch 7.2).
     * Messages that return 404 on getMessage are silently skipped.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws GmailProviderException
     */
    public function execute(FetchGmailTriggerMessagesInput $input): array
    {
        $resolved = $this->credentials->resolve($input->userId, $input->connectionId);

        try {
            $messageIds = $this->reader->listMessageIds(
                $resolved,
                $input->afterEpochSeconds,
                $input->labelIds,
            );
        } catch (GoogleServiceException $e) {
            throw $this->translate($e);
        }

        $payloads = [];

        foreach ($messageIds as $messageId) {
            try {
                $message = $this->reader->getMessage($resolved, $messageId);
            } catch (GoogleServiceException $e) {
                if ((int) $e->getCode() === 404) {
                    // Handled: skip this message silently.
                    continue;
                }

                throw $this->translate($e);
            }

            $payloads[] = $this->payloads->build($message);
        }

        return $payloads;
    }

    private function translate(GoogleServiceException $e): GmailProviderException
    {
        $code = (int) $e->getCode();

        if ($code === 429 || $code >= 500) {
            return GmailProviderException::transient($code, $e->getMessage());
        }

        return GmailProviderException::permanent($code, $e->getMessage());
    }
}
