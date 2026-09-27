<?php

namespace App\Modules\Connections\Application\Actions;

use App\Modules\Connections\Core\Contracts\OAuthGateway;
use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\Exceptions\OAuthException;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Core\Repositories\OAuthStateRepository;
use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ConnectionStatus;
use DateTimeImmutable;

final class HandleGoogleCallbackAction
{
    public const PROVIDER = 'google';

    public const REQUIRED_SCOPES = ['openid', 'email', 'profile'];

    public function __construct(
        private readonly OAuthStateRepository $states,
        private readonly ConnectionRepository $connections,
        private readonly OAuthGateway $oauth,
    ) {}

    public function execute(string $code, string $rawState): Connection
    {
        $now = new DateTimeImmutable;

        // 1. Consume OAuth state atomically (CAS)
        $state = $this->states->findByState($rawState);
        if ($state === null) {
            throw OAuthException::invalidState();
        }
        if ($state->isConsumed()) {
            throw OAuthException::replayedState();
        }
        if ($state->isExpired($now)) {
            throw OAuthException::expiredState();
        }
        if (! $this->states->markConsumedIfNotYet((int) $state->id, $now)) {
            throw OAuthException::replayedState();
        }

        // 2. Exchange the authorization code
        $tokens = $this->oauth->exchangeAuthorizationCode($code);
        $accessToken = $tokens['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw OAuthException::codeExchangeFailed();
        }

        // 3. Resolve granted scopes + external account identity
        $access = $this->oauth->resolveGrantedAccess($accessToken);

        $grantedScopes = is_array($access['granted_scopes'] ?? null)
            ? array_values(array_filter($access['granted_scopes'], 'is_string'))
            : [];

        if (! empty(array_diff(self::REQUIRED_SCOPES, $grantedScopes))) {
            throw OAuthException::scopeInvalid();
        }

        $sub = $access['external_account_id'] ?? null;
        if (! is_string($sub) || $sub === '') {
            throw OAuthException::accountFetchFailed();
        }

        $email = is_string($access['email'] ?? null) && $access['email'] !== ''
            ? $access['email']
            : null;

        $displayName = is_string($access['display_name'] ?? null) && $access['display_name'] !== ''
            ? $access['display_name']
            : null;

        // 4. Upsert by (user_id, provider, external_account_id)
        $existing = $this->connections->findByUserAndExternalAccount(
            $state->userId,
            self::PROVIDER,
            $sub,
        );

        // Refresh-token fallback: treat null/empty as "not returned by provider".
        // Preserve the existing refresh token if we have one.
        $refreshToken = $tokens['refresh_token'] ?? null;
        if (
            (! is_string($refreshToken) || $refreshToken === '')
            && $existing !== null
        ) {
            $refreshToken = $existing->credentials->refreshToken;
        }
        if (! is_string($refreshToken) || $refreshToken === '') {
            $refreshToken = null;
        }

        $expiresIn = $tokens['expires_in'] ?? null;
        $expiresAt = is_int($expiresIn)
            ? $now->modify('+'.$expiresIn.' seconds')
            : null;

        $credentials = new ConnectionCredentials(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
        );

        $connection = new Connection(
            id: $existing?->id,
            userId: $state->userId,
            provider: self::PROVIDER,
            externalAccountId: $sub,
            email: $email ?? $existing?->email,
            displayName: $displayName ?? $existing?->displayName,
            credentials: $credentials,
            scopes: $grantedScopes,
            status: ConnectionStatus::Active,
            createdAt: $existing?->createdAt ?? $now,
            updatedAt: $now,
        );

        return $this->connections->save($connection);
    }
}
