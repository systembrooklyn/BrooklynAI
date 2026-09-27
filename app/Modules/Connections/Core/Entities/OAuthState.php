<?php

namespace App\Modules\Connections\Core\Entities;

use DateTimeImmutable;

final class OAuthState
{
    public const TTL_SECONDS = 600;

    public function __construct(
        public readonly ?int $id,
        public readonly string $state,
        public readonly int $userId,
        public readonly string $provider,
        public readonly array $scopesRequested,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $consumedAt,
    ) {}

    public static function generate(int $userId, string $provider, array $scopes, DateTimeImmutable $now): self
    {
        return new self(
            id: null,
            state: bin2hex(random_bytes(32)),
            userId: $userId,
            provider: $provider,
            scopesRequested: array_values($scopes),
            expiresAt: $now->modify('+'.self::TTL_SECONDS.' seconds'),
            consumedAt: null,
        );
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }
}
