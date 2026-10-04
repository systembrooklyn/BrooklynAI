<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\RequestPasswordResetAction;
use App\Modules\Identity\Application\DTOs\RequestPasswordResetInput;
use App\Modules\Identity\Http\Requests\RequestPasswordResetRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;

class RequestPasswordResetController extends Controller
{
    public function __invoke(
        RequestPasswordResetRequest $request,
        RequestPasswordResetAction $action,
    ): JsonResponse {
        $email = strtolower((string) $request->input('email'));
        $ip = (string) $request->ip();

        $emailKey = 'password-reset:forgot:email:' . $email;
        $ipKey = 'password-reset:forgot:ip:' . $ip;

        $emailLimit = (int) config('identity.password_reset.forgot_rate_limit_per_email_per_hour', 5);
        $ipLimit = (int) config('identity.password_reset.forgot_rate_limit_per_ip_per_hour', 20);

        if (RateLimiter::tooManyAttempts($emailKey, $emailLimit)
            || RateLimiter::tooManyAttempts($ipKey, $ipLimit)) {
            return response()->json([
                'message' => __('identity::messages.too_many_requests'),
            ], 429)->header('Retry-After', '3600');
        }

        RateLimiter::hit($emailKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        $action->execute(new RequestPasswordResetInput($email));

        return response()->json([
            'message' => __('identity::messages.password_reset_requested'),
        ]);
    }
}
