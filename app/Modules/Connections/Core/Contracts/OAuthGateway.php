<?php

namespace App\Modules\Connections\Core\Contracts;

/**
 * Provider-agnostic OAuth boundary for the Connections module.
 *
 * Implementations live in Infrastructure. Core must not depend on
 * Google-specific or framework-specific types.
 */
interface OAuthGateway
{
    /**
     * Build the provider authorization URL for the given OAuth state and scopes.
     */
    public function buildAuthorizationUrl(string $state, array $scopes): string;

    /**
     * Exchange an authorization code for tokens.
     *
     * @return array{
     *     access_token?: string,
     *     refresh_token?: string,
     *     expires_in?: int,
     *     scope?: string,
     *     id_token?: string
     * }
     */
    public function exchangeAuthorizationCode(string $code): array;

    /**
     * Resolve granted scopes and external account identity from an access token.
     *
     * @return array{
     *     granted_scopes?: array<int, string>,
     *     external_account_id?: string|null,
     *     email?: string|null,
     *     display_name?: string|null
     * }
     */
    public function resolveGrantedAccess(string $accessToken): array;
}
