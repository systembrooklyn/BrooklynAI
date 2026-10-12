<?php

namespace App\Modules\Integrations\Infrastructure\Google\Drive;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Core\Exceptions\DriveProviderException;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile as GoogleDriveFile;
use Google\Service\Exception as GoogleServiceException;

/**
 * Uploads a single file into the authenticated user's Google Drive.
 *
 * Uses the least-privilege scope https://www.googleapis.com/auth/drive.file:
 * the resulting file is owned by the user's Google account and visible only
 * to that account. This class never calls permissions->create and never
 * shares the file publicly.
 *
 * The bytes are provided as an in-memory string and never written to disk.
 */
class GoogleDriveFileUploader
{
    /**
     * Upload $bytes to the user's Drive and return the created file ID.
     *
     * @throws DriveProviderException On any Drive API failure or a missing file ID.
     */
    public function upload(
        ResolvedGoogleCredentials $credentials,
        string $bytes,
        string $filename,
        string $mimeType,
    ): string {
        return $this->uploadWithMetadata($credentials, $bytes, $filename, $mimeType)['id'];
    }

    /**
     * Upload $bytes to the user's Drive and return the created file ID
     * together with the Drive webViewLink when Google returns one.
     *
     * The link is metadata only. It does not alter Drive permissions, and
     * the created file remains private to the authenticated Google account.
     * A missing or empty webViewLink is normalized to null.
     *
     * @return array{id: string, web_view_link: string|null}
     *
     * @throws DriveProviderException On any Drive API failure or a missing file ID.
     */
    public function uploadWithMetadata(
        ResolvedGoogleCredentials $credentials,
        string $bytes,
        string $filename,
        string $mimeType,
    ): array {
        $metadata = new GoogleDriveFile([
            'name' => $filename,
            'mimeType' => $mimeType,
        ]);

        try {
            $created = $this->performUpload(
                $this->createService($credentials),
                $metadata,
                $bytes,
                $mimeType,
            );
        } catch (GoogleServiceException $e) {
            throw $this->translate($e);
        }

        $id = $created->getId();

        if (! is_string($id) || $id === '') {
            throw DriveProviderException::permanent(
                0,
                'Google Drive upload succeeded but returned no file ID.',
            );
        }

        $link = $created->getWebViewLink();

        return [
            'id' => $id,
            'web_view_link' => (is_string($link) && $link !== '') ? $link : null,
        ];
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleDriveService
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setAccessType('offline');
        $client->addScope('https://www.googleapis.com/auth/drive.file');

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
     * Test seam. Overridden by test doubles; production always calls the
     * real Drive API.
     */
    protected function performUpload(
        GoogleDriveService $service,
        GoogleDriveFile $metadata,
        string $bytes,
        string $mimeType,
    ): GoogleDriveFile {
        return $service->files->create($metadata, [
            'data' => $bytes,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,webViewLink',
        ]);
    }

    private function translate(GoogleServiceException $e): DriveProviderException
    {
        $code = (int) $e->getCode();

        if ($code === 429 || $code >= 500) {
            return DriveProviderException::transient($code, $e->getMessage());
        }

        return DriveProviderException::permanent($code, $e->getMessage());
    }
}
