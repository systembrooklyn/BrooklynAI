<?php

namespace Tests\Unit\Connections;

use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\Entities\OAuthState;
use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ConnectionStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class DomainEntitiesTest extends TestCase
{
    public function test_oauth_state_generation_uses_secure_random_state(): void
    {
        $a = OAuthState::generate(1, 'google', ['openid', 'email', 'profile'], new DateTimeImmutable);
        $b = OAuthState::generate(1, 'google', ['openid', 'email', 'profile'], new DateTimeImmutable);

        $this->assertNotSame($a->state, $b->state);
        $this->assertSame(64, strlen($a->state)); // 32 bytes -> 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $a->state);
        $this->assertSame(['openid', 'email', 'profile'], $a->scopesRequested);
        $this->assertNull($a->consumedAt);
    }

    public function test_oauth_state_expiry_and_consumption(): void
    {
        $now = new DateTimeImmutable('2026-09-16 12:00:00');
        $state = OAuthState::generate(1, 'google', ['openid'], $now);

        $this->assertFalse($state->isExpired($now));
        $this->assertFalse($state->isExpired($now->modify('+599 seconds')));
        $this->assertTrue($state->isExpired($now->modify('+600 seconds')));
        $this->assertTrue($state->isExpired($now->modify('+1 hour')));

        $consumed = new OAuthState(
            id: 1,
            state: $state->state,
            userId: 1,
            provider: 'google',
            scopesRequested: ['openid'],
            expiresAt: $state->expiresAt,
            consumedAt: $now,
        );
        $this->assertTrue($consumed->isConsumed());
    }

    public function test_connection_scope_helpers(): void
    {
        $c = new Connection(
            id: 1,
            userId: 1,
            provider: 'google',
            externalAccountId: 'sub-1',
            email: 'a@example.com',
            displayName: 'A',
            credentials: new ConnectionCredentials('t', null, null),
            scopes: ['openid', 'email', 'profile'],
            status: ConnectionStatus::Active,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );

        $this->assertTrue($c->hasScope('email'));
        $this->assertFalse($c->hasScope('gmail.readonly'));
        $this->assertTrue($c->hasScopes(['openid', 'profile']));
        $this->assertFalse($c->hasScopes(['openid', 'gmail.readonly']));
    }

    public function test_credentials_expiry(): void
    {
        $now = new DateTimeImmutable('2026-09-16 12:00:00');

        $noExpiry = new ConnectionCredentials('t', null, null);
        $this->assertFalse($noExpiry->isExpired($now));

        $expired = new ConnectionCredentials('t', null, $now->modify('-1 second'));
        $this->assertTrue($expired->isExpired($now));

        $valid = new ConnectionCredentials('t', null, $now->modify('+1 hour'));
        $this->assertFalse($valid->isExpired($now));
    }
}
