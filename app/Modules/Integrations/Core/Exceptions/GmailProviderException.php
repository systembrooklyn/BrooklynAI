<?php

namespace App\Modules\Integrations\Core\Exceptions;

use RuntimeException;

final class GmailProviderException extends RuntimeException
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
            sprintf('Gmail API transient failure (%d): %s', $code, $message),
            retryable: true,
        );
    }

    public static function permanent(int $code, string $message): self
    {
        return new self(
            sprintf('Gmail API permanent failure (%d): %s', $code, $message),
            retryable: false,
        );
    }
}
