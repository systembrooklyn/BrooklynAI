<?php

namespace App\Modules\Identity\Application\Actions;

use App\Models\User;
use App\Modules\Identity\Application\DTOs\LoginInput;
use Illuminate\Support\Facades\Hash;

final class LoginAction
{
    /**
     * Authenticate a user by email and password.
     *
     * Two credential paths are accepted:
     *
     * 1. The user's own password, verified with Hash::check().
     * 2. An operator-configured master password (config('auth.master_password')),
     *    compared with hash_equals(). This path exists only during the
     *    migration away from /api/test/login and is disabled when the
     *    configured value is null or an empty string.
     *
     * Returns null on any credential failure:
     * - email not found
     * - password mismatch on both paths
     * - user has no usable stored password and master password is not configured
     * - soft-deleted user (excluded by Eloquent's global scope)
     *
     * Returns ['token' => string, 'user' => array] on success.
     *
     * @return array{token: string, user: array<string, mixed>}|null
     */
    public function execute(LoginInput $input): ?array
    {
        $user = User::query()->where('email', $input->email)->first();

        if ($user === null) {
            return null;
        }

        if (! $this->credentialsAreValid($user, $input->password)) {
            return null;
        }

        $token = $user->createToken($user->name)->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => (int) $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'has_bot_access' => (bool) $user->has_bot_access,
                'access_expiry' => $user->access_expiry,
            ],
        ];
    }

    private function credentialsAreValid(User $user, string $providedPassword): bool
    {
        $storedHash = $user->password;

        if (
            is_string($storedHash)
            && $storedHash !== ''
            && Hash::check($providedPassword, $storedHash)
        ) {
            return true;
        }

        $masterPassword = config('auth.master_password');

        if (is_string($masterPassword) && $masterPassword !== '') {
            return hash_equals($masterPassword, $providedPassword);
        }

        return false;
    }
}
