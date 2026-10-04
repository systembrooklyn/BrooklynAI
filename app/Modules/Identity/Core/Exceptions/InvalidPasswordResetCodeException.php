<?php

namespace App\Modules\Identity\Core\Exceptions;

use RuntimeException;

final class InvalidPasswordResetCodeException extends RuntimeException
{
    public static function make(): self
    {
        return new self('The password reset code is invalid or has expired.');
    }
}
