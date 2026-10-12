<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;

/**
 * Fetches Gmail attachment bytes via users.messages.attachments.get.
 *
 * Independent of GmailMessageReader. Does not touch existing Gmail
 * infrastructure. Returns the decoded binary payload as a PHP string;
 * the bytes are never written to disk.
 *
 * The Gmail API returns attachment content as base64url (RFC 4648 §5)
 * without padding. This class normalizes and decodes that form using
 * the same algorithm as GmailMessagePayloadBuilder::decodeBodyData().
 */
class GmailAttachmentReader
{
    /**
     * Retrieve a single Gmail attachment as raw bytes.
     *
     * @throws GmailProviderException On any Gmail API failure or decode failure.
     */
    public function read(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        string $attachmentId,
    ): string {
        try {
            $part = $this->fetchFromApi(
                $this->createService($credentials),
                $messageId,
                $attachmentId,
            );
        } catch (GoogleServiceException $e) {
            throw $this->translate($e);
        }

        return $this->decodeBody($part, $messageId, $attachmentId);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/gmail.readonly');

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]);

        return new GoogleGmailService($client);
    }

    /**
     * Test seam. Overridden by test doubles; production always calls the
     * real Gmail API.
     */
    protected function fetchFromApi(
        GoogleGmailService $service,
        string $messageId,
        string $attachmentId,
    ): GoogleGmailMessagePartBody {
        return $service->users_messages_attachments->get('me', $messageId, $attachmentId);
    }

    private function decodeBody(
        GoogleGmailMessagePartBody $part,
        string $messageId,
        string $attachmentId,
    ): string {
        $data = $part->getData();

        if (! is_string($data) || $data === '') {
            return '';
        }

        // Gmail returns base64url without padding.
        $normalized = strtr($data, '-_', '+/');
        $padding = strlen($normalized) % 4;

        if ($padding > 0) {
            $normalized .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode($normalized, true);

        if ($decoded === false) {
            throw GmailProviderException::permanent(
                0,
                sprintf(
                    'Gmail attachment %s for message %s could not be decoded.',
                    $attachmentId,
                    $messageId,
                ),
            );
        }

        return $decoded;
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
