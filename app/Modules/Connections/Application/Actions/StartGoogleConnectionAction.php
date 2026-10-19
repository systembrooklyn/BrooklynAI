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

        // Determine if we need to force consent
        $prompt = 'select_account';
        $existingConnections = $this->connections->listForUser($userId);

        foreach ($existingConnections as $connection) {
            if ($connection->provider === self::PROVIDER && $connection->credentials->refreshToken !== null) {
                // User already has a valid refresh token. No need to force consent.
                $prompt = 'select_account';
                break;
            } else {
                // No valid refresh token found. Force consent to get one.
                $prompt = 'consent';
            }
        }

        return $this->google->buildAuthorizationUrl($state->state, $scopes);
    }
}
