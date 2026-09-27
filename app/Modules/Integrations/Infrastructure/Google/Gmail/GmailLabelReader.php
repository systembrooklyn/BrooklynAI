<?php

namespace App\Modules\Integrations\Infrastructure\Google\Gmail;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Gmail as GoogleGmailService;

class GmailLabelReader
{
    /**
     * @return array<int, array{id: string, name: string, type: string}>
     */
    public function list(ResolvedGoogleCredentials $credentials): array
    {
        $service = $this->createService($credentials);

        $response = $service->users_labels->listUsersLabels('me');

        $out = [];

        foreach ($response->getLabels() ?? [] as $label) {
            $id = $label->getId();

            if (! is_string($id) || $id === '') {
                continue;
            }

            $name = $label->getName();
            $type = $label->getType();

            $out[] = [
                'id' => $id,
                'name' => is_string($name) ? $name : '',
                'type' => is_string($type) ? $type : 'user',
            ];
        }

        return $out;
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
