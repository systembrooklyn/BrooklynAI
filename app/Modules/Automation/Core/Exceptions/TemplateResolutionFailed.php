<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class TemplateResolutionFailed extends RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Template path "%s" cannot be resolved.', $path));
    }

    public static function forTypeMismatch(string $path): self
    {
        return new self(sprintf('Template path "%s" points to a non-array value.', $path));
    }
}
