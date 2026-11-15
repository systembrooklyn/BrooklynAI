<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class FakeGoogleDriveClient extends GoogleDriveClient
{
    /** @var array<int, array<string, mixed>> */
    public array $getCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $createFileCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $createFolderCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $updateMetadataCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $moveFileCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $copyFileCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $deleteFileCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $listFilesCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $downloadFileCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $exportFileCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $createPermissionCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $getStartPageTokenCalls = [];
    /** @var array<int, array<string, mixed>> */
    public array $listChangesCalls = [];

    public array $nextGetFileReturn = [];
    public array $nextCreateFileReturn = [];
    public array $nextCreateFolderReturn = [];
    public array $nextUpdateMetadataReturn = [];
    public array $nextMoveFileReturn = [];
    public array $nextCopyFileReturn = [];
    public array $nextListFilesReturn = ['files' => [], 'next_page_token' => null];
    public string $nextDownloadFileReturn = '';
    public string $nextExportFileReturn = '';
    public array $nextCreatePermissionReturn = [];
    public string $nextStartPageTokenReturn = 'start-token';
    public array $nextListChangesReturn = [
        'changes' => [],
        'new_start_page_token' => 'next-token',
        'next_page_token' => null,
    ];

    /**
     * FIFO queue for listChanges() responses. When non-empty, each call to
     * listChanges() returns and removes the next item. When empty, falls
     * back to nextListChangesReturn.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $listChangesQueue = [];

    public function getFile(ResolvedGoogleCredentials $credentials, string $fileId, array $fields = []): array
    {
        $this->getCalls[] = ['file_id' => $fileId, 'fields' => $fields];
        return $this->nextGetFileReturn;
    }

    public function createFile(ResolvedGoogleCredentials $credentials, string $filename, string $mimeType, string $content, ?string $folderId = null): array
    {
        $this->createFileCalls[] = [
            'filename' => $filename,
            'mime_type' => $mimeType,
            'content' => $content,
            'folder_id' => $folderId,
        ];
        return $this->nextCreateFileReturn;
    }

    public function createFolder(ResolvedGoogleCredentials $credentials, string $name, ?string $parentId = null): array
    {
        $this->createFolderCalls[] = ['name' => $name, 'parent_id' => $parentId];
        return $this->nextCreateFolderReturn;
    }

    public function updateMetadata(ResolvedGoogleCredentials $credentials, string $fileId, array $updates): array
    {
        $this->updateMetadataCalls[] = ['file_id' => $fileId, 'updates' => $updates];
        return $this->nextUpdateMetadataReturn;
    }

    public function moveFile(ResolvedGoogleCredentials $credentials, string $fileId, string $newParentId): array
    {
        $this->moveFileCalls[] = ['file_id' => $fileId, 'new_parent_id' => $newParentId];
        return $this->nextMoveFileReturn;
    }

    public function copyFile(ResolvedGoogleCredentials $credentials, string $fileId, string $newName, ?string $folderId = null): array
    {
        $this->copyFileCalls[] = ['file_id' => $fileId, 'new_name' => $newName, 'folder_id' => $folderId];
        return $this->nextCopyFileReturn;
    }

    public function deleteFile(ResolvedGoogleCredentials $credentials, string $fileId): void
    {
        $this->deleteFileCalls[] = ['file_id' => $fileId];
    }

    public function listFiles(ResolvedGoogleCredentials $credentials, array $query, int $pageSize = 100, ?string $pageToken = null): array
    {
        $this->listFilesCalls[] = ['query' => $query, 'page_size' => $pageSize, 'page_token' => $pageToken];
        return $this->nextListFilesReturn;
    }

    public function downloadFile(ResolvedGoogleCredentials $credentials, string $fileId): string
    {
        $this->downloadFileCalls[] = ['file_id' => $fileId];
        return $this->nextDownloadFileReturn;
    }

    public function exportFile(ResolvedGoogleCredentials $credentials, string $fileId, string $mimeType): string
    {
        $this->exportFileCalls[] = ['file_id' => $fileId, 'mime_type' => $mimeType];
        return $this->nextExportFileReturn;
    }

    public function createPermission(
        ResolvedGoogleCredentials $credentials,
        string $fileId,
        string $type,
        string $role,
        ?string $emailAddress = null,
        ?string $domain = null,
    ): array {
        $this->createPermissionCalls[] = [
            'file_id' => $fileId,
            'type' => $type,
            'role' => $role,
            'email_address' => $emailAddress,
            'domain' => $domain,
        ];
        return $this->nextCreatePermissionReturn;
    }

    public function getStartPageToken(ResolvedGoogleCredentials $credentials): string
    {
        $this->getStartPageTokenCalls[] = [];
        return $this->nextStartPageTokenReturn;
    }

    public function listChanges(ResolvedGoogleCredentials $credentials, string $pageToken, int $pageSize = 100): array
    {
        $this->listChangesCalls[] = ['page_token' => $pageToken, 'page_size' => $pageSize];

        if (! empty($this->listChangesQueue)) {
            return array_shift($this->listChangesQueue);
        }

        return $this->nextListChangesReturn;
    }
}
