<?php

namespace App\Modules\Integrations\Infrastructure\Google\Sheets;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Sheets as GoogleSheetsService;
use Google\Service\Sheets\ClearValuesRequest as GoogleSheetsClearValuesRequest;
use Google\Service\Sheets\ValueRange as GoogleSheetsValueRange;

class GoogleSheetsDataWriter
{
    /**
     * @return array{message: string}
     */
    public function clear(ResolvedGoogleCredentials $credentials, string $spreadsheetId, string $range): array
    {
        $service = $this->createService($credentials);

        return $this->clearRange($service, $spreadsheetId, $range);
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     * @return array{message: string}
     */
    public function update(ResolvedGoogleCredentials $credentials, string $spreadsheetId, string $range, array $values): array
    {
        $service = $this->createService($credentials);

        return $this->updateRange($service, $spreadsheetId, $range, $values);
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     * @return array{message: string}
     */
    public function append(ResolvedGoogleCredentials $credentials, string $spreadsheetId, string $range, array $values): array
    {
        $service = $this->createService($credentials);

        return $this->appendRange($service, $spreadsheetId, $range, $values);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleSheetsService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/spreadsheets');

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

    protected function clearRange(GoogleSheetsService $service, string $spreadsheetId, string $range): array
    {
        $service->spreadsheets_values->clear(
            $spreadsheetId,
            $range,
            new GoogleSheetsClearValuesRequest
        );

        return ['message' => 'Cleared'];
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     */
    protected function updateRange(GoogleSheetsService $service, string $spreadsheetId, string $range, array $values): array
    {
        $body = new GoogleSheetsValueRange;
        $body->setValues($values);

        $service->spreadsheets_values->update($spreadsheetId, $range, $body, [
            'valueInputOption' => 'USER_ENTERED',
        ]);

        return ['message' => 'Updated'];
    }

    /**
     * @param  array<int, array<int, mixed>>  $values
     */
    protected function appendRange(GoogleSheetsService $service, string $spreadsheetId, string $range, array $values): array
    {
        $body = new GoogleSheetsValueRange;
        $body->setValues($values);

        $service->spreadsheets_values->append($spreadsheetId, $range, $body, [
            'valueInputOption' => 'USER_ENTERED',
            'insertDataOption' => 'INSERT_ROWS',
        ]);

        return ['message' => 'Appended'];
    }
}
