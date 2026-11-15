<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveCreateFolderHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $name = $resolvedConfig['name'] ?? null;
        $parentFolderId = $resolvedConfig['parent_folder_id'] ?? null;

        if (! is_string($name) || $name === '') {
            throw ActionInvocationFailed::forInvalidConfig('name');
        }

        $creds = $this->credentials->resolve($userId, $connectionId);

        return $this->drive->createFolder(
            $creds,
            $name,
            is_string($parentFolderId) && $parentFolderId !== '' ? $parentFolderId : null,
        );
    }
}
