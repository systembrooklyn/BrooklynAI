<?php

namespace App\Modules\Connections\Application\Actions;

use App\Modules\Connections\Core\Contracts\OAuthGateway;
use App\Modules\Connections\Core\Entities\OAuthState;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Core\Repositories\OAuthStateRepository;
use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use DateTimeImmutable;

final class StartGoogleConnectionAction
{
    public const PROVIDER = 'google';

    public const SCOPES = ['openid', 'email', 'profile'];

    public function __construct(
        private readonly OAuthStateRepository $states,
        private readonly OAuthGateway $google,
        private readonly CapabilityScopeMap $capabilities,
        private readonly ConnectionRepository $connections,
    ) {}

    public function execute(
        int $userId,
        ?string $capability = null,
        string $platform = OAuthState::PLATFORM_WEB,
    ): string {
        $scopes = self::SCOPES;

        if ($capability !== null) {
            $scopes = array_values(array_unique(array_merge(
                $scopes,
                $this->capabilities->resolve($capability),
            )));
        }

        $state = OAuthState::generate(
            userId: $userId,
            provider: self::PROVIDER,
            scopes: $scopes,
            now: new DateTimeImmutable,
            platform: $platform,
        );

        $this->states->save($state);

        // Default to smooth UX (account picker only)
        $prompt = 'select_account';

        $existingConnections = $this->connections->listForUser($userId);

        foreach ($existingConnections as $connection) {
            // If ANY existing Google connection is missing a refresh token,
            // we must force the consent screen to acquire a new one.
            if ($connection->provider === self::PROVIDER && $connection->credentials->refreshToken === null) {
                $prompt = 'consent';
                break;
            }
        }

        // CRITICAL FIX: Pass the $prompt variable into the method!
        return $this->google->buildAuthorizationUrl($state->state, $scopes, $prompt);
    }
}
