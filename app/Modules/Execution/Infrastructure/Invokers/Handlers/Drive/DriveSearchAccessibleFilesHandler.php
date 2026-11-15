<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveSearchAccessibleFilesHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $searchQuery = $resolvedConfig['query'] ?? null;
        $pageSizeRaw = $resolvedConfig['page_size'] ?? 100;

        if (! is_string($searchQuery)) {
            throw ActionInvocationFailed::forInvalidConfig('query');
        }

        $searchQuery = trim($searchQuery);

        if ($searchQuery === '') {
            throw ActionInvocationFailed::forInvalidConfig('query');
        }

        // Reject control characters. Drive query fragments must be printable text.
        if (preg_match('/[\x00-\x1F\x7F]/', $searchQuery) === 1) {
            throw ActionInvocationFailed::forInvalidConfig('query');
        }

        $pageSize = is_numeric($pageSizeRaw) ? (int) $pageSizeRaw : 100;
        $pageSize = max(1, min($pageSize, 1000));

        $creds = $this->credentials->resolve($userId, $connectionId);

        // Prepend trashed=false; the caller's query is composed with it.
        // Users must supply a valid Drive query fragment, e.g.:
        //   name contains 'report'
        //   mimeType = 'application/pdf'
        //   fullText contains 'invoice'
        $query = ['trashed=false', '('.$searchQuery.')'];

        return $this->drive->listFiles($creds, $query, $pageSize);
    }
}
