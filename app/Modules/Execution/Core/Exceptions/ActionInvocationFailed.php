<?php

namespace App\Modules\Execution\Core\Exceptions;

use RuntimeException;

final class ActionInvocationFailed extends RuntimeException
{
    public static function forUnknownIntegration(string $integrationKey): self
    {
        return new self(sprintf('Unknown integration "%s".', $integrationKey));
    }

    public static function forUnknownAction(string $integrationKey, string $actionKey): self
    {
        return new self(sprintf('Unknown action "%s.%s".', $integrationKey, $actionKey));
    }

    public static function forMissingHandler(string $integrationKey, string $actionKey): self
    {
        return new self(sprintf('No handler registered for "%s.%s".', $integrationKey, $actionKey));
    }

    public static function forInvalidConfig(string $key): self
    {
        return new self(sprintf('Invalid or missing config key "%s".', $key));
    }

    public static function forMissingUser(int $userId): self
    {
        return new self(sprintf('User %d not found.', $userId));
    }
}
