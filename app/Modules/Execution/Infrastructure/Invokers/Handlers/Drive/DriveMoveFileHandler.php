<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveMoveFileHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $fileId = $resolvedConfig['file_id'] ?? null;
        $newParentId = $resolvedConfig['new_parent_id'] ?? null;

        if (! is_string($fileId) || $fileId === '') {
            throw ActionInvocationFailed::forInvalidConfig('file_id');
        }
        if (! is_string($newParentId) || $newParentId === '') {
            throw ActionInvocationFailed::forInvalidConfig('new_parent_id');
        }

        $creds = $this->credentials->resolve($userId, $connectionId);

        return $this->drive->moveFile($creds, $fileId, $newParentId);
    }
}
