<?php

namespace App\Modules\Identity\Application\Services;

use App\Models\User;
use App\Modules\Identity\Core\Exceptions\InvalidPasswordResetCodeException;
use App\Modules\Identity\Core\Repositories\PasswordResetOtpRepository;
use App\Modules\Identity\Infrastructure\Notifications\PasswordResetOtpNotification;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class PasswordResetService
{
    public function __construct(
        private readonly PasswordResetOtpRepository $otps,
    ) {}

    /**
     * Issue a reset OTP for the given email.
     *
     * Silently no-ops when the email does not correspond to a non-deleted
     * user, so that the HTTP layer can return an identical response in both
     * cases and email enumeration is prevented.
     */
    public function requestReset(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return;
        }

        $code = $this->generateCode();
        $hash = Hash::make($code);
        $ttlMinutes = max(1, (int) config('identity.password_reset.code_ttl_minutes', 15));
        $expiresAt = (new DateTimeImmutable)->modify('+' . $ttlMinutes . ' minutes');

        $this->otps->replaceForEmail($email, $hash, $expiresAt);

        $user->notify(new PasswordResetOtpNotification($code));
    }

    /**
     * Verify the OTP and set the new password.
     *
     * @throws InvalidPasswordResetCodeException
     */
    public function resetPassword(string $email, string $code, string $newPassword): void
    {
        $maxAttempts = max(1, (int) config('identity.password_reset.max_attempts', 5));

        DB::transaction(function () use ($email, $code, $newPassword, $maxAttempts) {
            $otp = $this->otps->lockByEmail($email);

            if ($otp === null) {
                throw InvalidPasswordResetCodeException::make();
            }

            if ($otp->isUsed()) {
                throw InvalidPasswordResetCodeException::make();
            }

            $now = new DateTimeImmutable;

            if ($otp->isExpired($now)) {
                throw InvalidPasswordResetCodeException::make();
            }

            if ($otp->attempts >= $maxAttempts) {
                throw InvalidPasswordResetCodeException::make();
            }

            if (! Hash::check($code, $otp->codeHash)) {
                $this->otps->incrementAttempts($otp->id);
                throw InvalidPasswordResetCodeException::make();
            }

            // At this point the code is valid. Look up the user again in case
            // the account was deleted between issue and verify.
            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                throw InvalidPasswordResetCodeException::make();
            }

            $this->otps->markUsed($otp->id, $now);

            // User's 'password' cast is 'hashed', so this assignment auto-hashes.
            $user->password = $newPassword;
            $user->save();

            // Revoke every Sanctum token — a password reset invalidates all
            // existing sessions, including any that may be compromised.
            $user->tokens()->delete();
        });
    }

    private function generateCode(): string
    {
        $length = (int) config('identity.password_reset.code_length', 6);
        $length = max(4, min(10, $length));

        $min = (int) ('1' . str_repeat('0', $length - 1));
        $max = (int) str_repeat('9', $length);

        return (string) random_int($min, $max);
    }
}
