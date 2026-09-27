<?php

namespace Tests\Unit\Connections;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ResolvedGoogleCredentialsTest extends TestCase
{
    public function test_no_expiry_means_never_expired(): void
    {
        $credentials = new ResolvedGoogleCredentials(
            accessToken: 'token',
            refreshToken: 'refresh',
            expiresAt: null,
        );

        $this->assertFalse($credentials->isExpired(new DateTimeImmutable));
    }

    public function test_future_expiry_is_not_expired(): void
    {
        $now = new DateTimeImmutable('2026-09-16 12:00:00');

        $credentials = new ResolvedGoogleCredentials(
            accessToken: 'token',
            refreshToken: 'refresh',
            expiresAt: $now->modify('+1 hour'),
        );

        $this->assertFalse($credentials->isExpired($now));
    }

    public function test_past_expiry_is_expired(): void
    {
        $now = new DateTimeImmutable('2026-09-16 12:00:00');

        $credentials = new ResolvedGoogleCredentials(
            accessToken: 'token',
            refreshToken: 'refresh',
            expiresAt: $now->modify('-1 second'),
        );

        $this->assertTrue($credentials->isExpired($now));
    }

    public function test_equal_expiry_is_considered_expired(): void
    {
        $now = new DateTimeImmutable('2026-09-16 12:00:00');

        $credentials = new ResolvedGoogleCredentials(
            accessToken: 'token',
            refreshToken: 'refresh',
            expiresAt: $now,
        );

        $this->assertTrue($credentials->isExpired($now));
    }
}
