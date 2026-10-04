<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\ResetPasswordAction;
use App\Modules\Identity\Application\DTOs\ResetPasswordInput;
use App\Modules\Identity\Core\Exceptions\InvalidPasswordResetCodeException;
use App\Modules\Identity\Http\Requests\ResetPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class ResetPasswordController extends Controller
{
    public function __invoke(
        ResetPasswordRequest $request,
        ResetPasswordAction $action,
    ): JsonResponse {
        $email = strtolower((string) $request->input('email'));
        $ip = (string) $request->ip();

        $emailKey = 'password-reset:reset:email:' . $email;
        $ipKey = 'password-reset:reset:ip:' . $ip;

        $emailLimit = (int) config('identity.password_reset.reset_rate_limit_per_email_per_hour', 20);
        $ipLimit = (int) config('identity.password_reset.reset_rate_limit_per_ip_per_hour', 60);

        if (RateLimiter::tooManyAttempts($emailKey, $emailLimit)
            || RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
            return response()->json([
                'message' => __('identity::messages.too_many_requests'),
            ], 429)->header('Retry-After', '3600');
        }

        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        try {
            $action->execute(new ResetPasswordInput(
                email: $email,
                code: (string) $request->input('code'),
                password: (string) $request->input('password'),
            ));
        } catch (InvalidPasswordResetCodeException) {
            return response()->json([
                'message' => __('identity::messages.invalid_or_expired_code'),
            ], 422);
        }

        return response()->json([
            'message' => __('identity::messages.password_reset_success'),
        ]);
    }
}
