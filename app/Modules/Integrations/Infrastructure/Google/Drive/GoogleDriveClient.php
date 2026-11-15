<?php

namespace App\Modules\Integrations\Infrastructure\Google\Drive;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile as GoogleDriveFile;
use Google\Service\Drive\Permission as GoogleDrivePermission;
use Google\Service\Exception as GoogleServiceException;

/**
 * Phase 3: Google Drive API v3 client using only drive.file scope.
 *
 * Access is limited to files created by BrooklynAI or selected via Picker.
 * No method in this class may call APIs that assume Drive-wide access.
 */
class GoogleDriveClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    public function getFile(ResolvedGoogleCredentials $credentials, string $fileId, array $fields = []): array
    {
        $service = $this->createService($credentials);
        $params = $fields === [] ? [] : ['fields' => implode(',', $fields)];
        $file = $service->files->get($fileId, $params);

        return $this->mapFile($file);
    }

    public function createFile(ResolvedGoogleCredentials $credentials, string $filename, string $mimeType, string $content, ?string $folderId = null): array
    {
        $service = $this->createService($credentials);
        $metadata = new GoogleDriveFile(['name' => $filename, 'mimeType' => $mimeType]);
        if ($folderId !== null && $folderId !== '') {
            $metadata->setParents([$folderId]);
        }

        $created = $service->files->create($metadata, [
            'data' => $content,
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,size,webViewLink,createdTime,modifiedTime',
        ]);

        return $this->mapFile($created);
    }

    public function createFolder(ResolvedGoogleCredentials $credentials, string $name, ?string $parentId = null): array
    {
        $metadata = new GoogleDriveFile([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);
        if ($parentId !== null && $parentId !== '') {
            $metadata->setParents([$parentId]);
        }

        $service = $this->createService($credentials);
        $created = $service->files->create($metadata, [
            'fields' => 'id,name,mimeType,createdTime,webViewLink',
        ]);

        return $this->mapFile($created);
    }

    public function updateMetadata(ResolvedGoogleCredentials $credentials, string $fileId, array $updates): array
    {
        $service = $this->createService($credentials);
        $metadata = new GoogleDriveFile($updates);
        $updated = $service->files->update($fileId, $metadata, [
            'fields' => 'id,name,description,properties,modifiedTime',
        ]);

        return $this->mapFile($updated);
    }

    public function moveFile(ResolvedGoogleCredentials $credentials, string $fileId, string $newParentId): array
    {
        $service = $this->createService($credentials);
        $file = $service->files->get($fileId, ['fields' => 'parents']);
        $oldParents = implode(',', $file->getParents() ?? []);

        $updated = $service->files->update($fileId, new GoogleDriveFile, [
            'addParents' => $newParentId,
            'removeParents' => $oldParents,
            'fields' => 'id,name,parents',
        ]);

        return $this->mapFile($updated);
    }

    public function copyFile(ResolvedGoogleCredentials $credentials, string $fileId, string $newName, ?string $folderId = null): array
    {
        $service = $this->createService($credentials);
        $metadata = new GoogleDriveFile(['name' => $newName]);
        if ($folderId !== null && $folderId !== '') {
            $metadata->setParents([$folderId]);
        }

        $copied = $service->files->copy($fileId, $metadata, [
            'fields' => 'id,name,mimeType,size,webViewLink',
        ]);

        return $this->mapFile($copied);
    }

    public function deleteFile(ResolvedGoogleCredentials $credentials, string $fileId): void
    {
        $service = $this->createService($credentials);
        $service->files->delete($fileId);
    }

    public function listFiles(ResolvedGoogleCredentials $credentials, array $query, int $pageSize = 100, ?string $pageToken = null): array
    {
        $service = $this->createService($credentials);
        $params = [
            'q' => implode(' and ', $query),
            'fields' => 'files(id,name,mimeType,size,webViewLink,modifiedTime,parents),nextPageToken',
            'pageSize' => min($pageSize, 1000),
            'orderBy' => 'modifiedTime desc',
        ];
        if ($pageToken !== null && $pageToken !== '') {
            $params['pageToken'] = $pageToken;
        }

        $result = $service->files->listFiles($params);

        return [
            'files' => array_map(fn($f) => $this->mapFile($f), $result->getFiles() ?? []),
            'next_page_token' => $result->getNextPageToken(),
        ];
    }

    public function downloadFile(ResolvedGoogleCredentials $credentials, string $fileId): string
    {
        $service = $this->createService($credentials);
        $response = $service->files->get($fileId, ['alt' => 'media']);

        // Google Drive API v3: files.get with alt=media returns a PSR-7 Response
        // only for files that support direct binary download. Google Workspace
        // files (Docs, Sheets, Slides, Forms) return metadata instead and must
        // be exported via files.export. Detect that case and surface it clearly.
        if ($response instanceof \Psr\Http\Message\ResponseInterface) {
            return (string) $response->getBody();
        }

        if ($response instanceof GoogleDriveFile) {
            $mime = $response->getMimeType();

            if (is_string($mime) && str_starts_with($mime, 'application/vnd.google-apps.')) {
                throw new \RuntimeException(
                    'Drive file is a Google Workspace document (' . $mime . ') '
                        . 'and cannot be downloaded as binary. Use the '
                        . '"Export Google Workspace Files" action instead.'
                );
            }
        }

        throw new \RuntimeException(
            'Drive download did not return binary content. '
                . 'Verify the file exists, is accessible under drive.file scope, '
                . 'and is not a Google Workspace document.'
        );
    }
    public function createPermission(
        ResolvedGoogleCredentials $credentials,
        string $fileId,
        string $type,
        string $role,
        ?string $emailAddress = null,
        ?string $domain = null,
    ): array {
        if ($type === 'anyone') {
            throw new \InvalidArgumentException(
                'Sharing with type:anyone is not permitted in Phase 3. Use user, group, or domain.'
            );
        }

        $permission = new GoogleDrivePermission([
            'type' => $type,
            'role' => $role,
        ]);
        if ($emailAddress !== null) {
            $permission->setEmailAddress($emailAddress);
        }
        if ($domain !== null) {
            $permission->setDomain($domain);
        }

        $service = $this->createService($credentials);
        $created = $service->permissions->create($fileId, $permission, [
            'fields' => 'id,type,role,emailAddress,domain',
        ]);

        return [
            'permission_id' => $created->getId(),
            'type' => $created->getType(),
            'role' => $created->getRole(),
            'email_address' => $created->getEmailAddress(),
            'domain' => $created->getDomain(),
        ];
    }

    public function getStartPageToken(ResolvedGoogleCredentials $credentials): string
    {
        $service = $this->createService($credentials);
        $response = $service->changes->getStartPageToken();

        return (string) $response->getStartPageToken();
    }

    /**
     * @return array{changes: array<int, array<string, mixed>>, new_start_page_token: ?string, next_page_token: ?string}
     */
    public function listChanges(ResolvedGoogleCredentials $credentials, string $pageToken, int $pageSize = 100): array
    {
        $service = $this->createService($credentials);

        $response = $service->changes->listChanges($pageToken, [
            'pageSize' => min($pageSize, 1000),
            'fields' => 'changes(changeType,fileId,removed,file(id,name,mimeType,size,parents,modifiedTime,webViewLink)),nextPageToken,newStartPageToken',
            'includeRemoved' => true,
        ]);

        $changes = [];
        foreach ($response->getChanges() ?? [] as $change) {
            $file = $change->getFile();
            $changes[] = [
                'file_id' => $change->getFileId(),
                'removed' => (bool) $change->getRemoved(),
                'file' => $file !== null ? $this->mapFile($file) : null,
                'change_type' => $change->getChangeType(),
            ];
        }

        return [
            'changes' => $changes,
            'new_start_page_token' => $response->getNewStartPageToken(),
            'next_page_token' => $response->getNextPageToken(),
        ];
    }

    private function mapFile(GoogleDriveFile $file): array
    {
        return [
            'id' => $file->getId(),
            'name' => $file->getName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'web_view_link' => $file->getWebViewLink(),
            'parents' => $file->getParents() ?? [],
            'description' => $file->getDescription(),
            'properties' => $file->getProperties() ?? [],
            'created_time' => $file->getCreatedTime(),
            'modified_time' => $file->getModifiedTime(),
        ];
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleDriveService
    {
        $client = new GoogleClient;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setAccessType('offline');
        $client->addScope(self::SCOPE);

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

    public function exportFile(ResolvedGoogleCredentials $credentials, string $fileId, string $mimeType): string
    {
        $service = $this->createService($credentials);
        $response = $service->files->export($fileId, $mimeType, ['alt' => 'media']);

        if ($response instanceof \Psr\Http\Message\ResponseInterface) {
            return (string) $response->getBody();
        }

        throw new \RuntimeException(
            'Drive export did not return binary content. '
                . 'The file may not be a Google Workspace file, or the target mimeType '
                . '(' . $mimeType . ') is not a valid export format for it.'
        );
    }
}
