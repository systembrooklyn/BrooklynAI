<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;

final class DriveShareFileHandler implements ActionHandler
{
    private const ALLOWED_TYPES = ['user', 'group', 'domain'];
    private const ALLOWED_ROLES = ['reader', 'commenter', 'writer'];

    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $fileId = $resolvedConfig['file_id'] ?? null;
        $type = $resolvedConfig['type'] ?? null;
        $role = $resolvedConfig['role'] ?? null;
        $emailAddress = $resolvedConfig['email_address'] ?? null;
        $domain = $resolvedConfig['domain'] ?? null;

        if (! is_string($fileId) || $fileId === '') {
            throw ActionInvocationFailed::forInvalidConfig('file_id');
        }
        if (! is_string($type) || ! in_array($type, self::ALLOWED_TYPES, true)) {
            throw new ActionInvocationFailed(
                'type must be one of: '.implode(', ', self::ALLOWED_TYPES).'. Sharing with type:anyone is not permitted in Phase 3.'
            );
        }
        if (! is_string($role) || ! in_array($role, self::ALLOWED_ROLES, true)) {
            throw ActionInvocationFailed::forInvalidConfig('role');
        }
        if ($type === 'user' || $type === 'group') {
            if (! is_string($emailAddress) || $emailAddress === '') {
                throw ActionInvocationFailed::forInvalidConfig('email_address');
            }
        }
        if ($type === 'domain') {
            if (! is_string($domain) || $domain === '') {
                throw ActionInvocationFailed::forInvalidConfig('domain');
            }
        }

        $creds = $this->credentials->resolve($userId, $connectionId);

        return $this->drive->createPermission(
            $creds,
            $fileId,
            $type,
            $role,
            is_string($emailAddress) ? $emailAddress : null,
            is_string($domain) ? $domain : null,
        );
    }
}
