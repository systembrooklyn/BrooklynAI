<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;

class GmailMessageReader
{
    /**
     * Paginate messages.list until nextPageToken is exhausted.
     *
     * @param  array<int, string>  $labelIds
     * @return array<int, string>
     */
    public function listMessageIds(
        ResolvedGoogleCredentials $credentials,
        int $afterEpochSeconds,
        array $labelIds = [],
    ): array {
        $service = $this->createService($credentials);

        $query = 'after:'.max($afterEpochSeconds, 0);

        $ids = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $query,
                'maxResults' => 500,
            ];

            if (! empty($labelIds)) {
                $params['labelIds'] = array_values($labelIds);
            }

            if (is_string($pageToken) && $pageToken !== '') {
                $params['pageToken'] = $pageToken;
            }

            $response = $service->users_messages->listUsersMessages('me', $params);

            foreach ($response->getMessages() ?? [] as $m) {
                $id = $m->getId();

                if (is_string($id) && $id !== '') {
                    $ids[] = $id;
                }
            }

            $pageToken = $response->getNextPageToken();
        } while (is_string($pageToken) && $pageToken !== '');

        return $ids;
    }

    public function getMessage(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): GoogleGmailMessage {
        $service = $this->createService($credentials);

        return $service->users_messages->get('me', $messageId, ['format' => 'full']);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
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
}
