<?php

namespace App\Modules\Integrations\Infrastructure\Google\Sheets;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheetsService;

class GoogleSheetsGetter
{
    /**
     * @return array{id: string|null, title: string|null, url: string|null, sheets: array<int, array{id: int|null, title: string|null, index: int|null}>}
     */
    public function get(ResolvedGoogleCredentials $credentials, string $spreadsheetId): array
    {
        $service = $this->createService($credentials);

        return $this->fetchSpreadsheet($service, $spreadsheetId);
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

    /**
     * @return array{id: string|null, title: string|null, url: string|null, sheets: array<int, array{id: int|null, title: string|null, index: int|null}>}
     */
    protected function fetchSpreadsheet(GoogleSheetsService $service, string $spreadsheetId): array
    {
        $response = $service->spreadsheets->get($spreadsheetId);
        $sheets = $response->getSheets();

        $sheetList = [];
        foreach ($sheets as $sheet) {
            $p = $sheet->getProperties();
            $sheetList[] = [
                'id' => $p->getSheetId(),
                'title' => $p->getTitle(),
                'index' => $p->getIndex(),
            ];
        }

        return [
            'id' => $response->spreadsheetId,
            'title' => $response->getProperties()->getTitle(),
            'url' => $response->spreadsheetUrl,
            'sheets' => $sheetList,
        ];
    }
}
