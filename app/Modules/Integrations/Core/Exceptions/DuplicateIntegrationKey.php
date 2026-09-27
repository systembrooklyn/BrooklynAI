<?php

namespace App\Modules\Integrations\Core\Exceptions;

use RuntimeException;

final class DuplicateIntegrationKey extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Integration "%s" is already registered.', $key));
    }
}
