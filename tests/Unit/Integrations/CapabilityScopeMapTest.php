<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Application\Services\CapabilityScopeMap;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class CapabilityScopeMapTest extends TestCase
{
    private const SCOPE_SEND = 'https://www.googleapis.com/auth/gmail.send';

    public function test_gmail_capability_resolves_to_send_only(): void
    {
        $map = new CapabilityScopeMap;

        $scopes = $map->resolve('gmail');

        $this->assertCount(1, $scopes);
        $this->assertSame([self::SCOPE_SEND], $scopes);
    }

    public function test_gmail_capability_does_not_request_restricted_scopes(): void
    {
        $map = new CapabilityScopeMap;

        $scopes = $map->resolve('gmail');

        $this->assertNotContains('https://www.googleapis.com/auth/gmail.readonly', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/gmail.compose', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/gmail.modify', $scopes);
        $this->assertNotContains('https://www.googleapis.com/auth/gmail.labels', $scopes);
    }

    public function test_unknown_capability_throws(): void
    {
        $map = new CapabilityScopeMap;

        $this->expectException(InvalidArgumentException::class);

        $map->resolve('unknown_capability');
    }

    public function test_has_returns_true_for_gmail(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertTrue($map->has('gmail'));
    }

    public function test_has_returns_false_for_unknown_capability(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertFalse($map->has('unknown_capability'));
    }

    public function test_capabilities_lists_gmail_and_drive(): void
    {
        $map = new CapabilityScopeMap;

        $this->assertSame(['gmail', 'drive'], $map->capabilities());
    }
}
