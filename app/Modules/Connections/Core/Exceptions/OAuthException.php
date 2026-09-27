<?php

namespace App\Modules\Connections\Core\Exceptions;

use RuntimeException;

final class OAuthException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public static function invalidState(): self
    {
        return new self('oauth_state_invalid', 'Invalid OAuth state.');
    }

    public static function expiredState(): self
    {
        return new self('oauth_state_expired', 'OAuth state expired.');
    }

    public static function replayedState(): self
    {
        return new self('oauth_state_replayed', 'OAuth state already consumed.');
    }

    public static function codeExchangeFailed(): self
    {
        return new self('oauth_code_exchange_failed', 'Failed to exchange authorization code.');
    }

    public static function scopeInvalid(): self
    {
        return new self('oauth_scope_invalid', 'Required scopes were not granted.');
    }

    public static function accountFetchFailed(): self
    {
        return new self('oauth_account_fetch_failed', 'Failed to fetch Google account identity.');
    }

    public static function connectionFailed(): self
    {
        return new self('oauth_connection_failed', 'Failed to persist connection.');
    }
}
