<?php

namespace App\Modules\Automation\Core\Exceptions;

use RuntimeException;

final class TemplateSyntaxException extends RuntimeException
{
    public static function forPath(string $expression): self
    {
        return new self(sprintf(
            'Invalid template expression: "%s".',
            self::truncate($expression),
        ));
    }

    public static function forString(string $value): self
    {
        return new self(sprintf(
            'Invalid template syntax in string: "%s".',
            self::truncate($value),
        ));
    }

    private static function truncate(string $value): string
    {
        return strlen($value) > 200 ? substr($value, 0, 200).'…' : $value;
    }
}
