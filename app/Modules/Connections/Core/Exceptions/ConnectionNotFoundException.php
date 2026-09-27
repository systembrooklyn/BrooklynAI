<?php

namespace App\Modules\Connections\Core\Exceptions;

use RuntimeException;

final class ConnectionNotFoundException extends RuntimeException
{
    public static function forUser(int $userId, int $connectionId): self
    {
        return new self(sprintf('Connection %d not found for user %d.', $connectionId, $userId));
    }
}
