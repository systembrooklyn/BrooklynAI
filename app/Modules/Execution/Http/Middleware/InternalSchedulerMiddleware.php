<?php

namespace App\Modules\Execution\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

final class InternalSchedulerMiddleware
{
    private const RATE_KEY = 'internal-scheduler-tick';

    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('internal_scheduler.token', '');

        if ($expected === '') {
            return response()->json(['error' => 'server_misconfigured'], 503);
        }

        $provided = $request->bearerToken();

        if (! is_string($provided) || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        $rateLimit = (int) config('internal_scheduler.rate_limit_per_minute', 20);

        if ($rateLimit > 0) {
            if (RateLimiter::tooManyAttempts(self::RATE_KEY, $rateLimit)) {
                $retryAfter = RateLimiter::availableIn(self::RATE_KEY);

                return response()->json(['error' => 'rate_limited'], 429)
                    ->header('Retry-After', (string) $retryAfter);
            }

            RateLimiter::hit(self::RATE_KEY, 60);
        }

        return $next($request);
    }
}
