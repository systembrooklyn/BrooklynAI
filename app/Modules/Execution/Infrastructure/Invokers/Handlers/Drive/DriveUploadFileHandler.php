<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;
use Illuminate\Support\Facades\Storage;

final class DriveUploadFileHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveFileUploader $uploader,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $storagePath = $resolvedConfig['storage_path'] ?? null;
        $filename = $resolvedConfig['filename'] ?? null;
        $mimeType = $resolvedConfig['mime_type'] ?? null;
        $folderId = $resolvedConfig['folder_id'] ?? null;

        if (! is_string($storagePath) || $storagePath === '') {
            throw ActionInvocationFailed::forInvalidConfig('storage_path');
        }
        if (str_contains($storagePath, '..') || str_starts_with($storagePath, '/') || str_contains($storagePath, '://')) {
            throw ActionInvocationFailed::forInvalidConfig('storage_path');
        }
        if (! is_string($filename) || $filename === '') {
            throw ActionInvocationFailed::forInvalidConfig('filename');
        }
        if (! is_string($mimeType) || $mimeType === '') {
            throw ActionInvocationFailed::forInvalidConfig('mime_type');
        }

        if (! Storage::disk('local')->exists($storagePath)) {
            throw new ActionInvocationFailed('File not found in storage: ' . $storagePath);
        }

        $bytes = (string) Storage::disk('local')->get($storagePath);
        $creds = $this->credentials->resolve($userId, $connectionId);

        // If a folder is specified, use the full client (uploader targets root Drive).
        if (is_string($folderId) && $folderId !== '') {
            $drive = app(\App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient::class);
            $result = $drive->createFile($creds, $filename, $mimeType, $bytes, $folderId);

            return [
                'file_id' => $result['id'],
                'web_view_link' => $result['web_view_link'],
                'filename' => $filename,
                'size' => strlen($bytes),
            ];
        }

        $result = $this->uploader->uploadWithMetadata($creds, $bytes, $filename, $mimeType);

        return [
            'file_id' => $result['id'],
            'web_view_link' => $result['web_view_link'],
            'filename' => $filename,
            'size' => strlen($bytes),
        ];
    }
}
