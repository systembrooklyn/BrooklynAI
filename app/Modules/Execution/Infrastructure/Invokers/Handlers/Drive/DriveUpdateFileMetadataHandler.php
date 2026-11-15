<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveUpdateFileMetadataHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $fileId = $resolvedConfig['file_id'] ?? null;
        $description = $resolvedConfig['description'] ?? null;
        $propertiesRaw = $resolvedConfig['properties'] ?? null;

        if (! is_string($fileId) || $fileId === '') {
            throw ActionInvocationFailed::forInvalidConfig('file_id');
        }

        $updates = [];
        if (is_string($description)) {
            $updates['description'] = $description;
        }
        if (is_string($propertiesRaw) && $propertiesRaw !== '') {
            $decoded = json_decode($propertiesRaw, true);
            if (! is_array($decoded)) {
                throw ActionInvocationFailed::forInvalidConfig('properties');
            }
            $updates['properties'] = $decoded;
        }

        if ($updates === []) {
            throw new ActionInvocationFailed('At least one of description or properties must be provided.');
        }

        $creds = $this->credentials->resolve($userId, $connectionId);

        return $this->drive->updateMetadata($creds, $fileId, $updates);
    }
}
