<?php

namespace App\Modules\Connections\Application\Actions;

use App\Modules\Connections\Core\Contracts\OAuthGateway;
use App\Modules\Connections\Core\Entities\OAuthState;
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

        return $this->google->buildAuthorizationUrl($state->state, $scopes);
    }
}
