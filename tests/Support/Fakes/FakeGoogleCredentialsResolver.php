<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;

final class FakeGoogleCredentialsResolver implements GoogleCredentialsResolver
{
    /** @var array<int, array{userId: int, connectionId: ?int}> */
    public array $calls = [];

    public ?\Throwable $throws = null;

    public function resolve(int $userId, ?int $connectionId = null): ResolvedGoogleCredentials
    {
        $this->calls[] = ['userId' => $userId, 'connectionId' => $connectionId];

        if ($this->throws !== null) {
            throw $this->throws;
        }

        return new ResolvedGoogleCredentials(
            accessToken: 'fake-access-token',
            refreshToken: 'fake-refresh-token',
            expiresAt: null,
        );
    }
}
