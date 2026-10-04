<?php

namespace App\Modules\Identity\Core\Entities;

use DateTimeImmutable;

final class PasswordResetOtp
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $codeHash,
        public readonly int $attempts,
        public readonly DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $usedAt,
    ) {}

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }
}
