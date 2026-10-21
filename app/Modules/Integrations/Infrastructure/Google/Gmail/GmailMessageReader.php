<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;

class GmailMessageReader
{
    /**
     * Paginate messages.list until nextPageToken is exhausted or limit is reached.
     *
     * @param  array<int, string>  $labelIds
     * @return array<int, string>
     */
    public function listMessageIds(
        ResolvedGoogleCredentials $credentials,
        int $afterEpochSeconds,
        array $labelIds = [],
        ?string $query = null,
        int $limit = 500, // <--- Added $limit here
    ): array {
        $service = $this->createService($credentials);

        $baseQuery = 'after:'.max($afterEpochSeconds, 0);

        $fullQuery = ($query !== null && trim($query) !== '')
            ? $baseQuery.' '.trim($query)
            : $baseQuery;

        $ids = [];
        $pageToken = null;

        do {
            $params = [
                'q' => $fullQuery,
                'maxResults' => min($limit, 500),
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

                // Stop immediately if we reached our limit
                if (count($ids) >= $limit) {
                    break 2;
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
        // CHANGED: Use config() instead of env() for production safety
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
}
