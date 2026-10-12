<?php

namespace App\Modules\Integrations\Core\Exceptions;

use RuntimeException;

final class DriveProviderException extends RuntimeException
{
    private function __construct(
        string $message,
        public readonly bool $retryable,
    ) {
        parent::__construct($message);
    }

    public static function transient(int $code, string $message): self
    {
        return new self(
            sprintf('Google Drive API transient failure (%d): %s', $code, $message),
            retryable: true,
        );
    }

    public static function permanent(int $code, string $message): self
    {
        return new self(
            sprintf('Google Drive API permanent failure (%d): %s', $code, $message),
            retryable: false,
        );
    }
}
