<?php

namespace App\Modules\Integrations\Infrastructure\Google\Sheets;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;

class GoogleSheetsLister
{
    /**
     * @return array<int, array{id: string|null, name: string|null, lastModified: mixed, ownerEmail: string|null, url: string|null}>
     */
    public function list(ResolvedGoogleCredentials $credentials): array
    {
        $service = $this->createService($credentials);

        return $this->fetchSpreadsheets($service);
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleDriveService
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/drive.readonly');

        $expiresIn = 3600;
        if ($credentials->expiresAt !== null) {
            $expiresIn = max(0, $credentials->expiresAt->getTimestamp() - time());
        }

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
        ]);

        return new GoogleDriveService($client);
    }

    /**
     * @return array<int, array{id: string|null, name: string|null, lastModified: mixed, ownerEmail: string|null, url: string|null}>
     */
    protected function fetchSpreadsheets(GoogleDriveService $service): array
    {
        $optParams = [
            'q' => "mimeType='application/vnd.google-apps.spreadsheet' and trashed=false",
            'fields' => 'files(id, name, modifiedTime, owners, webViewLink)',
            'orderBy' => 'modifiedTime desc',
            'pageSize' => 100,
        ];

        $response = $service->files->listFiles($optParams);
        $files = $response->getFiles();

        $result = [];
        foreach ($files as $file) {
            $owners = $file->getOwners();
            $result[] = [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'lastModified' => $file->getModifiedTime(),
                'ownerEmail' => $owners ? $owners[0]->getEmailAddress() : null,
                'url' => $file->getWebViewLink(),
            ];
        }

        return $result;
    }
}
