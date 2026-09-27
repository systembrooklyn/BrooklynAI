<?php

namespace App\Modules\Execution\Core\Exceptions;

use RuntimeException;

final class ExecutionNotFoundException extends RuntimeException
{
    public static function forUser(int $userId, int $executionId): self
    {
        return new self(sprintf(
            'Execution %d not found for user %d.',
            $executionId,
            $userId,
        ));
    }
}
