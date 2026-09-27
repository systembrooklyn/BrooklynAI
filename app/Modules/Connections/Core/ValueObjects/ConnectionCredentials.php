<?php

namespace App\Modules\Connections\Core\ValueObjects;

use DateTimeImmutable;

final class ConnectionCredentials
{
    public function __construct(
        public readonly ?string $accessToken,
        public readonly ?string $refreshToken,
        public readonly ?DateTimeImmutable $expiresAt,
    ) {}

    public function isExpired(DateTimeImmutable $now): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt <= $now;
    }

    public function __toString(): string
    {
        return '[credentials]';
    }
}
