<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;

final class FakeGoogleCredentialsResolver implements GoogleCredentialsResolver
{
    public ?\Throwable $throws = null;

    /** @var array<int, array{userId: int, connectionId: ?int}> */
    public array $calls = [];

    public function resolve(int $userId, ?int $connectionId = null): ResolvedGoogleCredentials
    {
        $this->calls[] = ['userId' => $userId, 'connectionId' => $connectionId];

        if ($this->throws !== null) {
            throw $this->throws;
        }

        return new ResolvedGoogleCredentials(
            accessToken: 'test-access',
            refreshToken: 'test-refresh',
            expiresAt: null,
        );
    }
}
