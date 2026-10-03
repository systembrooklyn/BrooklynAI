<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\ModifyMessageRequest as GoogleGmailModifyMessageRequest;

/**
 * Modifies Gmail message state (read/unread, inbox membership, trash, labels).
 *
 * All operations require the `gmail.modify` scope. Exceptions from the
 * Gmail API propagate so that the workflow step can fail with a useful
 * error message.
 */
class GmailMessageModifier
{
    public function markAsRead(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): void {
        $this->modify(
            $credentials,
            $messageId,
            removeLabelIds: ['UNREAD'],
        );
    }

    public function markAsUnread(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): void {
        $this->modify(
            $credentials,
            $messageId,
            addLabelIds: ['UNREAD'],
        );
    }

    public function archive(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): void {
        $this->modify(
            $credentials,
            $messageId,
            removeLabelIds: ['INBOX'],
        );
    }

    public function trash(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): void {
        $service = $this->createService($credentials);

        $service->users_messages->trash('me', $messageId);
    }

    public function addLabel(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        string $labelId,
    ): void {
        $this->modify(
            $credentials,
            $messageId,
            addLabelIds: [$labelId],
        );
    }

    public function removeLabel(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        string $labelId,
    ): void {
        $this->modify(
            $credentials,
            $messageId,
            removeLabelIds: [$labelId],
        );
    }

    /**
     * @param  array<int, string>  $addLabelIds
     * @param  array<int, string>  $removeLabelIds
     */
    private function modify(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        array $addLabelIds = [],
        array $removeLabelIds = [],
    ): void {
        $service = $this->createService($credentials);

        $request = new GoogleGmailModifyMessageRequest;

        if ($addLabelIds !== []) {
            $request->setAddLabelIds($addLabelIds);
        }

        if ($removeLabelIds !== []) {
            $request->setRemoveLabelIds($removeLabelIds);
        }

        $service->users_messages->modify('me', $messageId, $request);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/gmail.modify');

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
}
