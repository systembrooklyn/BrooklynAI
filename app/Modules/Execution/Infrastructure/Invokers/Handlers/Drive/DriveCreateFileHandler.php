<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveCreateFileHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $filename = $resolvedConfig['filename'] ?? null;
        $content = $resolvedConfig['content'] ?? null;
        $mimeType = $resolvedConfig['mime_type'] ?? null;
        $folderId = $resolvedConfig['folder_id'] ?? null;

        if (! is_string($filename) || $filename === '') {
            throw ActionInvocationFailed::forInvalidConfig('filename');
        }
        if (! is_string($content)) {
            throw ActionInvocationFailed::forInvalidConfig('content');
        }
        if (! is_string($mimeType) || $mimeType === '') {
            throw ActionInvocationFailed::forInvalidConfig('mime_type');
        }

        $creds = $this->credentials->resolve($userId, $connectionId);

        return $this->drive->createFile(
            $creds,
            $filename,
            $mimeType,
            $content,
            is_string($folderId) && $folderId !== '' ? $folderId : null,
        );
    }
}
