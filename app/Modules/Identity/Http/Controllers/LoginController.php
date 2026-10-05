<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Application\Actions\LoginAction;
use App\Modules\Identity\Application\DTOs\LoginInput;
use App\Modules\Identity\Http\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;


class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;
    public function __invoke(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $email = strtolower((string) $request->input('email'));
        $key = 'login:' . $email . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => __('identity::messages.too_many_attempts'),
            ], 429)->header('Retry-After', (string) $retryAfter);
        }
        $result = $action->execute(new LoginInput(
            email: (string) $request->input('email'),
            password: (string) $request->input('password'),
        ));

        if ($result === null) {
            return response()->json([
                'message' => __('identity::messages.invalid_credentials'),
            ], 401);
        }

        // Successful login clears any accrued failures for this email+IP.
        RateLimiter::clear($key);

        return response()->json([
            'message' => __('identity::messages.login_success'),
            'data' => $result,
        ]);
    }
}
