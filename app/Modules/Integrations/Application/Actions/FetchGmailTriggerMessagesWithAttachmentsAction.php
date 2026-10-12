<?php

namespace App\Modules\Integrations\Application\Actions;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Integrations\Application\DTOs\FetchGmailTriggerMessagesInput;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentMaterializer;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Exception as GoogleServiceException;

/**
 * Sibling of FetchGmailTriggerMessagesAction for the attachment-aware
 * Gmail trigger.
 *
 * Behaviour differences versus the existing action:
 *   - Messages without attachments are skipped (defensive; the
 *     'has:attachment' query should already filter them out).
 *   - For messages with attachments, the payload is produced via
 *     GmailMessagePayloadBuilder::buildWithAttachments(), which
 *     materializes each attachment into the user's Google Drive and
 *     adds an `attachments` key to the 14-key base payload.
 *
 * The existing FetchGmailTriggerMessagesAction is untouched.
 */
final class FetchGmailTriggerMessagesWithAttachmentsAction
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GmailMessageReader $reader,
        private readonly GmailMessagePayloadBuilder $payloads,
        private readonly GmailAttachmentMaterializer $materializer,
    ) {}

    /**
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
                $input->query,
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
                    continue;
                }

                throw $this->translate($e);
            }

            $base = $this->payloads->build($message);

            if ($base['has_attachment'] !== true) {
                continue;
            }

            $payloads[] = $this->payloads->buildWithAttachments(
                $message,
                $resolved,
                $this->materializer,
            );
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
