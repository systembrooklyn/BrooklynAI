<?php

namespace App\Modules\Integrations\Core\Exceptions;

use RuntimeException;

final class IntegrationNotFound extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Integration "%s" is not registered.', $key));
    }
}
