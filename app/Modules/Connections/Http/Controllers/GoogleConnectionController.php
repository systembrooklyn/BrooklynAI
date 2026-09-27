<?php

namespace App\Modules\Connections\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Connections\Application\Actions\HandleGoogleCallbackAction;
use App\Modules\Connections\Application\Actions\StartGoogleConnectionAction;
use App\Modules\Connections\Core\Exceptions\OAuthException;
use App\Modules\Connections\Http\Requests\StartGoogleConnectionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleConnectionController extends Controller
{
    public function start(
        StartGoogleConnectionRequest $request,
        StartGoogleConnectionAction $action,
    ): JsonResponse {
        $capability = $request->filled('capability')
            ? (string) $request->input('capability')
            : null;

        $url = $action->execute(
            userId: (int) $request->user()->id,
            capability: $capability,
        );

        return response()->json([
            'redirect_url' => $url,
        ]);
    }

    public function callback(Request $request, HandleGoogleCallbackAction $action): Response
    {
        $redirectBase = config('connections.frontend_redirect');

        if (! is_string($redirectBase) || $redirectBase === '') {
            // Configuration error. Do not leak internal details.
            return response()->json(['error' => 'server_misconfigured'], 500);
        }

        $state = $request->query('state');
        $code = $request->query('code');

        if (! is_string($state) || $state === '') {
            return redirect()->away($this->appendQuery($redirectBase, 'error', 'oauth_state_invalid'));
        }

        if (! is_string($code) || $code === '') {
            return redirect()->away($this->appendQuery($redirectBase, 'error', 'oauth_code_exchange_failed'));
        }

        try {
            $action->execute($code, $state);
        } catch (OAuthException $e) {
            return redirect()->away($this->appendQuery($redirectBase, 'error', $e->errorCode()));
        } catch (Throwable) {
            return redirect()->away($this->appendQuery($redirectBase, 'error', 'oauth_connection_failed'));
        }

        return redirect()->away($this->appendQuery($redirectBase, 'status', 'connected'));
    }

    private function appendQuery(string $base, string $key, string $value): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.rawurlencode($key).'='.rawurlencode($value);
    }
}
