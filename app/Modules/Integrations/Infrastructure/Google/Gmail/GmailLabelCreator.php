<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Label as GoogleGmailLabel;

/**
 * Creates Gmail labels.
 *
 * Requires the `gmail.labels` scope on the access token. This is a
 * separate scope from `gmail.modify`, which is why this class exists
 * as a distinct infrastructure boundary.
 */
class GmailLabelCreator
{
    /**
     * @return array{label_id: string, name: string}|null
     */
    public function create(
        ResolvedGoogleCredentials $credentials,
        string $name,
    ): ?array {
        $service = $this->createService($credentials);

        $label = new GoogleGmailLabel;
        $label->setName($name);
        $label->setLabelListVisibility('labelShow');
        $label->setMessageListVisibility('show');

        $created = $service->users_labels->create('me', $label);

        if (! $created instanceof GoogleGmailLabel) {
            return null;
        }

        return [
            'label_id' => (string) $created->getId(),
            'name' => (string) $created->getName(),
        ];
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/gmail.labels');

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
