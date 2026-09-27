<?php

namespace App\Modules\Connections\Infrastructure\Google;

use App\Modules\Connections\Core\Contracts\OAuthGateway;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleOAuthClient implements OAuthGateway
{
    private const AUTHORIZE_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const TOKENINFO_ENDPOINT = 'https://oauth2.googleapis.com/tokeninfo';

    private const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function buildAuthorizationUrl(string $state, array $scopes): string
    {
        $params = [
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => (string) config('services.google.connections_redirect'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'select_account',
            'include_granted_scopes' => 'true',
        ];

        return self::AUTHORIZE_ENDPOINT.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => (string) config('services.google.client_id'),
            'client_secret' => (string) config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => (string) config('services.google.connections_redirect'),
        ]);

        if (! $response->successful()) {
            return [];
        }

        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    public function resolveGrantedAccess(string $accessToken): array
    {
        $tokenInfo = $this->safeGet(self::TOKENINFO_ENDPOINT, [
            'access_token' => $accessToken,
        ]);

        $userInfo = $this->safeGet(self::USERINFO_ENDPOINT, [
            'access_token' => $accessToken,
        ]);

        $scopeString = is_string($tokenInfo['scope'] ?? null) ? $tokenInfo['scope'] : '';
        $scopes = array_values(array_filter(
            preg_split('/\s+/', trim($scopeString)) ?: [],
            static fn ($s) => $s !== ''
        ));

        $email = null;
        if (is_string($tokenInfo['email'] ?? null) && $tokenInfo['email'] !== '') {
            $email = $tokenInfo['email'];
        } elseif (is_string($userInfo['email'] ?? null) && $userInfo['email'] !== '') {
            $email = $userInfo['email'];
        }

        return [
            'granted_scopes' => $scopes,
            'external_account_id' => is_string($tokenInfo['sub'] ?? null) ? $tokenInfo['sub'] : null,
            'email' => $email,
            'display_name' => is_string($userInfo['name'] ?? null) ? $userInfo['name'] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeGet(string $url, array $query): array
    {
        try {
            $response = Http::get($url, $query);
            if (! $response->successful()) {
                return [];
            }

            $data = $response->json();

            return is_array($data) ? $data : [];
        } catch (Throwable) {
            return [];
        }
    }
}
