<?php

namespace App\Modules\Integrations\Infrastructure\Google\Sheets;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Sheets\AddSheetRequest as GoogleSheetsAddSheetRequest;
use Google\Service\Sheets as GoogleSheetsService;
use Google\Service\Sheets\BatchUpdateSpreadsheetRequest as GoogleSheetsBatchUpdateRequest;
use Google\Service\Sheets\DeleteSheetRequest as GoogleSheetsDeleteSheetRequest;
use Google\Service\Sheets\Request as GoogleSheetsRequest;
use Google\Service\Sheets\SheetProperties as GoogleSheetsSheetProperties;

class GoogleSheetsSheetManager
{
    /**
     * @return array{message: string}
     */
    public function add(ResolvedGoogleCredentials $credentials, string $spreadsheetId, string $title): array
    {
        $service = $this->createService($credentials);

        return $this->addSheet($service, $spreadsheetId, $title);
    }

    /**
     * @return array{message: string}
     */
    public function delete(ResolvedGoogleCredentials $credentials, string $spreadsheetId, int $sheetId): array
    {
        $service = $this->createService($credentials);

        return $this->deleteSheet($service, $spreadsheetId, $sheetId);
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

    protected function addSheet(GoogleSheetsService $service, string $spreadsheetId, string $title): array
    {
        $addSheetRequest = new GoogleSheetsAddSheetRequest;
        $sheetProps = new GoogleSheetsSheetProperties;
        $sheetProps->setTitle($title);
        $addSheetRequest->setProperties($sheetProps);

        $request = new GoogleSheetsRequest;
        $request->setAddSheet($addSheetRequest);

        $batchUpdate = new GoogleSheetsBatchUpdateRequest;
        $batchUpdate->setRequests([$request]);

        $service->spreadsheets->batchUpdate($spreadsheetId, $batchUpdate);

        return ['message' => "Sheet '{$title}' added"];
    }

    protected function deleteSheet(GoogleSheetsService $service, string $spreadsheetId, int $sheetId): array
    {
        $request = new GoogleSheetsRequest;
        $deleteSheet = new GoogleSheetsDeleteSheetRequest;
        $deleteSheet->setSheetId($sheetId);
        $request->setDeleteSheet($deleteSheet);

        $batch = new GoogleSheetsBatchUpdateRequest;
        $batch->setRequests([$request]);

        $service->spreadsheets->batchUpdate($spreadsheetId, $batch);

        return ['message' => 'Sheet deleted'];
    }
}
