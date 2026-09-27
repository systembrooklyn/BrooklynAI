<?php

namespace App\Modules\Integrations\Infrastructure\Google\Sheets;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheetsService;

class GoogleSheetsDataReader
{
    public function read(ResolvedGoogleCredentials $credentials, string $spreadsheetId, string $range): array
    {
        $service = $this->createService($credentials);

        return $this->fetchValues($service, $spreadsheetId, $range);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleSheetsService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/spreadsheets.readonly');

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]);

        return new GoogleSheetsService($client);
    }

    protected function fetchValues(GoogleSheetsService $service, string $spreadsheetId, string $range): array
    {
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);

        return $response->getValues() ?: [];
    }
}
