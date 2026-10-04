<?php

namespace App\Modules\Identity\Core\Repositories;

use App\Modules\Identity\Core\Entities\PasswordResetOtp;
use DateTimeImmutable;

interface PasswordResetOtpRepository
{
    /**
     * Insert or replace the OTP row for the given email. Any existing row is
     * overwritten: new hash, zero attempts, fresh expiry, unused.
     */
    public function replaceForEmail(string $email, string $codeHash, DateTimeImmutable $expiresAt): void;

    /**
     * Fetch the row for the given email with a pessimistic write lock.
     * Must be called inside a transaction.
     */
    public function lockByEmail(string $email): ?PasswordResetOtp;

    public function incrementAttempts(int $id): void;

    public function markUsed(int $id, DateTimeImmutable $now): void;
}
