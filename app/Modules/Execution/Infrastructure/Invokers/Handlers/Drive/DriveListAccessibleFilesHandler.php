<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveListAccessibleFilesHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $folderId = $resolvedConfig['folder_id'] ?? null;
        $pageSize = $resolvedConfig['page_size'] ?? 100;

        $pageSize = is_numeric($pageSize) ? (int) $pageSize : 100;
        $pageSize = max(1, min($pageSize, 1000));

        $query = ["trashed=false"];
        if (is_string($folderId) && $folderId !== '') {
            $query[] = "'".str_replace("'", "\\'", $folderId)."' in parents";
        }

        $creds = $this->credentials->resolve($userId, $connectionId);

        return $this->drive->listFiles($creds, $query, $pageSize);
    }
}
