<?php

namespace App\Modules\Connections\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Application\Actions\HandleGoogleCallbackAction;
use App\Modules\Connections\Application\Actions\StartGoogleConnectionAction;
use App\Modules\Connections\Core\Entities\OAuthState;
use App\Modules\Connections\Core\Exceptions\OAuthException;
use App\Modules\Connections\Core\Repositories\OAuthStateRepository;
use App\Modules\Connections\Http\Requests\StartGoogleConnectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleConnectionController extends Controller
{
    private const HEADER_CLIENT_PLATFORM = 'X-Client-Platform';

    public function start(
        StartGoogleConnectionRequest $request,
        StartGoogleConnectionAction $action,
    ): JsonResponse {
        $capability = $request->filled('capability')
            ? (string) $request->input('capability')
            : null;

        $platform = $this->resolveRequestedPlatform($request);

        $url = $action->execute(
            userId: (int) $request->user()->id,
            capability: $capability,
            platform: $platform,
        );

        return response()->json([
            'redirect_url' => $url,
        ]);
    }

    public function callback(Request $request, HandleGoogleCallbackAction $action): Response
    {
        $stateParam = $request->query('state');
        $codeParam  = $request->query('code');

        // Resolve the state row once. The platform stored on the row
        // determines the redirect target.
        $state = is_string($stateParam) && $stateParam !== ''
            ? app(OAuthStateRepository::class)->findByState($stateParam)
            : null;

        $redirectBase = $this->resolveRedirectBase($state);
        $isMobile = $state !== null && $state->isMobile();

        if ($redirectBase === null) {
            // Configuration error. Do not leak internal details.
            return response()->json(['error' => 'server_misconfigured'], 500);
        }

        if (! is_string($stateParam) || $stateParam === '') {
            return $this->failureRedirect($redirectBase, $isMobile, 'oauth_state_invalid');
        }

        if (! is_string($codeParam) || $codeParam === '') {
            return $this->failureRedirect($redirectBase, $isMobile, 'oauth_code_exchange_failed');
        }

        try {
            $action->execute($codeParam, $stateParam);
        } catch (OAuthException $e) {
            return $this->failureRedirect($redirectBase, $isMobile, $e->errorCode());
        } catch (Throwable) {
            return $this->failureRedirect($redirectBase, $isMobile, 'oauth_connection_failed');
        }

        return $this->successRedirect($redirectBase, $isMobile);
    }

    /**
     * Read the X-Client-Platform header. Only the exact value "mobile"
     * (case-insensitive, trimmed) is recognised. Every other value —
     * including a missing header — falls back to web.
     */
    private function resolveRequestedPlatform(Request $request): string
    {
        $value = $request->header(self::HEADER_CLIENT_PLATFORM);

        if (is_string($value) && strtolower(trim($value)) === OAuthState::PLATFORM_MOBILE) {
            return OAuthState::PLATFORM_MOBILE;
        }

        return OAuthState::PLATFORM_WEB;
    }

    /**
     * Look up the base redirect URL from the state's platform. Unknown
     * or missing state defaults to web.
     */
    private function resolveRedirectBase(?OAuthState $state): ?string
    {
        $platform = $state?->platform ?? OAuthState::PLATFORM_WEB;

        $key = $platform === OAuthState::PLATFORM_MOBILE
            ? 'connections.frontend_redirect_mobile'
            : 'connections.frontend_redirect';

        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function successRedirect(string $base, bool $isMobile): Response
    {
        if ($isMobile) {
            return redirect()->away($this->appendQuery($base, 'status', 'success'));
        }

        // Web behaviour is preserved: ?status=connected
        return redirect()->away($this->appendQuery($base, 'status', 'connected'));
    }

    private function failureRedirect(string $base, bool $isMobile, string $errorCode): Response
    {
        if ($isMobile) {
            $url = $this->appendQuery($base, 'status', 'failed');
            $url = $this->appendQuery($url, 'error', $errorCode);

            return redirect()->away($url);
        }

        // Web behaviour is preserved: ?error=<code>
        return redirect()->away($this->appendQuery($base, 'error', $errorCode));
    }

    private function appendQuery(string $base, string $key, string $value): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.rawurlencode($key).'='.rawurlencode($value);
    }
}
