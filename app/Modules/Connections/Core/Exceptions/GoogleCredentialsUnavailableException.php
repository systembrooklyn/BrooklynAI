<?php

namespace App\Modules\Connections\Core\Exceptions;

use RuntimeException;
use Throwable;

final class GoogleCredentialsUnavailableException extends RuntimeException
{
    public static function forLegacyUser(int $userId): self
    {
        return new self(sprintf(
            'No usable Google credentials for user %d.',
            $userId
        ));
    }

    public static function forConnection(int $userId, int $connectionId): self
    {
        return new self(sprintf(
            'Connection %d for user %d has no usable Google credentials.',
            $connectionId,
            $userId
        ));
    }

    public static function refreshFailed(
        int $userId,
        ?int $connectionId,
        ?Throwable $previous = null,
    ): self {
        $target = $connectionId === null
            ? sprintf('legacy user %d', $userId)
            : sprintf('connection %d (user %d)', $connectionId, $userId);

        return new self(
            sprintf('Google token refresh failed for %s.', $target),
            0,
            $previous,
        );
    }
}
